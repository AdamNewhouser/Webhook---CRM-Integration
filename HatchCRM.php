<?php

class HatchCRM
{
  /**
   * Sends a lead to Hatch
   * @link https://help.usehatchapp.com/hc/en-us/articles/360045114051-Standard-Web-Form-Integration
   *
   * Config Example:
   * $config = [
   *   'api_key' => 'CLIENT_API_KEY',
   *   'dept_id' => 'DEPT_ID',
   *   'api_url' => 'API_URL', (optional)
   *   'api_version'=>'API_VERSION'(optional)
   * ];
   *
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
    
    // Set up default values
    $defaults = [
      // Hatch fields
      'firstName' => $webhook->firstname(), // required
      'lastName' => $webhook->lastname(), // required
      'phoneNumber' => $webhook->phone(), // required
      'email' => $webhook->email(), // required
      'source' => '', // required
      'status' => '', // optional
      'id' => '', // optional
      'contactID' => '', // optional
      'createdAt' => '', // optional
      'updatedAt' => '', // optional
      
      // Non-Hatch fields
      'address' => $webhook->address(),
      'city' => $webhook->city(),
      'state' => $webhook->state(),
      'zip' => $webhook->zip(),
      'comments' => $webhook->forminfo()
    ];
    
    $mapping = [
      // Hatch fields
      'firstName' => ['firstname', 'first_name'],
      'lastName' => ['lastname', 'last_name'],
      'phoneNumber' => ['phone', 'phone1'],
      'email' => ['email1'],
      'source' => [],
      'status' => [],
      'id' => [],
      'contactID' => [],
      'createdAt' => [],
      'updatedAt' => [],
      
      // Non-Hatch fields
      'address' => ['streetaddress'],
      'city' => ['city'],
      'state' => [],
      'zip' => ['zip_code', 'postal_code'],
      'comments' => ['notes']
    ];
    
    $custom = isset($config['custom']) && is_array($config['custom']) ? $config['custom'] : [];
    
    $webhook->map($inputs, $defaults, $mapping, $custom);
    
    // Clean up & prep inputs
    $inputs['phoneNumber'] = preg_replace('/[^0-9]/', '', $inputs['phoneNumber']);
    $inputs['zip'] = strval(substr(preg_replace('/[^0-9]/', '', $inputs['zip']), 0, 5));
    
    // Remove empty input values inorder to send only what is set
    $array = $inputs;
    
    foreach ($inputs as $key => $value) {
      if (is_null($value) || (is_string($value) && !strlen($value))) {
        unset($array[$key]);
      }
    }
    
    $inputs = $array;
    
    // Testing
    if ($webhook->test) {
      $array = $inputs;
      
      $protect = [
        'lastName',
        'phoneNumber',
        'email',
        'address'
      ];
      
      if (isset($config['dept_id'])) {
        $array['dept_id'] = $config['dept_id'];
      }
      
      $json = $webhook->protectArray($array, 4, $protect);
      $json = json_encode($json, JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT);
      $webhook->info($json);
    }
    
    // Validate api_key
    if (empty($config['api_key'])) {
      $error = 'API key is missing';
    }
    
    // Validate dept_id
    if (empty($config['dept_id'])) {
      $error = 'Dept ID is missing';
    }
    
    // Send lead
    if (!$error && $webhook->send) {
      $version = '1';
      $url = 'https://prod.usehatchapp.com/api/webhooks/{dept_id}/newlead';
      
      if (!preg_match('/^(\d{8})$/', $config['dept_id'])) {
        $version = '2';
        $url = 'https://app.usehatchapp.com/api/webhooks/{dept_id}/newlead';
      }
      
      $version = isset($config['api_version']) ? $config['api_version'] : $version;
      $url = isset($config['api_url']) ? $config['api_url'] : $url;
      
      $url = preg_replace('/\{dept_id\}/i', $config['dept_id'], $url);
      
      // Build json
      $json = json_encode($inputs);
      
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
          //"Accept-Encoding: gzip, deflate",
          "Cache-Control: no-cache",
          "Connection: keep-alive",
          "Content-Length: " . strlen($json),
          "Content-Type: application/json",
          "X-API-KEY: {$config['api_key']}"
        ],
      ]);

      $result = curl_exec($ch);
      $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
      $error = curl_error($ch);

      curl_close($ch);

      $success = $status && ($status == 200 || $status == 204);

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
    
    $firstName = @$inputs['firstName'];
    
    // Log status
    if ($success) {
      $webhook->success("Lead: \"{$firstName}\" | Lead successfully submitted to Hatch");
    } else {
      $webhook->error("Lead: \"{$firstName}\" | Unable to submit lead to Hatch", true, $inputs);
      $webhook->error("Result: " . print_r($result, 1));
      $webhook->error("Status: " . print_r($status, 1));
      $webhook->error("Error: " . print_r($error, 1));
      
      if ($die) {
        echo "Error: Unable to submit lead to Hatch\n\n";
        echo print_r($status, 1) . "\n\n";
        echo print_r($error, 1) . "\n\n";
      
        http_response_code(500);
        die();
      }
    }
    
    return $success;
  }
}
