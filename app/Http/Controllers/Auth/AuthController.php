<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Auth\OtpCode;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|string|email',
            'password' => 'required|string',
        ]);
        if ($validator->fails()) {
            return sendError('Validation Error.', $validator->errors(), 400);
        }

        $credentials = request(['email', 'password']);

        if (!Auth::attempt($credentials)) {
            return sendError('Invalid email or password', [], 400);
        }

        $success['userData'] = User::where('email', $request->email)->first();
        $tokenResult = $success['userData']->createToken('API TOKEN');
        $success['token'] = $tokenResult->plainTextToken;

        return sendResponse('Login Successfull', $success);
    }

    public function register(Request $request)
    {

        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:255'],
            'uname' => ['required', 'max:255'],
            'email' => 'required|string|unique:users',
            'password' => [
                'required',
                'string',
                'min:8',
            ],
            'phone' => [
                'required',
                'string',
                'min:10',
                'unique:users'
            ],
            // 'confirm_password' => 'required_with:password|same:password',
        ]);

        if ($validator->fails()) {
            return sendError('Validation Error.', $validator->errors(), 400);
        }
        $user = User::create([
            'name'  => $request->name,
            'uname'  => $request->uname,
            'email' => $request->email,
            'phone' => $request->phone,
            'password' => Hash::make($request->password),
        ]);
        return sendResponse('User Registered Successfully', $user);
    }


    public function sendOtp(Request $request, $type)
    {
        // Validate email input
        $request->validate([
            'email' => 'required|email'
        ]);

        // Retrieve the user by email
        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return response()->json(['errors' => ['email' => ['Invalid Email']]], 404);
        }

        // Clean up old reset records (older than 30 minute)
        OtpCode::where(function ($query) use ($request, $type) {
            $query->where('created_at', '>', Carbon::now()->subMinutes(10))
                ->where('type', $type);
        })
            ->orWhere(function ($query) use ($request, $type) {
                $query->where('type', $type)
                    ->where('email', $request->email);
            })
            ->delete();


        // Generate a unique token and a 6-digit OTP
        $token = Str::random(25);
        $otp = random_int(100000, 999999);

        // Create a new reset password record
        // $resetPassword = OtpCode::create([
        //     'email' => $request->email,
        //     'token' => $token,
        //     'otp' => $otp,
        //     'type' => 'forgotpassword'
        // ]);

        // // Send the OTP email if the record was created successfully
        // if ($resetPassword) {
        //     Mail::to($request->email)->queue(new ForgotPassword($user->fname, $otp));
        // }

        // // Return a success response with the token
        // return response()->json([
        //     'status' => true,
        //     'message' => 'Code sent successfully',
        //     'token' => $token
        // ], 200);
    }
}
