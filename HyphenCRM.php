<?php

class HyphenCRM
{
  /**
   * Send a lead to Hyphen
   *
   * Config Example:
   * $config = [
   *  "username" => "{USERNAME}",
   *  "api_key" => "{API_KEY}",
   *  "endpoint" => "{ENDPOINT_URL}",
   *  "homeBuilderID" => "{HOMEBUILDER_ID}",
   * ];
   *
   * API Documentation - Create Lead
   * @link https://documenter.getpostman.com/view/4704246/TVYQ3Ew9
   * @param array $inputs
   * @param array $config
   * @param boolean [$die=true] Whether to die with a 500 response on errors.
   *                            Services like Wufoo expect this type of error response.
   * @return boolean
   */
  public function send($inputs, $config = [], $die = true)
  {
    global $webhook;
    
    $config = is_array($config) ? $config : [];
    
    $success = false;
    $result = null;
    $status = null;
    $error = null;
    
    $url = $config['endpoint'] . '/api/auth/external/customers/create';
    
    // Set up default values
    $defaults = [
      'fname' => $webhook->firstname(),
      'lname' => $webhook->lastname(),
      'email' => $webhook->email(),
      'phone' => $webhook->phone(),
      'stage' => 'Lead',
      'psrc' => '',
      'grade' => '',
      'cntm' => '',
    ];
    
    $mapping = [
      'fname' => ['first_name', 'firstname'],
      'lname' => ['last_name', 'lastname'],
      'email' => ['email', 'email_address'],
      'phone' => ['phone', 'phone1', 'phone_number'],
      'stage' => [],
      'psrc' => [],
      'grade' => [],
      'cntm' => [],
    ];
    
    $custom = isset($config['custom']) && is_array($config['custom']) ? $config['custom'] : [];
    
    $webhook->map($inputs, $defaults, $mapping, $custom);
    
    // Testing
    if ($webhook->test) {
      $protect = [
        'lname',
        'phone',
        'email',
      ];
      
      $json = $webhook->protectArray($inputs, 4, $protect);
      $json = json_encode($json, JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT);
      $webhook->info($json);
    }
    
    // Validate API Key
    if (empty($config['api_key'])) {
      $error = 'API key is missing';
    }
    
    // Send lead
    if (!$error && $webhook->send) {
      // Build json
      $json = (object) $inputs;
      
      $json = json_encode($json);
      
      $ch = curl_init();

      curl_setopt_array($ch, array(
        CURLOPT_URL => 'https://api-crm-external.hyphensolutions.com/prod/api/auth/external/token/get',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_POSTFIELDS =>'{ "username" : ' . $config['username'] . ' , "key" : ' . $config['api_key'] . '  }',
        CURLOPT_HTTPHEADER => array(
          'Content-Type: text/plain'
        ),
      ));

      $getBearer = curl_exec($ch);
      $decodedResponse = json_decode($getBearer);
      $authToken = $decodedResponse->code->access_token;

      curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_POSTFIELDS => '{"customers": 
          [ {
           "fname": "'.$inputs['fname'].'",
           "lname": "'.$inputs['lname'].'",
           "email": "'.$inputs['email'].'",
           "phone": "'.$inputs['phone'].'",
           "stage": "Lead",
           "psrc": "'.$inputs['psrc'].'",
           "grade": "'.$inputs['grade'].'",
           "cntm": "'.$inputs['cntm'].'",
           "noteSub":"Webhook",
           "hb_id": "'.$config['homeBuilderID'].'"
       }],
       "hb_id": "'.$config['homeBuilderID'].'"}',
        CURLOPT_HTTPHEADER => [
          "Accept: */*",
          "Cache-Control: no-cache",
          "Connection: keep-alive",
          "Content-Length: " . strlen($json),
          "Content-Type: application/json",
          "Authorization: Bearer ". $authToken,
        ],
      ]);

      $result = curl_exec($ch);
      $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
      $error = curl_error($ch);

      curl_close($ch);

      $success = $status && ($status == 200 || $status == 204 || $status == 202);

      // Decode JSON response
      if ($success) {
        $json = JsonUtils::decode($result);
        
        if (JsonUtils::isObject($json)) {
          if (isset($json->errors) && $json->errors) {
            $success = false;
          }
        } else {
          $success = false;
        }
      }
    }
    
    // Log status
    if ($success) {
      $webhook->success("Lead: \"{$inputs['fname']}\" | Lead successfully submitted to Acculynx");
    } else {
      $webhook->error("Lead: \"{$inputs['fname']}\" | Unable to submit lead to Acculynx", true, $inputs);
      $webhook->error("Result: " . print_r($result, 1));
      $webhook->error("Status: " . print_r($status, 1));
      $webhook->error("Error: " . print_r($error, 1));
      
      if ($die) {
        echo "Error: Unable to submit lead to Acculynx\n\n";
        echo print_r($status, 1) . "\n\n";
        echo print_r($error, 1) . "\n\n";
      
        http_response_code(500);
        die();
      }
    }
    
    return $success;
  }
}
