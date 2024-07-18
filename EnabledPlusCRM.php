<?php

class EnabledPlusCRM
{
  /**
   * Sends a lead to Enabled Plus
   *
   * Config Example:
   * $config = [
   *   'notes' => "\n", // Optional notes delimiter
   *   'url' => 'https://test.renewalbyandersen.com/api/sitecore/featureforms/submitform', // Optional testing url
   *   'api_key' => 'CLIENT_API_KEY',
   *   'custom' => []
   * ];
   *
   * [Required Fields]
   * FirstName
   * LastName
   * EmailAddress
   * PhoneNumber
   * Zipcode
   * FormType
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
      'FirstName' => $webhook->firstname(),
      'LastName' => $webhook->lastname(),
      'EmailAddress' => $webhook->email(),
      'PhoneNumber' => $webhook->phone(),
      'Address1' => $webhook->address(),
      'Address2' => '',
      'City' => $webhook->city(),
      'State' => $webhook->state(),
      'Zipcode' => $webhook->zip(),
      'RbASource' => $webhook->source(),
      'RbABreakdown' => '',
      'ConsultationType' => '',
      'Project' => $webhook->product(),
      'BuildingType' => '',
      'BestTimeToCall' => '',
      'Comment' => $webhook->forminfo(),
      'JobNumber' => '',
      'CallingRights' => '', // (value must be null, 'Y' or 'N')
      'WindowsAge' => '',
      'Windows' => '',
      'WindowsRotting' => '',
      'WindowsStyle' => '',
      'WindowMaterial' => '',
      'WindowsProblems' => '',
      'Doors' => '',
      'DoorsStyle' => '',
      'FramesCondition' => '',
      'ApptDate' => '', // (MM/dd/yyyy)
      'ApptTime' => '', // (hh:mm tt)
      'Sender' => '',
      'callbackdatetime' => '', // (mm/dd/yyyy hh:mmtt)
      'rba_1' => '',
      'rba_2' => '',
      'rba_3' => '',
      'rba_4' => '',
      'rba_5' => '',
      'Form Type' => '3'
    ];
    
    $mapping = [
      'FirstName' => ['first_name'],
      'LastName' => ['last_name'],
      'EmailAddress' => ['email', 'email_address'],
      'PhoneNumber' => ['phone', 'phone_number'],
      'Address1' => ['address'],
      'Address2' => [],
      'City' => [],
      'State' => [],
      'Zipcode' => ['zip', 'zip_code', 'postal_code'],
      'RbASource' => ['source'],
      'RbABreakdown' => ['breakdown'],
      'ConsultationType' => ['consultation_type'],
      'Project' => [],
      'BuildingType' => [],
      'BestTimeToCall' => [],
      'Comment' => ['comments', 'notes'],
      'JobNumber' => ['job_number'],
      'CallingRights' => [], // (value must be null, 'Y' or 'N')
      'WindowsAge' => [],
      'Windows' => [],
      'WindowsRotting' => [],
      'WindowsStyle' => [],
      'WindowMaterial' => [],
      'WindowsProblems' => ['notes2'],
      'Doors' => [],
      'DoorsStyle' => [],
      'FramesCondition' => [],
      'ApptDate' => [], // (MM/dd/yyyy)
      'ApptTime' => [], // (hh:mm tt)
      'Sender' => [],
      'callbackdatetime' => [], // (mm/dd/yyyy hh:mmtt)
      'rba_1' => [],
      'rba_2' => [],
      'rba_3' => [],
      'rba_4' => [],
      'rba_5' => [],
      'Form Type' => ['FormType']
    ];
    
    $custom = isset($config['custom']) && is_array($config['custom']) ? $config['custom'] : [];
    $delimiter = isset($config['notes']) ? $config['notes'] : ', ';
    
    $webhook->map($inputs, $defaults, $mapping, $custom, $delimiter);

    // Testing
    if ($webhook->test) {
      $protect = [
        'FirstName',
        'LastName',
        'PhoneNumber',
        'EmailAddress',
        'Address1'
      ];
      
      $params = [];
      
      // Only account for values that have been set
      foreach ($inputs as $key => $value) {
        if (is_string($value) && strlen($value)) {
          $params[$key] = $value;
        }
      }
      
      $json = $webhook->protectArray($params, 4, $protect);
      $json = json_encode($json, JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT);
      $webhook->info($json);
    }
    
    // Validate API Key
    if (empty($config['api_key'])) {
      $error = 'API key is missing';
    }
    
    // Send lead
    if (!$error && $webhook->send) {
      $url = !empty($config['url']) ? $config['url'] : 'https://www.renewalbyandersen.com/api/sitecore/featureforms/submitform';
      
      // Build query
      $query = '';
      
      foreach ($inputs as $key => $value) {
        if (is_string($value) && strlen($value)) {
          $query .= $key . '=' . urlencode($value) . '&';
        }
      }
      
      $query = rtrim($query, '&');
      
      $ch = curl_init();
      
      curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
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
          "Authorization: {$config['api_key']}"
        ],
      ]);

      $result = curl_exec($ch);
      $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
      $error = curl_error($ch);

      curl_close($ch);

      $success = $status && ($status == 200 || $status == 204);
      
      // Example response
      // {"status":"500","data":"Error","message":"E+ response returned null"}
      
      // Decode JSON response
      if ($success) {
        $json = JsonUtils::decode($result);
        
        if (JsonUtils::isObject($json)) {
          if (isset($json->data) && preg_match('/error/i', $json->data)) {
            $success = false;
            
            if (isset($json->message)) {
              $error .= ' ' . $json->message;
              $error = trim($error);
            }
          }
        } else {
          $success = false;
        }
      }
    }
    
    // Log status
    if ($success) {
      $webhook->info("Result: " . print_r($result, 1));
      $webhook->success("Lead: \"{$inputs['FirstName']}\" | Lead successfully submitted to Enabled Plus");
    } else {
      $webhook->error("Lead: \"{$inputs['FirstName']}\" | Unable to submit lead to Enabled Plus", true, $inputs);
      $webhook->error("Result: " . print_r($result, 1));
      $webhook->error("Status: " . print_r($status, 1));
      $webhook->error("Error: " . print_r($error, 1));
      
      if ($die) {
        echo "Error: Unable to submit lead to Enabled Plus\n\n";
        echo print_r($status, 1) . "\n\n";
        echo print_r($error, 1) . "\n\n";
      
        http_response_code(500);
        die();
      }
    }
    
    return $success;
  }
}
