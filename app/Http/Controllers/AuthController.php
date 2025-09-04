<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\User;
use Exception;
use Hash;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Mail;
use App\Mail\OTPMail;
use Illuminate\Support\Facades\DB;
use App\Models\PasswordResetToken;

class AuthController extends Controller
{
    public function signin(Request $request):JsonResponse
    {
        try{
            $validator = Validator::make($request->all(), [
                'email' => 'required|email',
                'password' => 'required',
            ]);
            if ($validator->fails())throw new Exception($validator->errors()->first(), 422);

            // Conditions
            if (!User::where('email', $request->email)->exists())throw new Exception('Invalid email address or password', 400);


            $user = User::where('email', $request->email)->first();
            if (!Hash::check($request->password, $user->password)) throw new Exception('Invalid email address or password', 400);
            if($user->role != $request->role) throw new Exception('Account not authorized', 400);
            // $user->tokens()->delete();

            $user->update([
                'fcm_token' => $request->fcm_token,
                'last_login_at' => now(),
                'device_id' => $request->device_id,
            ]);

            $token = $user->createToken('manager-token', [$request->role])->plainTextToken; 
            return response()->json(['token' => $token, 'user' => $user], 200);
        }catch(Exception $e){
            return response()->json(['error', $e->getMessage()], $e->getCode() ?: 500);
        }
    }

    public function forgotPassword(Request $request): JsonResponse
    {
        try {
            $validator = validator(
                $request->all(),
                [
                    'email' => 'required|email|exists:users',
                    'role' => 'required|in:technician,manager',
                ],
                [
                    'email.required' => 'Email Address required',
                    'email.email' => 'Invalid Email',
                    'email.exists' => 'Invalid Email Address',
                ]
            );

            if ($validator->fails())
                throw new Exception($validator->errors()->first(), 400);

            $tokenExist = PasswordResetToken::where('email', $request->email)->exists();
            if ($tokenExist)
                PasswordResetToken::where('email', $request->email)->delete();

            //  otp 6 number
            $token = rand(1000, 9999);
            PasswordResetToken::insert([
                'email' => $request->email,
                'token' => $token,
                'created_at' => now()
            ]);

            $user = User::where('email', $request->email)->first();

            Mail::to($request->email)->send(new OTPMail([
                'message' => 'Hi ' . $user->first_name . $user->last_name . 'This is your one time password',
                'otp' => $token,
                'is_url' => false
            ]));
            return response()->json([
                'message' => 'Reset OTP sent successfully',
            ], 200);
        } catch (QueryException $e) {
            return response()->json(['DB error' => $e->getMessage()], 400);
        } catch (Exception $e) {
            return response()->json(['error' => $e->getMessage()], $e->getCode() ?: 500);
        }
    }

    public function resetPassword(Request $request): JsonResponse
    {
        try {
            $validator = validator(
                $request->all(),
                [
                    'token' => 'required|string',

                    'password' => 'nullable|string|min:8',
                ],
                [
                    'token.required' => 'Token required',

                    'password.string' => 'Password must be a string',
                    'password.min' => 'Password must be at least 8 characters',
                ]
            );

            if ($validator->fails())
                throw new Exception($validator->errors()->first(), 400);

            $data = PasswordResetToken::where('token', $request->token)->first();
            if (empty($data))
                throw new Exception('Invalid token', 400);

            // Phase 1: OTP Verified successfully
            if (empty($request->password)) {
                // If no password is provided, just return a success message for OTP verification
                return response()->json([
                    'message' => 'OTP verified successfully',
                ], 200);
            }

            $user = User::where('email', $data->email)->first();
            $user->update([
                'password' => Hash::make($request->password),
            ]);

            PasswordResetToken::where('token', $request->token)->delete();

            return response()->json([
                'message' => 'Password reset successfully',
            ], 200);
        } catch (QueryException $e) {
            return response()->json(['DB error' => $e->getMessage()], 400);
        } catch (Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    public function resendCode(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'email' => 'required|email',
            ], [
                'email.required' => 'Email is required',
                'email.email' => 'Invalid email format',
            ]);

            if ($validator->fails())
                throw new Exception($validator->errors()->first(), 400);

            $user = User::where('email', $request->email)->first();
            if (!$user)
                throw new Exception('User not found', 404);
            $token = rand(1000, 9999);
                PasswordResetToken::where('email', $request->email)->delete();
                PasswordResetToken::insert([
                    'email' => $request->email,
                    'token' => $token,
                    'created_at' => now()
                ]);
                Mail::to($request->email)->send(new OTPMail([
                    'message' => 'Hi, This is your one time password',
                    'otp' => $token
                ]));



            return response()->json(['token' => $token], 200);
        } catch (QueryException $e) {
            return response()->json(['DB error' => $e->getMessage()], 500);
        } catch (Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function changePassword(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            DB::beginTransaction();
            $validator = Validator::make($request->all(), [
                'current_password' => 'required',
                'new_password' => 'required',
            ], [
                'current_password.required' => 'Current password is required',
                'new_password.required' => 'New password is required',
            ]);

            if ($validator->fails())
                throw new Exception($validator->errors()->first(), 400);


            if (!$user || !Hash::check($request->current_password, $user->password)) {
                return response()->json(['message' => 'Current password is mismatch'], 401);
            }
            $user->update([
                'password' => Hash::make($request->new_password),
            ]);
            DB::commit();
            return response()->json(['message' => 'Password changed successfully'], 200);
        } catch (QueryException $e) {
            DB::rollBack();
            return response()->json(['DB error' => $e->getMessage()], 500);
        } catch (Exception $e) {
            DB::rollBack();
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function logout(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            $user->tokens()->delete();
            return response()->json(['message' => 'Logged out successfully'], 200);
        } catch (Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
