<?php

namespace App\Http\Controllers;

use App\Models\SiteCheck;
use App\Models\SiteLink;
use App\Services\GooglePageSpeedService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Exception;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Log;

class SiteLinkController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        try{
            $user = Auth::user();

            $title = $request->input('title', '');
            $status = $request->input('status', '');
            $duration = $request->input('duration', ''); //no of days
            
            $query = SiteLink::select('site_links.*', 'site_checks.*','site_links.id as id')
            ->where('site_links.user_id', $user->id)
            ->join('site_checks', 'site_checks.site_link_id', '=', 'site_links.id')
            ->orderBy('site_links.created_at', 'desc');

            if ($title) {
                $query->where('site_links.title', 'like', '%' . $title . '%');
            }         

            if ($duration) {
                // here we filter records based on duration in days created_at
                $query->where('site_links.created_at', '>=', Carbon::now()->subDays($duration));
            }

            switch ($status) {
                case 'active':
                    $query->where('site_links.is_active', 'active');
                    break;
                case 'inactive':
                    $query->where('site_links.is_active', 'inactive');
                    break;
            }

            $result = $query->paginate(10);

            return response()->json($result, 200);

        }catch(QueryException $e){
            return response()->json(['DB error' => $e->getMessage()], 422);
        }catch(Exception $e){
            return response()->json(['error' => $e->getMessage()], $e->getCode() ?: 500);
        }
    }

    public function show($id): JsonResponse
    {
        try{
            $user = Auth::user();

            $data = SiteLink::select('site_links.*','site_checks.*')
            ->join('site_checks', 'site_checks.site_link_id', '=', 'site_links.id')
            ->where('site_links.id', $id)
            ->orderBy('site_checks.checked_at', 'desc')
            ->first();

            return response()->json($data, 200);
        }catch(QueryException $e){
            return response()->json(['DB error' => $e->getMessage()], 422);
        }catch(Exception $e){
            return response()->json(['error' => $e->getMessage()], $e->getCode() ?: 500);
        }
    }

    public function store(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();

            // 1️⃣ Limit check
            if ($user->activeLinks()->count() >= $user->linkLimit()) {
                return response()->json([
                    'message' => 'Link limit reached. Upgrade plan.'
                ], 403);
            }
            // 2️⃣ Validation

            $validator = Validator::make(
                $request->all(),
                [
                    'title' => 'required|string|max:255',
                    'url' => 'required|url|max:255',
                    'duration' => 'required|in:30,60,300,1800,3600,43200,86400',
                    'notify_email' => 'required|boolean',
                    'notify_sms' => 'required|boolean',
                    'notify_push' => 'required|boolean',
                ],
                [                    
                    'title.required' => 'Title is required.',
                    'title.string' => 'Title must be a string.',
                    'title.max' => 'Title may not be greater than 255 characters.',
                    'url.required' => 'URL is required.',
                    'url.url' => 'Invalid URL format.',
                    'url.max' => 'URL may not be greater than 255 characters.',
                    'duration.required' => 'Duration is required.',
                    'duration.in' => 'Invalid duration selected.',
                    'notify_email.boolean' => 'Notify email must be true or false.',
                    'notify_sms.boolean' => 'Notify SMS must be true or false.',
                    'notify_push.boolean' => 'Notify push must be true or false.',
                ]
            );
            // extra validation here..
            $siteCheck = SiteLink::where('user_id', $user->id)
                ->where('url', $request->url)
                ->where('is_disabled', false)
                ->first();

            if ($siteCheck) {
                throw new Exception('You are already monitoring this URL.', 400);
            }

            if ($validator->fails()) {
                throw new Exception($validator->errors()->first(), 400);
            }

            DB::beginTransaction();
            // here we hit the url to test that site is working or not by their status code
            // if status code is 200 then site is working otherwise down
            // $check =$this->isValidAndAccessible($request->url);
            // if(!$check)throw new Exception('Site is Invalid', 400);


            $metrics = probe($request->url, (int)$request->duration, 15);
            $service = new GooglePageSpeedService();
            $pageSpeedData = $service->getCombinedData($request->url);
            // Log::info('PageSpeed Data: ', ['data' => $pageSpeedData]);
            if(!$metrics)throw new Exception('Site is Invalid', 400);
            $data = SiteLink::create([
                'user_id' => $user->id,
                'title' => $request->title,
                'url' => $request->url,
                'duration' => $request->duration, 
                'notify_email' => $request->notify_email,
                'notify_sms' => $request->notify_sms,
                'notify_push' => $request->notify_push,
            ]);

            SiteCheck::create([
                'site_link_id' => $data->id,
                'status' => $metrics['status'],
                'response_time_ms' => $metrics['response_time_ms'],
                'ssl_days_left'    => $metrics['ssl_days_left'],
                'html_bytes'       => $metrics['html_bytes'],
                'assets_bytes'       => $metrics['assets_bytes'],
                'checked_at' => $metrics['last_checked_at'],
                'scores' => json_encode($pageSpeedData['scores']) ?? null,
            ]);

            DB::commit();

            return response()->json($data, 200);
        } catch (QueryException $e) {
            DB::rollBack();
            return response()->json(['DB error' => $e->getMessage()], 422);
        } catch (Exception $e) {
            DB::rollBack();
            return response()->json(['error' => $e->getMessage()], $e->getCode() ?: 500);
        }
    }


    public function destroy($id): JsonResponse
    {
        try{
            $user = Auth::user();

            DB::beginTransaction();
            $data = SiteLink::find($id);
            if (!$data) throw new Exception('Site not found', 404);

            $data->delete();
            // $info = SiteCheck::where('site_link_id',$id)->first();
            // $info->delete();
            DB::commit();
            return response()->json(['message' => 'Site deleted successfully'], 200);

        }catch(QueryException $e){
            DB::rollBack();
            return response()->json(['DB error' => $e->getMessage()], 422);
        }catch(Exception $e){
            DB::rollBack();
            return response()->json(['error' => $e->getMessage()], $e->getCode() ?: 500);
        }   
    }

    public function update(Request $request, $id): JsonResponse
    {
        try {
            $user = Auth::user();

            $validator = Validator::make(
                $request->all(),
                [
                    'title' => 'required|string|max:255',
                    'url' => 'required|url|max:255',
                    'duration' => 'required|in:30,60,300,1800,3600,43200,86400',
                    'notify_email' => 'required|boolean',
                    'notify_sms' => 'required|boolean',
                    'notify_push' => 'required|boolean',
                ],
                [                    
                    'title.required' => 'Title is required.',
                    'title.string' => 'Title must be a string.',
                    'title.max' => 'Title may not be greater than 255 characters.',
                    'url.required' => 'URL is required.',
                    'url.url' => 'Invalid URL format.',
                    'url.max' => 'URL may not be greater than 255 characters.',
                    'duration.required' => 'Duration is required.',
                    'duration.in' => 'Invalid duration selected.',
                    'notify_email.boolean' => 'Notify email must be true or false.',
                    'notify_sms.boolean' => 'Notify SMS must be true or false.',
                    'notify_push.boolean' => 'Notify push must be true or false.',
                ]
            );

            if ($validator->fails()) {
                throw new Exception($validator->errors()->first(), 400);
            }

            DB::beginTransaction();

            $data = SiteLink::findOrFail($id);
            if (!$data) throw new Exception('Record not found', 404);

            $data->update([
                'user_id' => $user->id,
                'title' => $request->title,
                'url' => $request->url,
                'duration' => $request->duration,
                'notify_email' => $request->notify_email,
                'notify_sms' => $request->notify_sms,
                'notify_push' => $request->notify_push,
            ]);

            DB::commit();

            return response()->json($data, 200);
        } catch (QueryException $e) {
            DB::rollBack();
            return response()->json(['DB error' => $e->getMessage()], 422);
        } catch (Exception $e) {
            DB::rollBack();
            return response()->json(['error' => $e->getMessage()], $e->getCode() ?: 500);
        }
    }

    public function updateStatus(Request $request, $id): JsonResponse
    {
        try {
            $user = Auth::user();

            $validator = Validator::make(
                $request->all(),
                [
                    'is_active' => 'required|in:active,inactive',
                ],
                [                    
                    'is_active.required' => 'Status is required.',
                    'is_active.in' => 'Invalid status selected.',
                ]
            );

            if ($validator->fails()) {
                throw new Exception($validator->errors()->first(), 400);
            }

            DB::beginTransaction();

            $data = SiteLink::findOrFail($id);
            if (!$data) {
                throw new Exception('Record not found', 404);
            }


            $data->update([
                'is_active' => $request->is_active,
            ]);

            DB::commit();

            return response()->json($data, 200);
        } catch (QueryException $e) {
            DB::rollBack();
            return response()->json(['DB error' => $e->getMessage()], 422);
        } catch (Exception $e) {
            DB::rollBack();
            return response()->json(['error' => $e->getMessage()], $e->getCode() ?: 500);
        }
    }


    private function isValidAndAccessible(string $url, int $timeout = 15)
    {
        // A quick check to ensure the string looks like a valid URL first
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return false;
        }

        try {
            // 1. Attempt a lightweight HEAD request first.
            // This is generally faster and uses less bandwidth since it doesn't download the body.
            $response = Http::timeout($timeout)->head($url);

            // If the HEAD request was NOT successful, we try a GET as a fallback.
            // Some servers block HEAD requests or are configured poorly.
            if (!$response->successful()) {
                $response = Http::timeout($timeout)->get($url);
            }

            // 2. Check for a successful status code (200-299).
            // The ->successful() method handles this range for us.
            if($response->successful()) {
                return true;
            }

        } catch (ConnectionException $e) {
            // Catches errors like DNS resolution failure, connection refusal, or request timeout.
            // This means the URL is unreachable at the network level.
            return false;
        } catch (Exception $e) {
            // Catches any other unexpected errors during the process.
            // You could log the error here if needed: \Log::warning("URL validation error: " . $e->getMessage());
            return false;
        }
    }

    public function notifyToggle($id): JsonResponse
    {
        try {
            DB::beginTransaction();

            $data = SiteLink::findOrFail($id);
            if (!$data) {
                throw new Exception('Record not found', 404);
            }
            $data->update([
                'is_notify' => !$data->is_notify,
            ]);
            
            DB::commit();
            $message = $data->is_notify ? 'Notifications enabled' : 'Notifications disabled';
            return response()->json($message, 200);
        } catch (QueryException $e) {
            DB::rollBack();
            return response()->json(['DB error' => $e->getMessage()], 422);
        } catch (Exception $e) {
            DB::rollBack();
            return response()->json(['error' => $e->getMessage()], $e->getCode() ?: 500);
        }
    }
}
