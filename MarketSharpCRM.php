<?php

class MarketSharpCRM
{
  /**
   * Sends a lead to MarketSharp
   * @link https://help.marketsharp.com/knowledge-base/advanced-lead-capture/
   *
   * Config Example:
   * $config = [
   *   'notes' => "\n", // Optional notes delimiter
   *   'custom' => [
   *     'MSM_custom_TypeofProduct' => 'producttype'
   *   ]
   * ];
   *
   * @param array $inputs
   * @param array [$config]
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
      'MSM_firstname' => $webhook->firstname(),
      'MSM_lastname' => $webhook->lastname(),
      'MSM_email' => $webhook->email(),
      'MSM_homephone' => $webhook->phone(),
      'MSM_cellphone' => '',
      'MSM_workphone' => '',
      'MSM_address1' => $webhook->address(),
      'MSM_address2' => '',
      'MSM_city' => $webhook->city(),
      'MSM_state' => $webhook->state(),
      'MSM_zip' => $webhook->zip(),
      'MSM_custom_Interests' => $webhook->forminfo(),
      'MSM_custom_Best_Time_To_Reach' => '',
      'MSM_coy' => '',
      'MSM_source' => '',
      'MSM_formId' => '',
      'MSM_leadCaptureName' => ''
    ];
    
    $mapping = [
      'MSM_firstname' => ['firstname', 'first_name'],
      'MSM_lastname' => ['lastname', 'last_name'],
      'MSM_email' => ['email1', 'email'],
      'MSM_homephone' => ['phone1', 'phone'],
      'MSM_cellphone' => ['cell', 'cellphone', 'cell_phone'],
      'MSM_workphone' => ['workphone', 'work_phone'],
      'MSM_address1' => ['address1', 'address'],
      'MSM_address2' => ['address2'],
      'MSM_city' => ['city'],
      'MSM_state' => ['state'],
      'MSM_zip' => ['zip', 'zip_code', 'postal_code'],
      'MSM_custom_Interests' => ['notes'],
      'MSM_custom_Best_Time_To_Reach' => ['best_time'],
      'MSM_coy' => ['coy'],
      'MSM_source' => ['formsource'],
      'MSM_formId' => ['formid'],
      'MSM_leadCaptureName' => ['capturename', 'type']
    ];
    
    $custom = isset($config['custom']) && is_array($config['custom']) ? $config['custom'] : [];
    $delimiter = isset($config['notes']) ? $config['notes'] : ', ';
    
    $webhook->map($inputs, $defaults, $mapping, $custom, $delimiter);
    
    // Clean up & prep inputs
    $inputs['MSM_homephone'] = strval(substr(preg_replace('/\D/', '', $inputs['MSM_homephone']), 0, 10));
    $inputs['MSM_cellphone'] = strval(substr(preg_replace('/\D/', '', $inputs['MSM_cellphone']), 0, 10));
    $inputs['MSM_workphone'] = strval(substr(preg_replace('/\D/', '', $inputs['MSM_workphone']), 0, 10));
    $inputs['MSM_zip'] = strval(substr(preg_replace('/\D/', '', $inputs['MSM_zip']), 0, 5));
    
    // Set missing MSM_source or MSM_formId if one is set
    if (empty($inputs['MSM_source']) && !empty($inputs['MSM_formId'])) {
      $inputs['MSM_source'] = $inputs['MSM_formId'];
    }
    
    if (empty($inputs['MSM_formId']) && !empty($inputs['MSM_source'])) {
      $inputs['MSM_formId'] = $inputs['MSM_source'];
    }
    
    // Validate MSM_coy
    if (empty($inputs['MSM_coy'])) {
      $error = 'MSM_coy is missing';
    }
    
    // Validate MSM_source
    else if (empty($inputs['MSM_source'])) {
      $error = 'MSM_source is missing';
    }
    
    // Validate MSM_formId
    else if (empty($inputs['MSM_formId'])) {
      $error = 'MSM_formId is missing';
    }
    
    // Validate that MSM_source and MSM_formId match
    else if ($inputs['MSM_source'] !== $inputs['MSM_formId']) {
      $error = 'MSM_source must match MSM_formId';
    }
    
    // Testing
    if ($webhook->test) {
      $protect = [
        'MSM_lastname',
        'MSM_homephone',
        'MSM_cellphone',
        'MSM_workphone',
        'MSM_email',
        'MSM_address1',
        'MSM_address2'
      ];
      
      $json = $webhook->protectArray($inputs, 4, $protect);
      $json = json_encode($json, JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT);
      $webhook->info($json);
    }

    // Send lead
    if (!$error && $webhook->send) {
      $query = '';
      
      foreach ($inputs as $key => $value) {
        $query .= $key . '=' . $value . '&|&';
      }
      
      $url = 'https://ha.marketsharpm.com/LeadCapture/MarketSharp/LeadCapture.ashx?callback=jsonp&info=' . urlencode($query) . '&version=2';
      
      $ch = curl_init();
      curl_setopt($ch, CURLOPT_URL, $url);
      
      // @link https://stackoverflow.com/questions/27776129/php-curl-curlopt-connecttimeout-vs-curlopt-timeout
      curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30); // Max time to connect to server
      curl_setopt($ch, CURLOPT_TIMEOUT, 60); // Max total execution time
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); // Return result on request
      curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.1)");
      curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true); // Never set to false on production servers
      curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1); // Follow any redirects
    
      $result = curl_exec($ch);
      $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
      $error = curl_error($ch);

      curl_close($ch);

      $success = $status && $status == 200;
    }
    
    // Log status
    if ($success) {
      $webhook->success("Lead: \"{$inputs['MSM_firstname']}\" | Lead successfully submitted to MarketSharp");
    } else {
      $webhook->error("Lead: \"{$inputs['MSM_firstname']}\" | Unable to submit lead to MarketSharp", true, $inputs);
      $webhook->error("Result: " . print_r($result, 1));
      $webhook->error("Status: " . print_r($status, 1));
      $webhook->error("Error: " . print_r($error, 1));
      
      if ($die) {
        echo "Error: Unable to submit lead to MarketSharp\n\n";
        echo print_r($status, 1) . "\n\n";
        echo print_r($error, 1) . "\n\n";
      
        http_response_code(500);
        die();
      }
    }
    
    return $success;
  }
}
