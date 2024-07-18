<?php

class MetricWiseCRM
{
  /**
   * Send a lead to MetricWise
   *
   * Config Example:
   * $config = [
   *   'username' => '{USERNAME}', // (required)
   *   'api_url' => 'https://api-centralpa.metricwise.net/1.0/webservice.php', // (required)
   *   'api_key' => '{API_KEY}', // (required)
   *   'access_key' => '{ACCESS_KEY}' // (required)
   * ];
   *
   * API Documentation
   * @link https://github.com/MetricWise/metricwise-api-php
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
    
    $result = null;
    $status = null;
    $error = null;
    $success = false;
    
    $step = 1;
    $token = null;
    $session_name = null;
    $user_id = null;
    
    // Set up default values
    $defaults = [
      'firstname' => $webhook->firstname(),
      'lastname' => $webhook->lastname(),
      'email' => $webhook->email(),
      'phone' => $webhook->phone(),
      'lane' => $webhook->address(),
      'city' => $webhook->city(),
      'state' => $webhook->state(),
      'code' => $webhook->zip(),
      'description' => $webhook->forminfo(),
      'leadsource' => 'Internet',
      'leadstatus' => 'Hot'
    ];

    $mapping = [
      'firstname' => ['first_name', 'firstname'],
      'lastname' => ['last_name', 'lastname'],
      'email' => ['email_address'],
      'phone' => ['phone1', 'phone_number'],
      'lane' => ['address', 'address1', 'streetaddress', 'street'],
      'city' => [],
      'state' => [],
      'code' => ['zip', 'zip_code', 'postal_code'],
      'description' => ['notes', 'comments'],
      'leadsource' => ['source'],
      'leadstatus' => ['status']
    ];
    
    $custom = isset($config['custom']) && is_array($config['custom']) ? $config['custom'] : [];
    
    $webhook->map($inputs, $defaults, $mapping, $custom);
    
    // Testing
    if ($webhook->test) {
      $protect = [
        'lastname',
        'phone',
        'email',
        'lane'
      ];
      
      $json = $webhook->protectArray($inputs, 4, $protect);
      $json = json_encode($json, JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT);
      $webhook->info($json);
    }
    
    // Validate Username
    if (empty($config['username'])) {
      $error = 'Username is missing';
    }
    
    // Validate API URL
    if (empty($config['api_url'])) {
      $error = 'API URL is missing';
    }
    
    // Validate API Key
    if (empty($config['api_key'])) {
      $error = 'API key is missing';
    }
    
    // Validate Access Key
    if (empty($config['access_key'])) {
      $error = 'Access key is missing';
    }
    
    // Send lead
    if (!$error && $webhook->send) {
      $step = 1;
      
      // Step 1: Get token
      $response = $this->webservice($config);
      
      $result = $response->result;
      $status = $response->status;
      $error = $response->error;
      $success = $response->success;
      $json = $response->json;
      
      if ($success) {
        if (!empty($json->success)
          && isset($json->result) 
          && !empty($json->result->token))
        {
          $token = $json->result->token;
        } else {
          $success = false;
          
          // Web Service API
          if (isset($json->error) && !empty($json->error->message)) {
            $error = $json->error->message;
          }
          
          // AWS API Gateway
          if (!$error && !empty($json->message)) {
            $error = $json->message;
          }
        }
      }
      
      // Step 2: Login & get session
      if ($success) {
        $step = 2;
        
        $response = $this->webservice($config, [
          'operation' => 'login',
          'username' => $config['username'],
          'accessKey' => md5($token . $config['access_key'])
        ]);
        
        $result = $response->result;
        $status = $response->status;
        $error = $response->error;
        $success = $response->success;
        $json = $response->json;
      
        if ($success) {
          if (!empty($json->success)
            && !empty($json->result)
            && !empty($json->result->sessionName)
            && !empty($json->result->userId))
          {
            $session_name = $json->result->sessionName;
            $user_id = $json->result->userId;
            
            $inputs['assigned_user_id'] = $user_id;
          } else {
            $success = false;
            
            // Web Service API
            if (isset($json->error) && !empty($json->error->message)) {
              $error = $json->error->message;
            }
          }
        }
      }
    
      // Step 3: Send lead
      if ($success) {
        $step = 3;
        
        $response = $this->webservice($config, [
          'operation' => 'create',
          'sessionName' => $session_name,
          'elementType' => 'Leads',
          'element' => json_encode($inputs),
        ]);
        
        $result = $response->result;
        $status = $response->status;
        $error = $response->error;
        $success = $response->success;
        $json = $response->json;
        
        if ($success) {
          if (empty($json->success)) {
            $success = false;
            
            // Web Service API
            if (isset($json->error) && !empty($json->error->message)) {
              $error = $json->error->message;
            }
          }
        }
      }
      
      // Step 4: Logout
      if ($success) {
        $step = 4;
        
        $this->webservice($config, [
          'operation' => 'logout',
          'sessionName' => $session_name,
        ]);
      }
    }
    
    // Response debugging
    if ($json) {
      //$webhook->info($json);
    } else if ($result) {
      //$webhook->info($result);
    }
    
    // Log status
    if ($success) {
      $webhook->success("Lead: \"{$inputs['firstname']}\" | Lead successfully submitted to MetricWise");
    } else {
      $webhook->error("Lead: \"{$inputs['firstname']}\" | Unable to submit lead to MetricWise", true, $inputs);
      $webhook->error("Result: " . print_r($result, 1));
      $webhook->error("Status: " . print_r($status, 1) . " (Step: {$step})");
      $webhook->error("Error: " . print_r($error, 1));
      
      if ($die) {
        echo "Error: Unable to submit lead to MetricWise\n\n";
        echo print_r($status, 1) . "\n\n";
        echo print_r($error, 1) . "\n\n";
      
        http_response_code(500);
        die();
      }
    }
    
    return $success;
  }
  
	private function webservice($config, $inputs = null)
  {
    $result = null;
    $status = null;
    $error = null;
    $success = false;
    $json = null;
    
		if ($inputs) {
      // Build query
      $query = http_build_query($inputs);
      
      $ch = curl_init();
      
      curl_setopt_array($ch, [
        CURLOPT_URL => $config['api_url'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $query,
        CURLOPT_HTTPHEADER => [
          "Accept: */*",
          "Cache-Control: no-cache",
          "Pragma: no-cache",
          "Connection: keep-alive",
          "Content-Length: " . strlen($query),
          "X-Api-Key: {$config['api_key']}"
        ],
      ]);

      $result = curl_exec($ch);
      $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
      $error = curl_error($ch);

      curl_close($ch);
		} else {
      $url = $config['api_url'] . '?operation=getchallenge&username=' . $config['username'];
      
			$ch = curl_init();
      
      curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTPHEADER => [
          "Accept: */*",
          "Cache-Control: no-cache",
          "Pragma: no-cache",
          "Connection: keep-alive",
          "X-Api-Key: {$config['api_key']}"
        ],
      ]);
      
      $result = curl_exec($ch);
      $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
      $error = curl_error($ch);
      
      curl_close($ch);
		}
    
    $success = $status && ($status == 200 || $status == 204 || $status == 202);
    
    // Decode JSON response
    if ($success) {
      $json = JsonUtils::decode($result);
      
      if (!JsonUtils::isObject($json)) {
        $success = false;
      }
    }
    
    $response = (object) [
      'result' => $result,
      'status' => $status,
      'error' => $error,
      'success' => $success,
      'json' => $json
    ];
    
		return $response;
	}
}
