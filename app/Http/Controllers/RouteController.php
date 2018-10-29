<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use JWTAuth;
use App\Route;

class RouteController extends Controller
{
    public function add(Request $request)
    {
        //return response()->json(["request"=>$request->token], 200);
        $user = JWTAuth::toUser($request->token);
        $route = new Route([
            'user_id' => $user->id,
            'origin' => $request->origin,
            'destination' => $request->destination,
            'origin_coordinates' => $request->origin_coordinates,
            'destination_coordinates' => $request->destination_coordinates,
        ]); 

        $route->save();

        $response = ['success' => true, 'message' => 'Route saved', 'data'=>$route];
    

        return response()->json($response, 200);
    }

    public function all()
    {
        $routes = Route::all();

        $response = ['success' => true, 'routes'=>$routes];
    

        return response()->json($response, 200);
    }

    public function delete(Request $request)
    {
        $user = JWTAuth::toUser($request->token);
        $route = Route::find($id);
        if($route){
            // check if route belongs to user
            if ($route->user_id == $user->id){

                if ($route->delete()){
                    $response = ['success' => true, 'message'=>"Route deleted"];
                }else{
                    $response = ['success' => true, 'message'=>"Could not delete route"];
                }               

            }else{
                $response = ['success' => false, 'message'=>"Route does not belong to user"];
            }          
        }else{
            $response = ['success' => false, 'message'=>"Route doesnt exist"];

        }
        return response()->json($response, 200);

    }
}
