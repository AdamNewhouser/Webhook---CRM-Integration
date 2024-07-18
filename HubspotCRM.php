<?php

class HubspotCRM
{
  /**
   * Sends a lead to Hubspot
   *
   * Config Example:
   * $config = [
   *   'portal_id' => '3668872',
   *   'form_id' => '4ef91f1a-baa3-46a8-9287-d330db068a70'
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
      'firstname' => $webhook->firstname(),
      'lastname' => $webhook->lastname(),
      'email' => $webhook->email(),
      'phone' => $webhook->phone(),
      'address' => $webhook->address(),
      'zip_code' => $webhook->zip(),
      'message' => $webhook->forminfo(),
      'product' => '',
      'source' => '',
      'source_type' => 'Website'
    ];
    
    $mapping = [
      'firstname' => ['first_name'],
      'lastname' => ['last_name'],
      'email' => ['email1'],
      'phone' => ['phone1'],
      'address' => ['streetaddress'],
      'zip_code' => ['zip'],
      'message' => ['comments', 'notes'],
      'product' => [],
      'source' => '',
      'source_type' => ['sourcetype']
    ];
    
    $webhook->map($inputs, $defaults, $mapping);
    
    // Clean up & prep inputs
    $inputs['phone'] = preg_replace('/[^0-9]/', '', $inputs['phone']);
    $inputs['zip_code'] = strval(substr(preg_replace('/[^0-9]/', '', $inputs['zip_code']), 0, 5));
    
    // Testing
    if ($webhook->test) {
      $protect = [
        'firstname',
        'lastname',
        'phone',
        'email',
        'address'
      ];
      
      $json = $webhook->protectArray($inputs, 4, $protect);
      $json = json_encode($json, JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT);
      $webhook->info($json);
    }
    
    // Validate Portal ID
    if (empty($config['portal_id'])) {
      $error = 'portal_id is missing';
    }
    
    // Validate form_id
    if (empty($config['form_id'])) {
      $error = 'form_id is missing';
    }
    
    // Send lead
    if (!$error && $webhook->send) {
      // Build query
      $query = '';
      
      foreach ($inputs as $k => $v) {
        if ($v) {
          $query .= $k . '=' . urlencode($v) . '&';
        }
      }
      
      $query = rtrim($query, '&');
      
      $ch = curl_init();
      
      curl_setopt_array($ch, [
        CURLOPT_URL => "https://forms.hubspot.com/uploads/form/v2/{$config['portal_id']}/{$config['form_id']}",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_POSTFIELDS => $query,
        CURLOPT_HTTPHEADER => [
          "Accept: */*",
          //"Accept-Encoding: gzip, deflate",
          "Cache-Control: no-cache",
          "Connection: keep-alive",
          "Content-Length: " . strlen($query),
          "Content-Type: application/x-www-form-urlencoded",
          "Host: forms.hubspot.com"
        ],
      ]);

      $result = curl_exec($ch);
      $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
      $error = curl_error($ch);

      curl_close($ch);

      $success = $status && ($status == 200 || $status == 204);
    }
    
    // Log status
    if ($success) {
      $webhook->success("Lead: \"{$inputs['firstname']}\" | Lead successfully submitted to Hubspot");
    } else {
      $webhook->error("Lead: \"{$inputs['firstname']}\" | Unable to submit lead to Hubspot", true, $inputs);
      $webhook->error("Result: " . print_r($result, 1));
      $webhook->error("Status: " . print_r($status, 1));
      $webhook->error("Error: " . print_r($error, 1));
      
      if ($die) {
        echo "Error: Unable to submit lead to Hubspot\n\n";
        echo print_r($status, 1) . "\n\n";
        echo print_r($error, 1) . "\n\n";
      
        http_response_code(500);
        die();
      }
    }
    
    return $success;
  }
}
