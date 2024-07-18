<?php

class GiddyUpCRM
{
  /**
   * Send a lead to GiddyUp
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
    
    $url = 'https://mygiddyup.com/api/public/integration/zapier/create/' . $config['api_key'];
    
    // Testing
    if ($webhook->test) {
      $protect = [
        'LastName',
        'HomePhone',
        'CellPhone',
        'WorkPhone',
        'Email',
        'JobSiteAddressLine1'
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
          "Content-Type: text/plain"
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
      $webhook->success("Lead: \"{$inputs['FirstName']}\" | Lead successfully submitted to GiddyUp");
    } else {
      $webhook->error("Lead: \"{$inputs['FirstName']}\" | Unable to submit lead to GiddyUp", true, $inputs);
      $webhook->error("Result: " . print_r($result, 1));
      $webhook->error("Status: " . print_r($status, 1));
      $webhook->error("Error: " . print_r($error, 1));
      
      if ($die) {
        echo "Error: Unable to submit lead to GiddyUp\n\n";
        echo print_r($status, 1) . "\n\n";
        echo print_r($error, 1) . "\n\n";
      
        http_response_code(500);
        die();
      }
    }
    
    return $success;
  }
}
