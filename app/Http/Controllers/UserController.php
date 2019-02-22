<?php

namespace App\Http\Controllers;

use App\User;
use Illuminate\Http\Request;
use JWTAuth;
use JWTAuthException;
//use Flutterwave\Bvn;
//use Flutterwave\Flutterwave;
use GuzzleHttp\Client;
use App\VerifyBVN;
use App\BVN;
use Gbxnga\SmartSMSSolutions\SmartSMSSolutions;

class UserController extends Controller
{
    private function generateVerificationCode(): string 
    {
        $count = 0;
        $code = '';
        while ($count < 5) {
            $code .= mt_rand(0, 9);
            $count++;
        }
        return $code;
    }
    private function splitCodeAddComma(string $code)
    {
        $splittedString = '';
        $array = str_split($code); 

        foreach ($array as $char) {
            $splittedString .= ', '. $char;
        }
        return $splittedString;

    }
    private function prefixNumber($number)
    {

        $truncated_number = substr($number, 1);
        return "+234{$truncated_number}";
        return "0{$truncated_number}";
        //return "+234{$truncated_number}";
    }
    public function verify_sent_code_for_bvn(Request $request){

        $user = JWTAuth::toUser($request->token);

        $verification = VerifyBVN::where('user_id', $user->id)->orderBy('created_at','DESC')->first(['code']);
         
        if( $verification->code == $request->code){

            $verification->status = "verified";
            $verification->save();

            $user->bvn_verified = 'true';
            $user->save();

            $response = ['success' => true, 'data' => $user, 'message'=>"BVN verified"];
        }else{
            $response = ['success' => false, 'data' => $user, 'message'=>"Code doesnt match"];
        }

        return response()->json($response, 200);

    }
    public function verify_bvn(Request $request){
 
        $user = JWTAuth::toUser($request->token);
        if( !empty($request->bvn) ){

            $client = new Client();
            $secretKey = "sk_live_adeb0a9c21710208095ff0c914209d652bcab7fb";
    
            $response = $client->request('GET', "https://api.paystack.co/bank/resolve_bvn/$request->bvn", [
                'headers' => [
                    'Authorization' => 'Bearer ' . $secretKey,
                ],
            ]);
            if ($response->getStatusCode() == "200") {  
    
                $decoded_response = json_decode($response->getBody());
                $person = $decoded_response->data;
 
                $code = $this->generateVerificationCode();
                $verification = new VerifyBVN([
                    'bvn'=>$person->bvn,
                    'user_id'=> $user->id,
                    'phone' => $person->mobile,
                    'code' => $code,
                    // 'status'=>'unverified'
                ]);
                $verification->save();

                $data = new BVN([
                    'user_id'=> $user->id,
                    'bvn'=>$person->bvn,
                    'firstname' => $person->first_name,
                    'lastname' => $person->last_name,
                    'dob' => $person->dob,
                    'mobile' => $person->mobile,
                    'formatted_dob' =>$person->formatted_dob
                ]);
                $data->save(); 

                // send code:

                $sms = new SmartSMSSolutions("iamblizzyy@gmail.com","BetaGrades2018");
                $sender = "ASAPDROP";
                $recipient = $person->mobile;
                $recipient = "08159229330";
                $message = "Your ASAPDROP BVN verification code is: $code"; 
                
                //echo $sms->getBalance();

                $sms_status = $sms->sendMessage($sender,$recipient,$message);

                $response = [

                            'success' => true, 
                            'data' => 'Provide sent code', 
                            'status'=>$decoded_response->status,
                            'message' => $decoded_response->message,
                            'follow_id' => $verification->id,
                            'sent_to' => $person->mobile,
                            'sms_status' => $sms_status
                        ];
            }
            else{
                $response = ['success' => false, 'data' => 'Cound not fetch BVN details'];
            }

        }else{
            $response = ['success' => false, 'data' => 'BVN cannot be empty'];

        }

        return response()->json($response, 200);

 
    }


