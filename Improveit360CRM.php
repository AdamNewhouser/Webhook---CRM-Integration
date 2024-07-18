<?php

class Improveit360CRM
{
  /**
   * Sends a lead to Improveit 360
   * @link https://support.improveit360.com/hc/en-us/articles/360051591334-eLead-Standard-Field-Mapping
   *
   * Config Example:
   * $config = [
   *   'url' => '{URL}',
   *   'custom' => [
   *     'i360__Components__c' => 'product',
   *     'DNC_Lifetime_Waiver_Phone_1__c' => 'optin'
   *   ]
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
      'email1' => $webhook->email(),
      'phone1' => $webhook->phone(),
      'phone1type' => 'Home',
      'phone2' => '',
      'phone2type' => '',
      'phone3' => '',
      'phone3type' => '',
      'streetaddress' => $webhook->address(),
      'city' => $webhook->city(),
      'state' => $webhook->state(),
      'zip' => $webhook->zip(),
      'comments' => $webhook->forminfo(),
      'apptday' => '',
      'appttime' => '',
      'sourcetype' => 'Website',
      'source' => '',
      'retURL' => ''
    ];
    
    $mapping = [
      'firstname' => ['first_name'],
      'lastname' => ['last_name'],
      'email1' => ['email'],
      'phone1' => ['phone'],
      'phone1type' => ['phonetype', 'phonetype1'],
      'phone2' => [],
      'phone2type' => ['phonetype2'],
      'phone3' => [],
      'phone3type' => ['phonetype3'],
      'streetaddress' => ['address'],
      'city' => ['city'],
      'state' => [],
      'zip' => ['zip_code', 'postal_code'],
      'comments' => ['notes'],
      'apptday' => [],
      'appttime' => [],
      'sourcetype' => [],
      'source' => [],
      'retURL' => ['returnurl']
    ];
    
    $custom = isset($config['custom']) && is_array($config['custom']) ? $config['custom'] : [];
    
    $webhook->map($inputs, $defaults, $mapping, $custom);
    
    // Clean up & prep inputs
    $phones = ['phone1', 'phone2', 'phone3'];
    
    foreach ($phones as $name) {
      $phone = $inputs[$name];
      
      if ($phone) {
        // Remove all non integer characters
        $phone = preg_replace('/[^0-9]/', '', $phone);
        
        // Crop to 10 digits
        $phone = strval(substr($phone, -10));
        
        // Build array of phone number parts
        $parts = [
          'area_code' => strval(substr($phone, 0, 3)),
          'prefix' => strval(substr($phone, -7, 3)),
          'line_number' => strval(substr($phone, -4, 4)),
        ];
        
        $phone = '(' . $parts['area_code'] . ') ' . $parts['prefix'] . '-' . $parts['line_number'];
        
        $inputs[$name] = $phone;
      }
    }
    
    $inputs['zip'] = strval(substr(preg_replace('/[^0-9]/', '', $inputs['zip']), 0, 5));
    
    // Testing
    if ($webhook->test) {
      $protect = [
        'firstname',
        'lastname',
        'phone1',
        'phone2',
        'phone3',
        'email1',
        'streetaddress'
      ];
      
      $json = $webhook->protectArray($inputs, 4, $protect);
      $json = json_encode($json, JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT);
      $webhook->info($json);
    }
    
    // Validate url
    if (empty($config['url'])) {
      $error = 'i360 URL is missing';
    }
    
    // Send lead
    if (!$error && $webhook->send) {
      $query = http_build_query($inputs);
      
      $ch = curl_init();
      curl_setopt($ch, CURLOPT_URL, $config['url']);
      
      // @link https://stackoverflow.com/questions/27776129/php-curl-curlopt-connecttimeout-vs-curlopt-timeout
      curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30); // Max time to connect to server
      curl_setopt($ch, CURLOPT_TIMEOUT, 60); // Max total execution time
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); // Return result on request
      curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.1)");
      curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true); // Never set to false on production servers
      curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1); // Follow any redirects
      curl_setopt($ch, CURLOPT_POST, count($inputs));
      curl_setopt($ch, CURLOPT_POSTFIELDS, $query);
      
      $result = curl_exec($ch);
      $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
      $error = curl_error($ch);

      curl_close($ch);

      $success = $status && $status == 200;
    }
    
    // Log status
    if ($success) {
      $webhook->success("Lead: \"{$inputs['firstname']}\" | Lead successfully submitted to i360");
    } else {
      $webhook->error("Lead: \"{$inputs['firstname']}\" | Unable to submit lead to i360", true, $inputs);
      $webhook->error("Result: " . print_r($result, 1));
      $webhook->error("Status: " . print_r($status, 1));
      $webhook->error("Error: " . print_r($error, 1));
      
      if ($die) {
        echo "Error: Unable to submit lead to i360\n\n";
        echo print_r($status, 1) . "\n\n";
        echo print_r($error, 1) . "\n\n";
      
        http_response_code(500);
        die();
      }
    }
    
    return $success;
  }
}
