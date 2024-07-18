<?php

class MalimarCRM
{
  /**
   * Sends a lead to Malimar
   *
   * Config Example:
   * $config = [
   *   'api_key' => '{API_KEY}'
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
    $raw = null;
    
    $url = 'https://api.marlimar.com/OutboundMessage/Send/';
    
    $defaults = [
      'first_name' => $webhook->firstname(),
      'last_name' => $webhook->lastname(),
      'mobile_number' => $webhook->phone(),
      'repeat' => 'foo'
    ];

    $mapping = [
      'first_name' => ['firstname'],
      'last_name' => ['lastname'],
      'phone' => ['mobile_number']
    ];
    
    $webhook->map($inputs, $defaults, $mapping);
    
    // Clean up & prep inputs
    $array = [
      'first_name' => $inputs['first_name'],
      'last_name' => $inputs['last_name'],
      'mobile_number' => $inputs['mobile_number'],
      'repeat' => $inputs['repeat']
    ];
    
    $inputs = $array;
    
    // Testing
    if ($webhook->test) {
      $protect = [
        'mobile_number',
        'last_name'
      ];
      
      $json = $webhook->protectArray($inputs, 4, $protect);
      $json = json_encode($json, JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT);
      $webhook->info($json);
    }

    // Validate API Key
    if (empty($config['api_key'])) {
      $error = 'API key is missing';
    } else {
      $inputs['hash_key'] = $config['api_key'];
    }

    // Send lead
    if (!$error && $webhook->send) {
      $query = http_build_query($inputs);
      
      $ch = curl_init();
      curl_setopt($ch, CURLOPT_URL, $url);
      
      // @link https://stackoverflow.com/questions/27776129/php-curl-curlopt-connecttimeout-vs-curlopt-timeout
      curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30); // Max time to connect to server
      curl_setopt($ch, CURLOPT_TIMEOUT, 60); // Max total execution time
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); // Return result on request
      curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.1)");
      curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true); // Never set to false on production servers
      curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1); // Follow any redirects
      curl_setopt($ch, CURLOPT_POST, count($inputs));
      curl_setopt($ch, CURLOPT_POSTFIELDS, $query);
      curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Accept: application/json",
        "Cache-Control: no-cache",
        "Connection: keep-alive",
        "Content-Length: " . strlen($query),
        "Content-Type: application/x-www-form-urlencoded"
      ]);
      
      $result = curl_exec($ch);
      $raw = '[' . $result . ']';
      $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
      $error = curl_error($ch);

      curl_close($ch);

      $success = $status && $status == 200;
    }
    
    // Decode json response
    if ($success) {
      $result = preg_replace('/^"/', '', $result);
      $result = preg_replace('/"$/', '', $result);
      
      $result = JsonUtils::decode($result);

      if (JsonUtils::isObject($result) 
        && isset($result->message) 
        && preg_match('/success/i', $result->message)) {
        // Success
      } else {
        $success = false;
      }
    }
    
    if ($success) {
      $webhook->success("Lead: \"{$inputs['first_name']}\" | Lead successfully submitted to Malimar");
      $webhook->error("Raw: " . $raw);
    } else {
      $webhook->error("Lead: \"{$inputs['first_name']}\" | Unable to submit lead to Malimar", true, $inputs);
      $webhook->error("Raw: " . $raw);
      $webhook->error("Status: " . print_r($status, 1));
      
      if ($error) {
        $webhook->error("Error: " . print_r($error, 1));
      }
      
      if ($die) {
        echo "Error: Unable to submit lead to Malimar\n\n";
        echo print_r($status, 1) . "\n\n";
        echo print_r($error, 1) . "\n\n";
      
        http_response_code(500);
        die();
      }
    }
  }
}