    private function convertTextToSpeech(string $text){
 
        $polly = \AWS::createClient('polly');
        $result_polly = $polly->synthesizeSpeech([ 
            "OutputFormat" => "mp3", 
            "Text" => $text, 
            "TextType" => "text", 
            "VoiceId" => "Amy" ]);

        $resultData_polly = $result_polly->get("AudioStream")->getContents();
        $file_name = time()."-polly.mp3"; 

        $s3 = \AWS::createClient('s3');
        $result_s3 = $s3->putObject(array(
            'Bucket'     => 'myaws-thread-to-speech-codes',
            'Key'        => $file_name,
            "ACL" => "public-read", 
            "Body" => $resultData_polly, 
            "ContentType" => "audio/mpeg"
            //'SourceFile' => '/the/path/to/the/file/you/are/uploading.ext',
        ));
        return $file_name;
        return $result_s3['ObjectURL'];

    }

    public function callPhone(Request $request)
    {  
        $user = JWTAuth::toUser($request->token);
        if( !empty($request->bvn) ){

            $twilio_number = env("TWILIO_NUMBER");

            $data = BVN::where("bvn",$request->bvn)->get()->first();

            $number_with_prefix = $this->prefixNumber($data->mobile);
            $code = $this->generateVerificationCode();
            $splittedCode = $this->splitCodeAddComma($code);

            $message = "Hello. Your ASAP drop verification code is: {$splittedCode}. Again: {$splittedCode}. Goodbye.";

            $file_name = $this->convertTextToSpeech($message);

            $client = new TwilioClient(env("TWILIO_SID"), env("TWILIO_TOKEN"));
            $client->account->calls->create(  
                $number_with_prefix,
                $twilio_number,
                array( 
                    "url" => "https://api.asapfoods.com.ng/get-voice-message/$file_name"
                )
            );

            $verification = new VerifyBVN([
                'bvn'=>$request->bvn,
                'user_id'=> $user->id,
                'phone' => $data->mobile,
                'code' => $code,
                // 'status'=>'unverified'
            ]);
            $verification->save();

            return response()->json(["success"=>true, "name"=>$file_name], 200);
        }
        else return response()->json(["success"=>false, "request"=>$request], 200);

        

        //return response()->json(["success"=>true, "url"=>$file_name], 201);



        //$sql = "INSERT INTO `sentcodes` (`phone`, `code`) VALUES ('{$number_with_prefix}', '{$code}');";
        //$row = \DB::select($sql);

        
    }
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

    public function update_password(Request $request){

        $user = JWTAuth::toUser($request->token);

        if (\Hash::check($request->old_pass, $user->password)) {
            
            if ($request->new_pass === $request->new_pass_repeat)
            {
                $user->password = \Hash::make($request->new_pass);
                $user->save();
 
                $response = ['success' => true, 'data' => $user, 'message'=>"Success! Password successfully changed"];

            } else $response = ['success' => false, 'data' => $user, 'message'=>"password repeat doesnt match"];
            
          
        } else $response = ['success' => false, 'data' => $user, 'message'=>"Old password is wrong"];

        return response()->json($response, 201);
    }

    public function update(Request $request)
    {


        $user = JWTAuth::toUser($request->token);
        if ( $user->update($request->all()) ){
            $response = ['success' => true, 'data' => $user, 'message'=>"Profile updated successfully"];
        }else{
            $response = ['success' => false, 'data' => $user, 'message'=>"Failed to update profile"];
        }

        return response()->json($response, 201);
    }

    public function upload_photo(Request $request)
    {

        $user = JWTAuth::toUser($request->token);

        $image_name = ''; // default profile image

        if ($request->hasFile('photo'))
        {
            $image = $request->file('photo');

            $input['imagename'] = time().'.'.$image->getClientOriginalExtension();

            $destinationPath = public_path('/uploads/profile-photos');

            $image->move($destinationPath, $input['imagename']);

            $image_name = $input['imagename'];

            $user->photo = $image_name; /** Update image only if one was uploaded */

            $user->save();

            $response = ['success' => true, 'data' => $user, 'message'=>"Photo upload successfull"];
        }  else {
            $response = ['success' => false, 'data' => $user, 'message'=>"Photo upload failed"];
        }

        

        return response()->json($response, 201);
    }
}
