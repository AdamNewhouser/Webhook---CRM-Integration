<?php

class BuilderPrimeCRM
{
  /**
   * Sends a lead to Builder Prime
   *
   * Config Example:
   * $config = [
   *   'api_url' => '{API_URL}',
   *   'api_key' => '{API_KEY}',
   *   'custom' => [
   *     'referral_url' => 'Referral URL'
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
      'firstname' => $webhook->firstname(null, 40),
      'lastname' => $webhook->lastname(null, 80),
      'email' => $webhook->email(),
      'phone' => $webhook->phone(null, 10),
      'address' => $webhook->address(),
      'city' => $webhook->city(),
      'state' => $webhook->state(),
      'zip' => $webhook->zip(),
      'comments' => $webhook->comments(),
      'source' => $webhook->source('Website'),
      'referrer' => $webhook->referrer()
    ];
    
    $mapping = [
      'firstname' => ['first_name'],
      'lastname' => ['last_name'],
      'email' => [],
      'phone' => [],
      'address' => ['streetaddress'],
      'city' => ['city'],
      'state' => [],
      'zip' => ['zip_code', 'postal_code'],
      'comments' => ['notes'],
      'source' => [],
      'referrer' => []
    ];
    
    $custom = isset($config['custom']) && is_array($config['custom']) ? $config['custom'] : [];
    
    $webhook->map($inputs, $defaults, $mapping, $custom);
    
    // Testing
    if ($webhook->test) {
      $protect = [
        'firstname',
        'lastname',
        'phone',
        'email'
      ];
      
      $json = $webhook->protectArray($inputs, 4, $protect);
      $json = json_encode($json, JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT);
      $webhook->info($json);
    }
    
    // Validate api_key
    if (empty($config['api_key'])) {
      $error = 'API key is missing';
    }
    
    // Validate api_url
    if (empty($config['api_url'])) {
      $error = 'API URL is missing';
    }
    
    // Send lead
    if (!$error && $webhook->send) {
      $notes = '';

      if(!empty($inputs['comments'])) {
        $notes .= 'Question/Comment: ' . $inputs['comments'];
      }
      
      if(!empty($inputs['source'])) {
        $notes .= ' Source: ' . $inputs['source'];
      }
      
      if(!empty($inputs['referrer'])) {
        $notes .= ' Referrer: ' . $inputs['referrer'];
      }
      
      $notes .= " Form: {$webhook->formname()} (#{$webhook->formid()})";

      $notes = trim($notes);

      $post_json = '{
              "userAccount": {
                  "firstName": "' . $inputs['firstname'] . '",
                  "lastName": "' . $inputs['lastname'] . '",
                  "emailAddress": "' . $inputs['email'] . '"
              },
              "phoneNumber": "+1' . $inputs['phone'] . '",';

      if(!empty($inputs['zip'])) {
        $post_json .= '
              "zip": "' . $inputs['zip'] . '",';
      }

      if(!empty($notes)) {
        // Replace line breaks before sending to builder prime
        $notes = preg_replace('/\r\n|\r|\n/', ' ', $notes);
        
        $post_json .= '
              "notes": "' . $notes . '",';
      }
      
      // Custom fields
      if ($custom) {
        $post_json .= '
              "customFields": [';
          
        foreach ($custom as $key => $value) {
            $post_json .= '
                  {
                      "customFieldName": "' . $value . '",
                      "customFieldValue": "' . $inputs[$key] . '"
                  },';
        }
        
        $post_json = preg_replace('/,$/', '', $post_json);
        
        $post_json .= '
              ],';
      }

      $post_json .= '
              "leadStatus": {
                  "name": "Lead received"
              },
              "clientLeadSource": {
                  "description": "' . $inputs['source'] . '"
              }
          }';

      // Testing
      if ($webhook->test) {
        //$webhook->info($post_json);
      }
      
      $ch = curl_init();

      curl_setopt_array($ch, array(
        CURLOPT_URL => $config['api_url'],
        CURLOPT_SSL_VERIFYPEER => 0,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => "",
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => "POST",
        CURLOPT_POSTFIELDS => $post_json,
        CURLOPT_HTTPHEADER => array(
          "Content-Type: application/json",
          "Cache-Control: no-cache",
          "X-API-KEY: " . $config['api_key']
        ),
      ));

      $result = curl_exec($ch);
      $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
      $error = curl_error($ch);

      curl_close($ch);

      $success = $status && ($status == 200 || $status == 204);
    }
    
    // Log status
    if ($success) {
      $webhook->success("Lead: \"{$inputs['firstname']}\" | Lead successfully submitted to Builder Prime");
    } else {
      $webhook->error("Lead: \"{$inputs['firstname']}\" | Unable to submit lead to Builder Prime", true, $inputs);
      $webhook->error("Result: " . print_r($result, 1));
      $webhook->error("Status: " . print_r($status, 1));
      $webhook->error("Error: " . print_r($error, 1));
      
      if ($die) {
        echo "Error: Unable to submit lead to Hatch\n\n";
        echo print_r($status, 1) . "\n\n";
        echo print_r($error, 1) . "\n\n";
      
        http_response_code(500);
        die();
      }
    }
    
    return $success;
  }
}
