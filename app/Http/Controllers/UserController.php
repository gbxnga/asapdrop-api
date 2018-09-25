<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\User;
use JWTAuth;
use JWTAuthException;

class UserController extends Controller
{
    private function getToken($email, $password)
    {
        $token = null;
        //$credentials = $request->only('email', 'password');
        try {
            //$e = compact('email','password');
            //dd($e);
            if (!$token = JWTAuth::attempt( compact('email','password') )) {
                return response()->json([
                    'response' => 'error',
                    'message' => 'Password or email is invalid',
                    'token'=>$token
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
            $response = ['success'=>true, 'data'=>$user];           
        }
        else 
          $response = ['success'=>false, 'data'=>'Record doesnt exists'];
      
        return response()->json($response, 200);
    }
    public function register(Request $request)
    { 
        //return response()->json(["success"=>true,"data"=>["hi"=>"hello"]], 201);
        $payload = [
            'password'=>\Hash::make($request->password),
            'email'=>$request->email,
            'name'=>$request->name,
            'auth_token'=> ''
        ];
                  
        $user = new \App\User($payload);
        if ($user->save())
        {
            
            $token = self::getToken($request->email, $request->password); // generate user token
            
            if (!is_string($token))  return response()->json(['success'=>false,'data'=>'Token generation failed'], 201);
            
            $user = \App\User::where('email', $request->email)->get()->first();
            
            $user->auth_token = $token; // update user token
            
            $user->save();
            
            $response = ['success'=>true, 'data'=>$user];        
        }
        else
            $response = ['success'=>false, 'data'=>'Couldnt register user'];
        
        
        return response()->json($response, 201);
    }
    public function register_oauth(Request $request)
    {  
        $payload = [
            'password'=>\Hash::make($request->oauth_id),
            'email'=>$request->email,
            'name'=>$request->name,
            'oauth_uid'=>$request->oauth_id,
            'oauth_token'=>$request->oauth_token,
            'oauth_provider'=>$request->oauth_provider,
            'auth_token'=> ''
        ];
                  
        $user = new \App\User($payload);
        if ($user->save())
        {
            
            $token = self::getToken($request->email, $request->oauth_id); // generate user token
            
            if (!is_string($token))  return response()->json(['success'=>false,'data'=>'Token generation failed'], 201);
            
            $user = \App\User::where('email', $request->email)->get()->first();
            
            $user->auth_token = $token; // update user token
            
            $user->save();
            
            $response = ['success'=>true, 'data'=>$user];        
        }
        else
            $response = ['success'=>false, 'data'=>'Couldnt register user'];
        
        
        return response()->json($response, 201);
    } 
}
