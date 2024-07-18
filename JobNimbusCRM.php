<?php

class JobNimbusCRM
{
  /**
   * Sends a lead to Job Nimbus
   *
   * Config Example:
   * $config = [
   *   'api_key' => '{API_KEY}',
   *   'actor' => '{ACCOUNT_EMAIL_ADDRESS}' (optional for handling CREATED_BY)
   * ];
   *
   * REST API - Create a Contact
   * @link https://documenter.getpostman.com/view/3919598/S11PpG4x?version=latest#7ec1541f-7241-4840-9322-0ed83c01d48e
   * @link https://support.jobnimbus.com/zapier-integration
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
    $raw = null;
    
    $url = 'https://app.jobnimbus.com/api1/contacts';
    
    if (isset($config['actor'])) {
      $url .= "?actor={$config['actor']}";
    }
    
    $defaults = [
      'first_name' => $webhook->firstname(),
      'last_name' => $webhook->lastname(),
      'home_phone' => $webhook->phone(),
      'mobile_phone' => '',
      'work_phone' => '',
      'email' => $webhook->email(),
      'address_line1' => $webhook->address(),
      'city' => $webhook->city(),
      'state_text' => $webhook->state(),
      'zip' => $webhook->zip(),
      'record_type_name' => 'Customer',
      'status_name' => 'Lead',
      'source_name' => ''
    ];
    
    $mapping = [
      'first_name' => ['firstname'],
      'last_name' => ['lastname'],
      'home_phone' => ['phone', 'phone1', 'phone_number'],
      'mobile_phone' => ['phone2'],
      'work_phone' => ['phone3'],
      'email' => ['email_address'],
      'address_line1' => ['address', 'streetaddress'],
      'city' => [],
      'state_text' => ['state'],
      'zip' => ['postal_code', 'zip_code'],
      'record_type_name' => ['record_type'],
      'status_name' => ['status'],
      'source_name' =>['source']
    ];
    
    $custom = isset($config['custom']) && is_array($config['custom']) ? $config['custom'] : [];
    
    $webhook->map($inputs, $defaults, $mapping, $custom);
    
    // Testing
    if ($webhook->test) {
      $protect = [
        'last_name',
        'home_number',
        'mobile_number',
        'work_number',
        'email',
        'address_line1'
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

      $success = $status && ($status == 200 || $status == 204);

      // Initial Testing
      //$webhook->info($result);
      
      // Decode JSON response
      if (0 && $success) {
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
      $webhook->success("Lead: \"{$inputs['first_name']}\" | Lead successfully submitted to JobNimbus");
    } else {
      $webhook->error("Lead: \"{$inputs['first_name']}\" | Unable to submit lead to JobNimbus", true, $inputs);
      $webhook->error("Result: " . print_r($result, 1));
      $webhook->error("Status: " . print_r($status, 1));
      $webhook->error("Error: " . print_r($error, 1));
      
      if ($die) {
        echo "Error: Unable to submit lead to JobNimbus\n\n";
        echo print_r($status, 1) . "\n\n";
        echo print_r($error, 1) . "\n\n";
      
        http_response_code(500);
        die();
      }
    }
    
    return $success;
  }
}
