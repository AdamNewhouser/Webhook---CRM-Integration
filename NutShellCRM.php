<?php

class NutShellCRM
{
  /**
   * Sends a lead to NutShell
   *
   * $config = [
   *   'api_url' => '{API_URL}'
   * ];
   *
   * @link https://developers.nutshell.com/#http-post-api
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

    $webhook->info(print_r($inputs, true));
    
    // Set up default values
    $defaults = [
      'contact[name]' => $webhook->firstname() . ' ' . $webhook->lastname(),
      'contact[email]' => $webhook->email(),
      'contact[phone]' => $webhook->phone(),
      'contact[address][address_1]' => $webhook->address(),
      'contact[address][address_2]' => '',
      'contact[address][city]' => $webhook->city(),
      'contact[address][state]' => $webhook->state(),
      'contact[address][postal_code]' => $webhook->zip(),
    ];
    
    $mapping = [
      'contact[name]' => ['name', 'contact_name'],
      'contact[email]' => ['email', 'contact_email'],
      'contact[phone]' => ['phone', 'contact_phone'],
      'contact[address][address_1]' => ['address', 'address1', 'contact_address'],
      'contact[address][address_2]' => ['address2'],
      'contact[address][city]' => ['city', 'contact_city'],
      'contact[address][state]' => ['state', 'contact_state'],
      'contact[address][postal_code]' => ['zip', 'postal_code', 'contact_zip', 'contact_postal_code'],
      'note' => ['notes'],
      'product[name]' => ['product_name'],
      'product[quantity]' => ['product_quantity']
    ];
    
    $webhook->map($inputs, $defaults, $mapping);
    
    // Clean up & prep inputs
    $inputs['contact[phone]'] = strval(substr(preg_replace('/\D/', '', $inputs['contact[phone]']), 0, 10));
    $inputs['contact[address][postal_code]'] = strval(substr(preg_replace('/\D/', '', $inputs['contact[address][postal_code]']), 0, 5));
    
    // Testing
    if ($webhook->test) {
      $protect = [];
      
      $json = $webhook->protectArray($inputs, 4, $protect);
      $json = json_encode($json, JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT);
      $webhook->info(print_r($inputs, true));
    }

    // Validate API URL
    if (empty($config['api_url'])) {
      $error = 'API URL is missing';
    }
    
    // Send lead
    if (!$error && $webhook->send) {

      $ch = curl_init();
      
      curl_setopt_array($ch, array(
        CURLOPT_URL => $config['api_url'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => "",
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => "POST",
        CURLOPT_POSTFIELDS => $inputs,
        CURLOPT_HTTPHEADER => array(),
      ));
    
      $result = curl_exec($ch);
      $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
      $error = curl_error($ch);

      curl_close($ch);

      $success = $status && $status == 200;
    }
    
    if ($success) {
      $webhook->success("Lead: \"{$inputs['contact[name]']}\" | Lead successfully submitted to NutShell");
      $webhook->error("Result: " . print_r($result, 1));
      $webhook->error("Status: " . print_r($status, 1));
      $webhook->error("Error: " . print_r($error, 1));
    } else {
      $webhook->error("Lead: \"{$inputs['contact[name]']}\" | Unable to submit lead to NutShell", true, $inputs);
      $webhook->error("Result: " . print_r($result, 1));
      $webhook->error("Status: " . print_r($status, 1));
      $webhook->error("Error: " . print_r($error, 1));
      
      if ($die) {
        echo "Error: Unable to submit lead to NutShell\n\n";
        echo print_r($status, 1) . "\n\n";
        echo print_r($error, 1) . "\n\n";
      
        http_response_code(500);
        die();
      }
    }
  
    return $success;
  }
}
