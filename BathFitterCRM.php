<?php

class BathFitterCRM
{
  /**
   * Send a lead to Bath Fitter CRM
   *
   * Config Example:
   * $config = [
   *   'api_key' => '{API_KEY}'
   * ];
   *
   * API Documentation
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
    $header = null;
    $body = null;
    $headers = [];
    
    $url = 'https://api.mybathfitter.com/BFService.svc/lead?format=json';
    
    // Set up default values
    $defaults = [
      'FirstName' => $webhook->firstname(),
      'LastName' => $webhook->lastname(),
      'Email' => $webhook->email(),
      'Phone1' => $webhook->phone(),
      'Phone2' => null,
      'Address1' => $webhook->address(),
      'City' => $webhook->city(),
      'State' => $webhook->state(),
      'ZipCode' => $webhook->zip(),
      'Country' => $webhook->country('US'),
      'Message' => $webhook->comments(),
      'InterestedIn' => null,
      'YearHomeBuilt' => null,
      'PreferredLanguage' => null,
      'UtmSource' => null,
      'aff_id' => null,
      'offer_id' => null
    ];
    
    $mapping = [
      'FirstName' => ['first_name', 'firstname'],
      'LastName' => ['last_name', 'lastname'],
      'Email' => ['email_address'],
      'Phone1' => ['phone', 'phone1', 'phone_number'],
      'Address1' => ['address', 'streetaddress'],
      'City' => ['city'],
      'State' => [],
      'ZipCode' => ['zip', 'zip_code', 'postal_code'],
      'Country' => [],
      'Message' => ['notes', 'comments'],
      'UtmSource' => ['utmsource', 'utm_source'],
      'aff_id' => ['affiliate'],
      'offer_id' => ['offer']
    ];
    
    $custom = isset($config['custom']) && is_array($config['custom']) ? $config['custom'] : [];
    
    $webhook->map($inputs, $defaults, $mapping, $custom);
    
    // Testing
    // Bath Fitter requested that any production tests have a certain zip
    if (!empty($inputs['Email']) && preg_match('/@test(ing)?\.com/i', $inputs['Email'])) {
      $inputs['ZipCode'] = 'z9z9z9';
    }
    
    // Testing
    if ($webhook->test) {
      $protect = [
        'LastName',
        'Email',
        'Phone1',
        'Address1'
      ];
      
      $json = $webhook->protectArray($inputs, 4, $protect);
      $json = json_encode($json, JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT);
      $webhook->info("\n" . $json);
    }
    
    // Validate API Key
    if (empty($config['api_key'])) {
      $error = 'API key is missing';
    }
    
    // Send lead
    if (!$error && $webhook->send) {
      
      // Clear nulls
      foreach ($inputs as $key => $val) {
        if (is_null($val)) {
          unset($inputs[$key]);
        }
      }
      
      // Build json
      $json = (object) ['Lead' => $inputs];
      
      $json = json_encode($json);
      
      $ch = curl_init();
      
      curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_POSTFIELDS => $json,
        CURLOPT_HEADER => true,
        CURLOPT_HTTPHEADER => [
          "Accept: */*",
          "Cache-Control: no-cache",
          "Connection: keep-alive",
          "Content-Length: " . strlen($json),
          "Content-Type: application/json",
          "Authorization: {$config['api_key']}"
        ],
      ]);

      $result = curl_exec($ch);
      
      $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
      $header = trim(substr($result, 0, $header_size));
      $body = trim(substr($result, $header_size));
      $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
      $error = curl_error($ch);

      curl_close($ch);

      // Parse headers
      $lines = preg_split('/\n/', $header);
      
      foreach ($lines as $line) {
        $line = trim($line);
        $parts = preg_split('/:\s+/i', $line);
        
        if (count($parts) >= 2) {
          $key = array_shift($parts);
          $val = implode(' ', $parts);
          $headers[$key] = $val;
        }
      }
      
      $success = $status && $status == 201 && isset($headers['ResultID']);
    }
    
    // Log status
    if ($success) {
      $id = $headers['ResultID'];
      $webhook->success("Lead: \"{$inputs['FirstName']}\" | Lead successfully submitted to BathFitter (#{$id})");
    } else {
      $webhook->error("Lead: \"{$inputs['FirstName']}\" | Unable to submit lead to BathFitter", true, $inputs);
      $webhook->error("Status: " . print_r($status, 1));
      $webhook->error("Error: " . print_r($error, 1));
      
      // Testing
      if (!empty($inputs['Email']) && preg_match('/@test(ing)?\.com/i', $inputs['Email'])) {
        $json = (object) ['Lead' => $inputs];
        $json = json_encode($json, JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT);
        
        $webhook->error("JSON:\n" . print_r($json, 1));
      }
      
      $webhook->error("Header:\n" . print_r($header, 1));
      
      $body = preg_replace('/\<\?/', '<', $body);
      $body = preg_replace('/\?\>/', '>', $body);
      $body = "<xmp>{$body}</xmp>";
      
      $webhook->error("Body:\n" . print_r($body, 1));
      
      if ($die) {
        echo "Error: Unable to submit lead to BathFitter\n\n";
        echo print_r($status, 1) . "\n\n";
        echo print_r($error, 1) . "\n\n";
      
        http_response_code(500);
        die();
      }
    }
    
    return $success;
  }
}
