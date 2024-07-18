<?php

class GoHighLevelCRM
{
  /**
   * Send a lead to GoHighLevel
   *
   * Config Example:
   * $config = [
   *   'api_key' => '{API_KEY}',
   *   'custom' => [
   *     // 'GOHIGHLEVEL_CUSTOM_FIELD_NAME' => 'WEBHOOK_FIELD_NAME'
   *     '5GAxe8jb51CPZXo4RFGF' => 'notes',
   *     'MQzdYzmxq6Jrcybsrd75' => 'product'
   *   ]
   * ];
   *
   * API Documentation - Create a Contact
   * @link https://developers.gohighlevel.com/#11a4ffbd-6429-4121-bfc9-849a690b9dd4
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
    
    $url = 'https://rest.gohighlevel.com/v1/contacts/';
    
    $source = $webhook->source();
    $source = $source ? "Public API ({$source})" : 'Public API';
    
    // Set up default values
    $defaults = [
      'name' => $webhook->firstname() . ' ' . $webhook->lastname(),
      'firstName' => $webhook->firstname(),
      'lastName' => $webhook->lastname(),
      'email' => $webhook->email(),
      'phone' => $webhook->phone(),
      'address1' => $webhook->address(),
      'city' => $webhook->city(),
      'state' => $webhook->state(),
      'postalCode' => $webhook->zip(),
      'notes' => $webhook->forminfo(),
      'product' => $webhook->product(),
      'companyName' => '',
      'website' => '',
      'timezone' => '',
      'dnd' => '',
      'tags' => '',
      'customField' => (object) [],
      'source' => $source
    ];

    $mapping = [
      'name' => [],
      'firstName' => ['first_name', 'firstname'],
      'lastName' => ['last_name', 'lastname'],
      'email' => ['email_address'],
      'phone' => ['phone1', 'phone_number'],
      'address1' => ['address', 'streetaddress', 'street'],
      'city' => [],
      'state' => [],
      'postalCode' => ['zip', 'zip_code', 'postal_code'],
      'notes' => ['additional_notes', 'description', 'comments'],
      'companyName' => ['company', 'company_name'],
      'website' => [],
      'timezone' => [],
      'dnd' => [],
      'tags' => [],
      'customField' => [],
      'source' => []
    ];
    
    $custom = isset($config['custom']) && is_array($config['custom']) ? $config['custom'] : [];
    
    $webhook->map($inputs, $defaults, $mapping, $custom);
    
    // Custom Fields - Ex: notes & product
    foreach ($custom as $field => $prop) {
      if (!empty($inputs[$prop])) {
        $inputs['customField']->{$field} = $inputs[$prop];
      }
      
      // Custom Field - Clean up
      unset($inputs[$prop]);
    }
    
    // Custom Field - Clean up
    unset($inputs['notes']);
    unset($inputs['product']);
    
    // Testing
    if ($webhook->test) {
      $protect = [
        'name',
        'lastName',
        'phone',
        'email',
        'address1'
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
          "Content-Type: application/json",
          "Authorization: Bearer {$config['api_key']}"
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
          if (!isset($json->contact) || empty($json->contact->id)) {
            $success = false;
          }
        } else {
          $success = false;
        }
      }
    }
    
    // Log status
    if ($success) {
      $webhook->info($result);
      $webhook->success("Lead: \"{$inputs['firstName']}\" | Lead successfully submitted to GoHighLevel");
    } else {
      $webhook->error("Lead: \"{$inputs['firstName']}\" | Unable to submit lead to GoHighLevel", true, $inputs);
      $webhook->error("Result: " . print_r($result, 1));
      $webhook->error("Status: " . print_r($status, 1));
      $webhook->error("Error: " . print_r($error, 1));
      
      if ($die) {
        echo "Error: Unable to submit lead to GoHighLevel\n\n";
        echo print_r($status, 1) . "\n\n";
        echo print_r($error, 1) . "\n\n";
      
        http_response_code(500);
        die();
      }
    }
    
    return $success;
  }
}
