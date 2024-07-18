<?php

class SalesforceCRM
{
  /**
   * Sends a lead to Salesforce
   *
   * Add fields & generate form to determine additional custom field names to use
   * @link https://help.salesforce.com/s/articleView?id=000326485&type=1
   *
   * Use config to map custom LP fields to their equivalent input value field name.
   *
   * Config Example:
   * $config = [
   *   'url' => '{URL}',
   *   'custom' => [
   *     // 'SALESFORCE_FIELD_NAME' => 'INPUT_FIELD_NAME'
   *     '00N0a00000DIwC1' => 'comments',
   *     '00Nj0000000I5a1' => 'lead_division',
   *     '00Nj0000003hvxM' => 'product',
   *     '00Nj0000007XumF' => 'Web2Lead'
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
      'first_name' => $webhook->firstname(),
      'last_name' => $webhook->lastname(),
      'email' => $webhook->email(),
      'phone' => $webhook->phone(),
      'street' => $webhook->address(),
      'city' => $webhook->city(),
      'state_code' => $webhook->state(),
      'zip' => $webhook->zip(),
      'country_code' => 'US',
      'lead_source' => '',
      'member_status' => 'Responded',
      'Campaign_ID' => '',
      'oid' => ''
    ];
    
    $mapping = [
      'first_name' => ['firstname'],
      'last_name' => ['lastname'],
      'email' => [],
      'phone' => [],
      'street' => ['address'],
      'city' => [],
      'state_code' => ['state'],
      'zip' => ['zip_code', 'postal_code'],
      'country_code' => ['country'],
      'lead_source' => ['source'],
      'member_status' => [],
      'Campaign_ID' => ['campaign'],
      'oid' => []
    ];
    
    $custom = isset($config['custom']) && is_array($config['custom']) ? $config['custom'] : [];
    
    $webhook->map($inputs, $defaults, $mapping, $custom);
    
    // Ignore various fields for different Salesforce client accounts
    $ignore = isset($config['ignore']) && is_array($config['ignore']) ? $config['ignore'] : [];
    
    foreach ($ignore as $field) {
      unset($inputs[$field]);
    }
    
    // Clean up & prep inputs
    $inputs['phone'] = strval(substr(preg_replace('/[^0-9]/', '', $inputs['phone']), 0, 10));
    $inputs['zip'] = strval(substr(preg_replace('/[^0-9]/', '', $inputs['zip']), 0, 10));
    
    // Testing
    if ($webhook->test) {
      $protect = [
        'last_name',
        'phone',
        'email',
        'street',
        'oid'
      ];
      
      $json = $webhook->protectArray($inputs, 4, $protect);
      $json = json_encode($json, JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT);
      $webhook->info($json);
    }

    // Validate OID
    if (empty($inputs['oid'])) {
      $error = 'OID is missing';
    }
    
    // Send lead
    if (!$error && $webhook->send) {
      $url = empty($config['url']) ? 'https://webto.salesforce.com/servlet/servlet.WebToLead' : $config['url'];
      
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
      
      $result = curl_exec($ch);
      $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
      $error = curl_error($ch);

      curl_close($ch);

      $success = $status && $status == 200;
    }
    
    // Log status
    if ($success) {
      $webhook->success("Lead: \"{$inputs['first_name']}\" | Lead successfully submitted to Salesforce");
    } else {
      $webhook->error("Lead: \"{$inputs['first_name']}\" | Unable to submit lead to Salesforce", true, $inputs);
      $webhook->error("Result: " . print_r($result, 1));
      $webhook->error("Status: " . print_r($status, 1));
      $webhook->error("Error: " . print_r($error, 1));
      
      if ($die) {
        echo "Error: Unable to submit lead to Salesforce\n\n";
        echo print_r($status, 1) . "\n\n";
        echo print_r($error, 1) . "\n\n";
      
        http_response_code(500);
        die();
      }
    }
    
    return $success;
  }
}
