<?php

namespace App\Http\Controllers;

use App\Models\SiteLink;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Exception;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class SiteLinkController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        try{
            $user = Auth::user();

            $search = $request->input('search', '');
            
            $query = SiteLink::where('user_id', $user->id)->orderBy('created_at', 'desc');

            if ($search) {
                $query->where('title', 'like', '%' . $search . '%');
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

            $case = SiteLink::find($id);
            if (!$case || $case->user_id !== $user->id) throw new Exception('Case not found', 404);

            return response()->json($case, 200);
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
            if (!$user || $user->role !== 'technician') {
                return response()->json(['error' => 'Unauthorized'], 401);
            }

            $validator = Validator::make(
                $request->all(),
                [
                    'title' => 'required|string|max:255',
                    'url' => 'required|url|max:255',
                    'duration' => 'required|in:30,60,300,1800,3600,43200,86400',
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
                ]
            );

            if ($validator->fails()) {
                throw new Exception($validator->errors()->first(), 400);
            }

            DB::beginTransaction();
            // here we hit the url to test that site is working or not by their status code
            // if status code is 200 then site is working otherwise down

            $response = Http::get($request->url);
            $status = 'working';
            if ($response->status() === 200) {
                $status = 'working';
            } else {
                $status = 'down';
            }

            $data = SiteLink::create([
                'user_id' => $user->id,
                'title' => $request->title,
                'url' => $request->url,
                'duration' => $request->duration,
                'status' => $status,
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
            if (!$user || $user->role !== 'technician') {
                return response()->json(['error' => 'Unauthorized'], 401);
            }

            DB::beginTransaction();
            $data = SiteLink::findOrFail($id);
            if (!$data) throw new Exception('Case not found', 404);

            $data->delete();
            DB::commit();
            return response()->json(['message' => 'Case deleted successfully'], 200);

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
            if (!$user || $user->role !== 'technician') {
                return response()->json(['error' => 'Unauthorized'], 401);
            }

            $validator = Validator::make(
                $request->all(),
                [
                    'doctor' => 'required|string|max:255',
                    'hospital' => 'required|string|max:255',
                    'country' => 'required|string|max:255',
                    'status' => 'required|in:VT,SVT,A.FIB.,PVC,L-AVRT,R-AVRT,AVNRT,AF,AFL,AT,Other',
                    'ep_products' => 'nullable|array',
                    'ep_products.*' => 'string|in:Columbus 3D,Stimulator,OptimAblate Pump,OptimAblate RF',
                    'note' => 'nullable|string',
                    'images' => 'nullable|array',
                    'images.*' => 'image|max:2048',
                    'bq_data' => 'nullable|array',
                    'date' => 'required|date',
                    'ref_no' => 'nullable|string|max:255',
                ],
                [
                    'doctor.required' => 'Doctor name is required.',
                    'hospital.required' => 'Hospital name is required.',
                    'country.required' => 'Country is required.',
                    'status.required' => 'Status is required.',
                    'date.required' => 'Date is required.',
                    'images.array' => 'Images must be an array.',
                    'images.*.image' => 'Invalid image selected.',
                    'ep_products.array' => 'EP products must be an array.',
                    'ep_products.*.in' => 'Invalid EP product selected.',
                    'ep_products.*.string' => 'EP products must be strings.',
                    'bq_data.array' => 'Barcode data must be an array.',
                    'ref_no.string' => 'Reference number must be a string.',
                ]
            );

            if ($validator->fails()) {
                throw new Exception($validator->errors()->first(), 400);
            }

            DB::beginTransaction();

            $case = CaseLog::findOrFail($id);
            if (!$case) {
                throw new Exception('Case not found', 404);
            }

            // Format EP products
            $products = $request->has('ep_products') && is_array($request->ep_products)
                ? implode(',', $request->ep_products)
                : null;

            // Handle existing images
            $existingImages = $case->images ? json_decode($case->images, true) : [];
            
            // Handle multiple image upload
            $newImages = [];
            if ($request->hasFile('images') && is_array($request->file('images'))) {
                // Delete existing images if new images are being uploaded
                if (!empty($existingImages)) {
                    foreach ($existingImages as $existingImage) {
                        $oldImagePath = public_path($existingImage);
                        if (file_exists($oldImagePath)) {
                            unlink($oldImagePath);
                        }
                    }
                }

                // Upload new images
                foreach ($request->file('images') as $index => $image) {
                    $image_name = 'case-image-' . time() . '-' . $index . '.' . $image->getClientOriginalExtension();
                    $image->move(public_path('case-image'), $image_name);
                    $newImages[] = 'case-image/' . $image_name;
                }
            } else {
                // Keep existing images if no new images are uploaded
                $newImages = $existingImages;
            }

            $formattedDate = Carbon::parse($request->date)->format('Y-m-d');

            // Update the case with new data
            $case->update([
                'doctor' => $request->doctor,
                'hospital' => $request->hospital,
                'country' => $request->country,
                'status' => $request->status,
                'ep_products' => $products,
                'note' => $request->note,
                'date' => $formattedDate,
                'images' => count($newImages) > 0 ? json_encode($newImages) : null,
                'bq_data' => $request->has('bq_data') ? json_encode($request->bq_data) : null,
                'ref_no' => $request->ref_no ?? null,
            ]);

            DB::commit();

            return response()->json($case, 200);

        } catch (QueryException $e) {
            DB::rollBack();
            return response()->json(['DB error' => $e->getMessage()], 422);
        } catch (Exception $e) {
            DB::rollBack();
            return response()->json(['error' => $e->getMessage()], $e->getCode() ?: 500);
        }
    }
}
