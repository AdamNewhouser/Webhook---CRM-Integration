<?php

class AcculynxCRM
{
  /**
   * Send a lead to Acculynx
   *
   * Config Example:
   * $config = [
   *   'api_key' => '{API_KEY}'
   * ];
   *
   * API Documentation - Create Lead
   * @link https://api.acculynx.com/api/v1#tag/Leads/paths/~1api~1v1~1leads/post
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
    
    $url = 'https://api.acculynx.com/api/v1/leads';
    
    // Set up default values
    $defaults = [
      'firstName' => $webhook->firstname(),
      'lastName' => $webhook->lastname(),
      'emailAddress' => $webhook->email(),
      'phoneNumber1' => $webhook->phone(),
      'phoneType1' => 'Home',
      'phoneNumber2' => '',
      'phoneType2' => 'Mobile',
      'phoneNumber3' => '',
      'phoneType3' => 'Work',
      'street' => $webhook->address(),
      'city' => $webhook->city(),
      'state' => $webhook->state(),
      'zip' => $webhook->zip(),
      'country' => $webhook->country('US'),
      'notes' => $webhook->comments()
    ];
    
    $mapping = [
      'firstName' => ['first_name', 'firstname'],
      'lastName' => ['last_name', 'lastname'],
      'emailAddress' => ['email', 'email_address'],
      'phoneNumber1' => ['phone', 'phone1', 'phone_number'],
      'phoneType1' => ['phone_type', 'phone_type1'],
      'phoneNumber2' => ['phone2', 'phone_number2'],
      'phoneType2' => ['phone_type2'],
      'phoneNumber3' => ['phone3', 'phone_number3'],
      'phoneType3' => ['phone_type3'],
      'street' => ['address', 'streetaddress'],
      'city' => ['city'],
      'state' => [],
      'zip' => ['zip_code', 'postal_code'],
      'country' => [],
      'notes' => ['comments']
    ];
    
    $custom = isset($config['custom']) && is_array($config['custom']) ? $config['custom'] : [];
    
    $webhook->map($inputs, $defaults, $mapping, $custom);
    
    // Testing
    if ($webhook->test) {
      $protect = [
        'lastName',
        'phoneNumber1',
        'phoneNumber2',
        'phoneNumber3',
        'emailAddress',
        'street'
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
      
      curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_POSTFIELDS => $json,
        CURLOPT_HTTPHEADER => [
          "Accept: */*",
          "Cache-Control: no-cache",
          "Connection: keep-alive",
          "Content-Length: " . strlen($json),
          "Content-Type: application/json",
          "Authorization: Bearer {$config['api_key']}"
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
      $webhook->success("Lead: \"{$inputs['firstName']}\" | Lead successfully submitted to Acculynx");
    } else {
      $webhook->error("Lead: \"{$inputs['firstName']}\" | Unable to submit lead to Acculynx", true, $inputs);
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
