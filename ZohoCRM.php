<?php

class ZohoCRM
{
  /**
   * Sends a lead to Zoho
   * @link https://help.zoho.com/portal/en/kb/crm/marketing-automation-tools/web-forms/articles/set-up-web-forms#Generate_Web_Forms
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
      'Company' => $webhook->company(),
      'First Name' => $webhook->firstname(),
      'Last Name' => $webhook->lastname(),
      'Email' => $webhook->email(),
      'Phone' => $webhook->phone(),
      'Street' => $webhook->address(),
      'City' => $webhook->city(),
      'State' => $webhook->state(),
      'Zip Code' => $webhook->zip(),
      'Country' => $webhook->country('US'),
      'Description' => [],
      'actionType' => 'TGVhZHM=', // Required hidden field
      'xnQsjsdp' => '', // Required hidden field
      'xmIwtLD' => '' // Required hidden field
    ];
    
    $mapping = [
      'Company' => [],
      'First Name' => ['firstname', 'first_name'],
      'Last Name' => ['lastname', 'last_name'],
      'Email' => [],
      'Phone' => [],
      'Street' => ['address'],
      'City' => [],
      'State' => [],
      'Zip Code' => ['zip', 'zip_code', 'postal_code'],
      'Country' => [],
      'Description' => ['notes', 'note'],
      'actionType' => ['action'],
      'xnQsjsdp' => ['xn'],
      'xmIwtLD' => ['xm']
    ];
    
    $custom = isset($config['custom']) && is_array($config['custom']) ? $config['custom'] : [];
    
    $webhook->map($inputs, $defaults, $mapping, $custom);
    
    // Ignore various fields for different Salesforce client accounts
    $ignore = isset($config['ignore']) && is_array($config['ignore']) ? $config['ignore'] : [];
    
    foreach ($ignore as $field) {
      unset($inputs[$field]);
    }
    
    // Clean up & prep inputs
    $inputs['Phone'] = strval(substr(preg_replace('/[^0-9]/', '', $inputs['Phone']), 0, 10));
    $inputs['Zip Code'] = strval(substr(preg_replace('/[^0-9]/', '', $inputs['Zip Code']), 0, 10));
    
    // Testing
    if ($webhook->test) {
      $protect = [
        'Last Name',
        'Phone',
        'Email',
        'Street',
        'actionType',
        'xnQsjsdp',
        'xmIwtLD'
      ];
      
      $json = $webhook->protectArray($inputs, 4, $protect);
      $json = json_encode($json, JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT);
      $webhook->info($json);
    }
    
    // Validate actionType
    if (empty($inputs['actionType'])) {
      $error = 'actionType is missing';
    }
    
    // Validate xnQsjsdp
    else if (empty($inputs['xnQsjsdp'])) {
      $error = 'xnQsjsdp is missing';
    }
    
    // Validate xmIwtLD
    else if (empty($inputs['xmIwtLD'])) {
      $error = 'xmIwtLD is missing';
    }
    
    // Send lead
    if (!$error && $webhook->send) {
      $url = 'https://crm.zoho.com/crm/WebToLeadForm';
      
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
      $webhook->success("Lead: \"{$inputs['First Name']}\" | Lead successfully submitted to Zoho");
    } else {
      $webhook->error("Lead: \"{$inputs['First Name']}\" | Unable to submit lead to Zoho", true, $inputs);
      $webhook->error("Result: " . print_r($result, 1));
      $webhook->error("Status: " . print_r($status, 1));
      $webhook->error("Error: " . print_r($error, 1));
      
      if ($die) {
        echo "Error: Unable to submit lead to Zoho\n\n";
        echo print_r($status, 1) . "\n\n";
        echo print_r($error, 1) . "\n\n";
      
        http_response_code(500);
        die();
      }
    }
    
    return $success;
  }
}
