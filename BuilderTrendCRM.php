<?php

class BuilderTrendCRM
{
  /**
   * Sends a lead to BuilderTrend
   *
   * Config Example:
   * $config = [
   *   'client_id' => '{REQUIRED_CLIENT_ID}',
   *   'field_prefix' => '{OPTIONAL_FIELD_PREFIX}',
   *   'custom' => [
	 *     'CustomFieldValueControlID_738623$Textbox1' => 'product',
	 *     'CustomFieldValueControlID_738624$Textbox1' => 'source',
	 *     'CustomFieldValueControlID_738625$Textbox1' => 'referrer'
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
    $field_prefix = 'ctl00$ctl00$ctl00$MasterMain$MasterMain$MasterMain$';
    $field_prefix = !empty($config['field_prefix']) ? $config['field_prefix'] : $field_prefix;
    $url = "https://buildertrend.net/leads/contactforms/ContactFormFrame.aspx?builderID={$config['client_id']}";
    
    $firstname = $webhook->firstname();
    
    // Set up default values
    $defaults = [
      "__EVENTTARGET" => "2",
      "{$field_prefix}hidmbid" => "74028",
      "{$field_prefix}LeadContactName" => $webhook->firstname() . " ". $webhook->lastname(),
      "{$field_prefix}LeadEmail" => $webhook->email(),
      "{$field_prefix}LeadPhone" => $webhook->phone(),
      "{$field_prefix}LeadStreet" => $webhook->address(),
      "{$field_prefix}LeadCity" => $webhook->city(),
      "{$field_prefix}LeadState" => $webhook->state(),
      "{$field_prefix}LeadZip" => $webhook->zip(),
      "{$field_prefix}LeadGeneralNotes" => "Form: {$webhook->formname()} (#{$webhook->formid()})",
      "{$field_prefix}btnSubmit" => "Submit"
    ];
    
    $mapping = [
      "{$field_prefix}LeadContactName" => ['name'],
      "{$field_prefix}LeadEmail" => ['email'],
      "{$field_prefix}LeadPhone"=> ['phone'],
      "{$field_prefix}LeadStreet" => ['address', 'streetaddress'],
      "{$field_prefix}LeadCity" => ['city'],
      "{$field_prefix}LeadState" => ['state'],
      "{$field_prefix}LeadZip" => ['zip'],
      "{$field_prefix}LeadGeneralNotes" => ['notes']
    ];

    $notes = ["{$field_prefix}LeadGeneralNotes"];
    
    $config_custom = isset($config['custom']) && is_array($config['custom']) ? $config['custom'] : [];
    
    $custom = [];

    if($config_custom) {
      foreach($config_custom as $key => $value) {
        $custom["{$field_prefix}{$key}"] = $value;
      }
    }
    
    $webhook->map($inputs, $defaults, $mapping, $custom, ', ', $notes);
    
    // Clean up & prep inputs
    $inputs["{$field_prefix}LeadPhone"] = preg_replace('/[^0-9]/', '', $inputs["{$field_prefix}LeadPhone"]);
    $inputs["{$field_prefix}LeadZip"] = substr(preg_replace('/[^0-9]/', '', $inputs["{$field_prefix}LeadZip"]), 0, 5);
    
    // Testing
    if ($webhook->test) {
      $protect = [
        "{$field_prefix}LeadContactName",
        "{$field_prefix}LeadEmail",
        "{$field_prefix}LeadPhone",
        "{$field_prefix}LeadStreet"
      ];
      
      $json = $webhook->protectArray($inputs, 4, $protect);
      $json = json_encode($json, JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT);
      $webhook->info($json);
    }
    
    // Validate client_id
    if (empty($config['client_id'])) {
      $error = 'client_id is missing';
    }
    
    // Send lead
    if (!$error && $webhook->send) {
      $ch = curl_init();
      
      $query = http_build_query($inputs);
      
      curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_POSTFIELDS => $query ,
        CURLOPT_HTTPHEADER => [
          "Content-Length: " . strlen($query),
          "Content-Type: application/x-www-form-urlencoded",
          "Accept: */*",
          "Cookie: ASP.NET_SessionId=jbg3sy0phnjtyths21yheyjc",
          "Host: " . $_SERVER['HTTP_HOST'],
          "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/73.0.3683.103 Safari/537.36",
          "X-Https: 1"
        ],
      ]);

      $result = curl_exec($ch);
      $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
      $error = curl_error($ch);

      curl_close($ch);

      $success = $status && ($status == 200 || $status == 204);
    }
    
    // Log status
    if ($success) {
      $output = $result;
      $output = preg_replace('/^.*<body>/is', '', $output);
      $output = preg_replace('/<\/body>.*$/is', '', $output);
      $output = strip_tags($output);
      
      $webhook->info("Result: " . print_r($output, 1));
      $webhook->success("Lead: \"{$firstname}\" | Lead successfully submitted to BuilderTrend");
    } else {
      $webhook->error("Lead: \"{$firstname}\" | Unable to submit lead to BuilderTrend", true, $inputs);
      $webhook->error("Result: " . print_r($result, 1));
      $webhook->error("Status: " . print_r($status, 1));
      $webhook->error("Error: " . print_r($error, 1));
      
      if ($die) {
        echo "Error: Unable to submit lead to BuilderTrend\n\n";
        echo print_r($status, 1) . "\n\n";
        echo print_r($error, 1) . "\n\n";
      
        http_response_code(500);
        die();
      }
    }

    return $success;
  }
}