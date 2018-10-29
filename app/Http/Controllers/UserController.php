<?php

namespace App\Http\Controllers;

use App\User;
use Illuminate\Http\Request;
use JWTAuth;
use JWTAuthException;

class UserController extends Controller
{
    private function getToken($email, $password)
    {
        $token = null; 
        try { 
            if (!$token = JWTAuth::attempt(compact('email', 'password'))) {
                return response()->json([
                    'response' => 'error',
                    'message' => 'Password or email is invalid',
                    'token' => $token,
                ]);
            }
        } catch (JWTAuthException $e) {
            return response()->json([
                'response' => 'error',
                'message' => 'Token creation failed',
            ]);
        }
        return $token;
    }
    public function login(Request $request)
    {
        $user = User::where('email', $request->email)->get()->first();
        if ($user && \Hash::check($request->password, $user->password)) // The passwords match...
        {
            $token = self::getToken($request->email, $request->password);
            $user->auth_token = $token;
            $user->save();
            $response = ['success' => true, 'data' => $user];
        } else {
            $response = ['success' => false, 'data' => 'Record doesnt exists'];
        }

        return response()->json($response, 200);
    }
    public function register(Request $request)
    {
        //return response()->json(["success"=>true,"data"=>["hi"=>"hello"]], 201);
        $payload = [
            'password' => \Hash::make($request->password),
            'email' => $request->email,
            'name' => $request->name,
            'auth_token' => '',
        ];

        $user = new \App\User($payload);
        if ($user->save()) {

            $token = self::getToken($request->email, $request->password); // generate user token

            if (!is_string($token)) {
                return response()->json(['success' => false, 'data' => 'Token generation failed'], 201);
            }

            $user = \App\User::where('email', $request->email)->get()->first();

            $user->auth_token = $token; // update user token

            $user->save();

            $response = ['success' => true, 'data' => $user];
        } else {
            $response = ['success' => false, 'data' => 'Couldnt register user'];
        }

        return response()->json($response, 201);
    }
    private static function verifyToken($access_token)
    {
        $fb = new \Facebook\Facebook([
            'app_id' => '1996180800633192',
            'app_secret' => '8fb9381750121a22a5e0c12007f57957',
            'default_graph_version' => 'v2.10',
            //'default_access_token' => '{access-token}', // optional
        ]);

        // Use one of the helper classes to get a Facebook\Authentication\AccessToken entity.
        //   $helper = $fb->getRedirectLoginHelper();
        //   $helper = $fb->getJavaScriptHelper();
        //   $helper = $fb->getCanvasHelper();
        //   $helper = $fb->getPageTabHelper();

        try {
            // Get the \Facebook\GraphNodes\GraphUser object for the current user.
            // If you provided a 'default_access_token', the '{access-token}' is optional.
            $response = $fb->get('/me?fields=name,email,id', $access_token);
        } catch (\Facebook\Exceptions\FacebookResponseException $e) {
            // When Graph returns an error
            return 'Graph returned an error: ' . $e->getMessage();
            exit;
        } catch (\Facebook\Exceptions\FacebookSDKException $e) {
            // When validation fails or other local issues
            return 'Facebook SDK returned an error: ' . $e->getMessage();
            exit;
        }

        return $response->getGraphUser(); 
    }
    public function login_oauth(Request $request)
    {
        $user = User::where('oauth_uid', $request->oauth_uid)->get()->first();
        if ($user) {

            try {
                // verify auth_token
                $info = self::verifyToken($request->oauth_token);

                if (!is_object($info)) {
                    return response()->json(['success' => false, 'data' => 'Facebook Could not verify token', 'error_message' => $info], 201);
                } else {
                    // update record
                    $user->password = \Hash::make($info->getId());
                    $user->oauth_token = $request->oauth_token;
                    $user->oauth_uid = $info->getId();
                    $user->email = $info->getEmail();
                    $user->name = $info->getName();
                    //$user->photo =  $request->photo;

                    $user->save();

                    // login and return
                    $token = self::getToken($user->email, $info->getId()); // generate user token

                    if (!is_string($token)) {
                        return response()->json(['email' => $info->getEmail(), 'id' => $info->getId(), 'user' => $user, 'success' => false, 'data' => 'Token generation failed'], 201);
                    }

                    $user = \App\User::where('email', $user->email)->get()->first();

                    $user->auth_token = $token; // update user token

                    $user->save();

                    $response = ['success' => true, 'data' => $user];

                    return response()->json($response, 201);

                }

            } catch (Exception $e) {
                return response()->json(['success' => false, 'data' => 'Could not verify token'], 201);
            }
        } else {
            return response()->json(['success' => false, 'data' => 'user not registered'], 201);
        }

    }
    public function register_oauth(Request $request)
    {

        // check if user is already registered in
        $user = User::where('oauth_uid', $request->oauth_uid)->get()->first();
        if ($user) {

            return self::login_oauth($request);

        } else {
            $payload = [
                'password' => \Hash::make($request->oauth_uid),
                'email' => $request->email,
                'name' => $request->name,
                'oauth_uid' => $request->oauth_uid,
                'oauth_token' => $request->oauth_token,
                'oauth_provider' => $request->oauth_provider,
                'auth_token' => '',
                'photo' => $request->photo,
            ];

            $user = new \App\User($payload);
            if ($user->save()) {

                $token = self::getToken($request->email, $request->oauth_uid); // generate user token

                if (!is_string($token)) {
                    return response()->json(['user' => $user, 'success' => false, 'data' => 'Token generation failed'], 201);
                }

                $user = User::where('email', $request->email)->get()->first();

                $user->auth_token = $token; // update user token

                $user->save();

                $response = ['success' => true, 'data' => $user];
            } else {
                $response = ['success' => false, 'data' => 'Couldnt register user'];
            }

            return response()->json($response, 201);

        }

    }
}
