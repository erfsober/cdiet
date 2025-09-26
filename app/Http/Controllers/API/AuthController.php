<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\API\CompleteProfileRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Models\VerificationCode;
use App\Models\WeightChangeLog;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller {
    /**
     * @OA\Post(
     *     path="/api/auth/get-verification-code/via-sms",
     *     summary="Get verification code via sms",
     *     operationId="getVerificationCodeViaSms",
     *     tags={"Auth"},
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\MediaType(
     *             mediaType="application/json",
     *
     *             @OA\Schema(
     *
     *                 @OA\Property(
     *                     property="phone_number",
     *                     type="string",
     *                     example="09372033422",
     *                     description=""
     *                 ),
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(response=200, description="Successful", @OA\JsonContent()),
     *     @OA\Response(response=204, description="Successful"),
     *     @OA\Response(response=400, description="Bad request"),
     *     @OA\Response(response=404, description="Not Found"),
     *     @OA\Response(response=401, description="Unauthenticated", @OA\JsonContent()),
     *     @OA\Response(response=422, description="Unprocessable Content", @OA\JsonContent()),
     * )
     */
    public function getVerificationCodeViaSms ( Request $request ) {
        $this->validate($request , [
            'phone_number' => [
                'required' ,
                'regex:/^(0)?9\d{9}$/i' ,
            ] ,
        ]);
        $phone_number = util()->standardPhoneNumber($request->get('phone_number'));
        $code = rand(1000 , 9999);
        if ( VerificationCode::inCoolDown($phone_number) ) {
            util()->throwError('لطفا چند دقیقه دیگر تلاش کنید!');
        }
        if ( VerificationCode::exceedsDailyLimit($phone_number) ) {
            util()->throwError('شما بیش از حد مجاز در روز درخواست کرده‌اید. لطفا فردا مجددا تلاش کنید!');
        }
        VerificationCode::query()
                        ->create([
                                     'phone_number' => $phone_number ,
                                     'code' => $code ,
                                 ]);
        util()->toSms($phone_number , $code);

        return response()->json([
                                    'status' => true ,
                                    'message' => 'کد با موفقیت ارسال شد!' ,
                                ]);
    }

    /**
     * @OA\Post(
     *     path="/api/auth/submit-verification-code/via-sms",
     *     summary="Submit verification code via sms",
     *     operationId="submitVerificationCodeViaSms",
     *     tags={"Auth"},
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\MediaType(
     *             mediaType="application/json",
     *
     *             @OA\Schema(
     *
     *                 @OA\Property(
     *                     property="phone_number",
     *                     type="string",
     *                     example="09372033422",
     *                     description=""
     *                 ),
     *                 @OA\Property(
     *                     property="code",
     *                     type="string",
     *                     example="1234",
     *                     description=""
     *                 ),
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(response=200, description="Successful", @OA\JsonContent()),
     *     @OA\Response(response=204, description="Successful"),
     *     @OA\Response(response=400, description="Bad request"),
     *     @OA\Response(response=404, description="Not Found"),
     *     @OA\Response(response=401, description="Unauthenticated", @OA\JsonContent()),
     *     @OA\Response(response=422, description="Unprocessable Content", @OA\JsonContent()),
     * )
     */
    public function submitVerificationCodeViaSms ( Request $request ) {
        $this->validate($request , [
            'phone_number' => [
                'required' ,
                'regex:/^(0)?9\d{9}$/i' ,
            ] ,
            'code' => [ 'required' ] ,
        ]);
        $phone_number = util()->standardPhoneNumber($request->get('phone_number'));
        $code = $request->get('code');
        $verification_code = VerificationCode::query()
                                             ->notUsed()
                                             ->where([
                                                         'phone_number' => $phone_number ,
                                                         'code' => $code ,
                                                     ])
                                             ->latest()
                                             ->first();
        if ( !$verification_code ) {
            util()->throwError('کد وارد شده صحیح نمیباشد!');
        }
        $verification_code->used_at = now();
        $verification_code->save();
        $user = User::query()
                    ->firstOrCreate([
                                        'phone_number' => $phone_number ,
                                    ]);

        return response()->json([
                                    'token' => $user->createToken($phone_number)->accessToken ,
                                    'user' => UserResource::make($user) ,
                                ]);
    }

    /**
     * @OA\Post(
     *     path="/api/auth/get-verification-code/via-email",
     *     summary="Get verification code via email",
     *     operationId="getVerificationCodeViaEmail",
     *     tags={"Auth"},
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\MediaType(
     *             mediaType="application/json",
     *
     *             @OA\Schema(
     *
     *                 @OA\Property(
     *                     property="email",
     *                     type="string",
     *                     example="erfaansabouri@gmail.com",
     *                     description=""
     *                 ),
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(response=200, description="Successful", @OA\JsonContent()),
     *     @OA\Response(response=204, description="Successful"),
     *     @OA\Response(response=400, description="Bad request"),
     *     @OA\Response(response=404, description="Not Found"),
     *     @OA\Response(response=401, description="Unauthenticated", @OA\JsonContent()),
     *     @OA\Response(response=422, description="Unprocessable Content", @OA\JsonContent()),
     * )
     */
    public function getVerificationCodeViaEmail ( Request $request ) {
        $this->validate($request , [
            'email' => [
                'required' ,
                'email' ,
            ] ,
        ]);
        $email = $request->get('email');
        $code = rand(1000 , 9999);
        if ( VerificationCode::inCoolDown($email) ) {
            throw ValidationException::withMessages([
                                                        'error' => 'لطفا چند دقیقه دیگر تلاش کنید!' ,
                                                    ]);
        }
        VerificationCode::query()
                        ->create([
                                     'email' => $email ,
                                     'code' => $code ,
                                 ]);
        // todo : send email $code
        util()->toDiscord("Email: $email - Code: $code");

        return response()->json([
                                    'status' => true ,
                                    'message' => 'کد با موفقیت ارسال شد!' ,
                                ]);
    }

    /**
     * @OA\Post(
     *     path="/api/auth/submit-verification-code/via-email",
     *     summary="Submit verification code via email",
     *     operationId="submitVerificationCodeViaEmail",
     *     tags={"Auth"},
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\MediaType(
     *             mediaType="application/json",
     *
     *             @OA\Schema(
     *
     *                 @OA\Property(
     *                     property="email",
     *                     type="string",
     *                     example="erfaansabouri@gmail.com",
     *                     description=""
     *                 ),
     *                 @OA\Property(
     *                     property="code",
     *                     type="string",
     *                     example="1234",
     *                     description=""
     *                 ),
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(response=200, description="Successful", @OA\JsonContent()),
     *     @OA\Response(response=204, description="Successful"),
     *     @OA\Response(response=400, description="Bad request"),
     *     @OA\Response(response=404, description="Not Found"),
     *     @OA\Response(response=401, description="Unauthenticated", @OA\JsonContent()),
     *     @OA\Response(response=422, description="Unprocessable Content", @OA\JsonContent()),
     * )
     */
    public function submitVerificationCodeViaEmail ( Request $request ) {
        $this->validate($request , [
            'email' => [
                'required' ,
                'email' ,
            ] ,
            'code' => [ 'required' ] ,
        ]);
        $email = $request->get('email');
        $code = $request->get('code');
        $verification_code = VerificationCode::query()
                                             ->notUsed()
                                             ->where([
                                                         'email' => $email ,
                                                         'code' => $code ,
                                                     ])
                                             ->latest()
                                             ->first();
        if ( !$verification_code ) {
            util()->throwError('کد وارد شده صحیح نمیباشد!');
        }
        $verification_code->used_at = now();
        $verification_code->save();
        $user = User::query()
                    ->firstOrCreate([
                                        'email' => $email ,
                                    ]);

        return response()->json([
                                    'token' => $user->createToken($email)->accessToken ,
                                    'user' => UserResource::make($user) ,
                                ]);
    }

    /**
     * @OA\Post(
     *     path="/api/auth/verify-google-sign-in",
     *     summary="Verify google sign in",
     *     operationId="verifyGoogleSignIn",
     *     tags={"Auth"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="application/json",
     *             @OA\Schema(
     *                 @OA\Property(
     *                     property="id_token",
     *                     type="string",
     *                     example="ABCD",
     *                     description=""
     *                 ),
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(response=200, description="Successful", @OA\JsonContent()),
     *     @OA\Response(response=204, description="Successful"),
     *     @OA\Response(response=400, description="Bad request"),
     *     @OA\Response(response=404, description="Not Found"),
     *     @OA\Response(response=401, description="Unauthenticated", @OA\JsonContent()),
     *     @OA\Response(response=422, description="Unprocessable Content", @OA\JsonContent()),
     * )
     */
    public function verifyGoogleSignIn ( Request $request ) {
        $this->validate($request , [
            'id_token' => 'required' ,
        ]);
        $id_token = $request->get('id_token');
        try {
            $json_result = file_get_contents("https://oauth2.googleapis.com/tokeninfo?id_token=" . $id_token);
        }
        catch ( Exception $e ) {
            return util()->throwError('خطا در دریافت اطلاعات از گوگل');
        }
        $result = json_decode($json_result);
        if ( !isset($result->email) ) {
            return util()->throwError('اطلاعات معتبر نیست!');
        }
        $user = User::query()
                    ->firstOrCreate([
                                        'email' => $result->email ,
                                    ]);

        return response()->json([
                                    'token' => $user->createToken($result->email)->accessToken ,
                                    'user' => UserResource::make($user) ,
                                ]);
    }

    /**
     * @OA\Get(
     *     path="/api/profile/show",
     *     summary="Show Profile",
     *     tags={"Auth"},
     *     @OA\Response(response=200, description="Successful", @OA\JsonContent()),
     *     @OA\Response(response=204, description="Successful"),
     *     @OA\Response(response=400, description="Bad request"),
     *     @OA\Response(response=404, description="Not Found"),
     *     @OA\Response(response=401, description="Unauthenticated", @OA\JsonContent()),
     *     @OA\Response(response=422, description="Unprocessable Content", @OA\JsonContent()),
     *     security={
     *         {
     *             "bearerAuth": {}
     *         }
     *     },
     * )
     */
    public function getProfile () {
        $user = Auth::user();

        return response()->json([ 'user' => UserResource::make($user) ]);
    }

    /**
     * @OA\Post(
     *     path="/api/profile/update",
     *     summary="Update Profile",
     *     operationId="updateProfile",
     *     tags={"Auth"},
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\MediaType(
     *             mediaType="application/json",
     *
     *             @OA\Schema(
     *
     *     @OA\Property(
     *         property="full_name",
     *         type="string",
     *         description="The user's full name",
     *         example="Erfan Sabouri"
     *     ),
     *     @OA\Property(
     *         property="email",
     *         type="string",
     *         nullable=true,
     *         description="The user's email address",
     *         example="erfaansabouri@gmail.com"
     *     ),
     *     @OA\Property(
     *         property="phone_number",
     *         type="string",
     *         nullable=true,
     *         description="phone_number",
     *         example="0937"
     *     ),
     *     @OA\Property(
     *         property="sex",
     *         type="string",
     *         description="The user's gender",
     *         enum={"مرد", "زن"}
     *     ),
     *     @OA\Property(
     *         property="pregnant_status",
     *         type="boolean",
     *         description="Whether the user is pregnant",
     *         example=true,
     *         required={"sex=زن"}
     *     ),
     *     @OA\Property(
     *         property="lactation_status",
     *         type="boolean",
     *         description="Whether the user is lactating",
     *         example=true,
     *         required={"sex=زن"}
     *     ),
     *     @OA\Property(
     *         property="birthday",
     *         type="string",
     *         description="The user's birthday",
     *         example="1360/07/15"
     *     ),
     *     @OA\Property(
     *         property="exercise",
     *         type="string",
     *         description="The user's exercise level",
     *         enum={"کم", "متوسط", "زیاد" , "خیلی زیاد"},
     *     ),
     *     @OA\Property(
     *         property="height",
     *         type="number",
     *         format="float",
     *         description="The user's height in meters",
     *         example=177
     *     ),
     *     @OA\Property(
     *         property="weight",
     *         type="number",
     *         format="float",
     *         description="The user's weight in kilograms",
     *         example=70.5
     *     ),
     *     @OA\Property(
     *          property="target_weight",
     *          type="number",
     *          format="float",
     *          description="The user's target_weight in kilograms",
     *          example=70.5
     *      ),
     *     @OA\Property(
     *         property="goal",
     *         type="string",
     *         description="The user's fitness goal",
     *         enum={"کاهش وزن", "تثبیت وزن", "افزایش وزن"}
     *     ),
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(response=200, description="Successful", @OA\JsonContent()),
     *     @OA\Response(response=204, description="Successful"),
     *     @OA\Response(response=400, description="Bad request"),
     *     @OA\Response(response=404, description="Not Found"),
     *     @OA\Response(response=401, description="Unauthenticated", @OA\JsonContent()),
     *     @OA\Response(response=422, description="Unprocessable Content", @OA\JsonContent()),
     *     security={
     *         {
     *             "bearerAuth": {}
     *         }
     *     },
     * )
     */
    public function updateProfile ( CompleteProfileRequest $request ) {
        $user = Auth::user();
        if ( $request->get('phone_number') && User::wherePhoneNumber($request->get('phone_number'))
                 ->where('id' , '!=' , $user->id)
                 ->exists() ) {
            return response()->json([
                                        'status' => false ,
                                        'message' => 'شماره قبلا انتخاب شده' ,
                                    ]);
        }
        if ( $request->get('email') && User::whereEmail($request->get('email'))
                 ->where('id' , '!=' , $user->id)
                 ->exists() ) {
            return response()->json([
                                        'status' => false ,
                                        'message' => 'ایمیل قبلا انتخاب شده' ,
                                    ]);
        }
        $user->fill($request->validated());
        $user->register_completed_at = now();
        $user->save();
        if ( $request->has('weight') && $user->wasChanged('weight') ) {
            WeightChangeLog::query()
                           ->create([
                                        'user_id' => $user->id ,
                                        'weight' => $request->get('weight'),
                                    ]);
        }
        $user = $user->refresh();

        return response()->json([ 'user' => UserResource::make($user) ]);
    }

    /**
     * @OA\Post(
     *     path="/api/profile/set-avatar",
     *     summary="Set avatar",
     *     tags={"Auth"},
     *
     *      @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *
     *             @OA\Schema(
     *
     *                 @OA\Property(
     *                     property="avatar",
     *                     type="file",
     *                     format="binary",
     *                 ),
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(response=200, description="Successful", @OA\JsonContent()),
     *     @OA\Response(response=204, description="Successful"),
     *     @OA\Response(response=400, description="Bad request"),
     *     @OA\Response(response=404, description="Not Found"),
     *     @OA\Response(response=401, description="Unauthenticated", @OA\JsonContent()),
     *     @OA\Response(response=422, description="Unprocessable Content", @OA\JsonContent()),
     *     security={
     *         {
     *             "bearerAuth": {}
     *         }
     *     },
     * )
     */
    public function setAvatar ( Request $request ) {
        $request->validate([
                               'avatar' => [
                                   'file' ,
                                   'image' ,
                               ] ,
                           ]);
        $user = Auth::user();
        $user->addMediaFromRequest('avatar')
             ->toMediaCollection('avatar');

        return response()->json([
                                    'status' => true ,
                                    'message' => 'آواتار با موفقیت ذخیره شد.' ,
                                ]);
    }

    /**
     * @OA\Post(
     *     path="/api/profile/update-firebase-token",
     *     summary="Update firebase token",
     *     tags={"Auth"},
     *
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\MediaType(
     *             mediaType="application/json",
     *
     *             @OA\Schema(
     *
     *     @OA\Property(
     *         property="firebase_token",
     *         type="string",
     *         description="firebase_token",
     *         example="firebase_token"
     *     ),
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(response=200, description="Successful", @OA\JsonContent()),
     *     @OA\Response(response=204, description="Successful"),
     *     @OA\Response(response=400, description="Bad request"),
     *     @OA\Response(response=404, description="Not Found"),
     *     @OA\Response(response=401, description="Unauthenticated", @OA\JsonContent()),
     *     @OA\Response(response=422, description="Unprocessable Content", @OA\JsonContent()),
     *     security={
     *         {
     *             "bearerAuth": {}
     *         }
     *     },
     * )
     */
    public function updateFirebaseToken ( Request $request ) {
        $request->validate([
                               'firebase_token' => [
                                   'required' ,
                                   'string' ,
                               ] ,
                           ]);
        $user = Auth::user();
        $user->firebase_token = $request->get('firebase_token');
        $user->save();

        return util()->simpleSuccess('توکن با موفقیت آپدیت شد');
    }

    /**
     * @OA\Post(
     *     path="/api/profile/toggle-allow-notification",
     *     summary="Toggle allow notification",
     *     tags={"Auth"},
     *     @OA\Response(response=200, description="Successful", @OA\JsonContent()),
     *     @OA\Response(response=204, description="Successful"),
     *     @OA\Response(response=400, description="Bad request"),
     *     @OA\Response(response=404, description="Not Found"),
     *     @OA\Response(response=401, description="Unauthenticated", @OA\JsonContent()),
     *     @OA\Response(response=422, description="Unprocessable Content", @OA\JsonContent()),
     *     security={
     *         {
     *             "bearerAuth": {}
     *         }
     *     },
     * )
     */
    public function toggleAllowNotification () {
        $user = Auth::user();
        $user->allow_notification = !$user->allow_notification;
        $user->save();

        return util()->simpleSuccess('تنظیمات اعلانات با موفقیت آپدیت شد');
    }
}
