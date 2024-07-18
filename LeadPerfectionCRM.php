<?php

class LeadPerfectionCRM
{
  /**
   * Sends a lead to Lead Perfection
   *
   * Credentials Example:
   * $config = [
   *   'server' => '{SERVER}',
   *   'user' => '{USER}',
   *   'password' => '{PASSWORD}',
   *   'database' => '{DATABASE}',
   *   'procedure' => '{PROCEDURE}'
   *   'srs_id' => true|false // Optional
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
      'firstname' => $webhook->firstname(),
      'lastname' => $webhook->lastname(),
      'email' => $webhook->email(),
      'phone' => $webhook->phone(),
      'phonetype' => '1',
      'phone2' => '',
      'phonetype2' => '3',
      'phone3' => '',
      'phonetype3' => '',
      'address1' => $webhook->address(),
      'city' => $webhook->city(),
      'state' => $webhook->state(),
      'zip' => $webhook->zip(),
      'notes' => $webhook->forminfo(),
      'productID' => '',
      'proddescr' => '',
      'sender' => '',
      'sentto' => '',
      'callmorning' => '',
      'callafternoon' => '',
      'callevening' => '',
      'callweekend' => '',
      'datereceived' => date('Y-m-d H:i:s'),
      'Source' => '',
      'srs_id' => '', // Sub Source ID
      'adword' => '',
      'pro_id' => '', // Promoter ID
      'hear_about_us' => '',
      'LogNumber' => '',
      'ForceSource' => ''
    ];
    
    $mapping = [
      'firstname' => ['first_name'],
      'lastname' => ['last_name'],
      'email' => ['email1'],
      'phone' => ['phone1'],
      'phonetype' => ['phonetype1'],
      'phone2' => [],
      'phonetype2' => [],
      'phone3' => [],
      'phonetype3' => [],
      'address1' => ['address'],
      'city' => ['city'],
      'state' => [],
      'zip' => ['zip_code', 'postal_code'],
      'notes' => [],
      'productID' => [],
      'proddescr' => [],
      'sender' => [],
      'sentto' => [],
      'callmorning' => [],
      'callafternoon' => [],
      'callevening' => [],
      'callweekend' => [],
      'datereceived' => [],
      'Source' => [],
      'srs_id' => [],
      'adword' => [],
      'pro_id' => [],
      'hear_about_us' => [],
      'LogNumber' => [],
      'ForceSource' => []
    ];
    
    $custom = isset($config['custom']) && is_array($config['custom']) ? $config['custom'] : [];
    
    $webhook->map($inputs, $defaults, $mapping, $custom);
    
    // Lead Perfection params
    $params = [
      'firstname' => (object) ['maxlen' => 25],
      'lastname' => (object) ['maxlen' => 25],
      'email' => (object) ['maxlen' => 100],
      'phone' => (object) ['maxlen' => 15],
      'phonetype' => (object) ['maxlen' => 1],
      'phone2' => (object) ['maxlen' => 15],
      'phonetype2' => (object) ['maxlen' => 1],
      'phone3' => (object) ['maxlen' => 15],
      'phonetype3' => (object) ['maxlen' => 1],
      'address1' => (object) ['maxlen' => 35],
      'city' => (object) ['maxlen' => 35],
      'state' => (object) ['maxlen' => 2],
      'zip' => (object) ['maxlen' => 5],
      'notes' => (object) ['maxlen' => 2000],
      'productID' => (object) ['maxlen' => 10],
      'proddescr' => (object) ['maxlen' => 20],
      'sender' => (object) ['maxlen' => 100],
      'sentto' => (object) ['maxlen' => 100],
      'callmorning' => (object) ['maxlen' => null],
      'callafternoon' => (object) ['maxlen' => null],
      'callevening' => (object) ['maxlen' => null],
      'callweekend' => (object) ['maxlen' => null],
      'datereceived' => (object) ['maxlen' => 19],
      'srs_id' => (object) ['maxlen' => null],
      
      // Not required params
      'Source' => (object) ['required' => false, 'maxlen' => 15],
      'ForceSource' => (object) ['required' => false, 'maxlen' => null],
      'LogNumber' => (object) ['required' => false, 'maxlen' => null],
      'adword' => (object) ['required' => false, 'maxlen' => 50],
      'pro_id' => (object) ['required' => false, 'maxlen' => null],
      'hear_about_us' => (object) ['required' => false, 'maxlen' => null]
    ];
    
    // Clean up & prep inputs
    foreach ($params as $name => $settings) {
      if (array_key_exists($name, $inputs)) {
        $value = $inputs[$name];
        
        // Prep state value
        if ($name === 'state') {
          $value = $webhook->convertState($value);
        }
        
        // Prep numeric only values
        if (in_array($name, ['phone', 'phone2', 'phone3', 'zip'])) {
          $value = preg_replace('/\D/', '', $value);
        }
        
        // Crop value to max length
        if (is_int($settings->maxlen) && (is_string($value) || is_numeric($value))) {
          $value = strval(substr($value, 0, $settings->maxlen));
        }
        
        // Convert booleans & nulls to strings
        if (is_bool($value) || is_null($value)) {
          $value = '';
        }
        
        // Remove empty non-required params
        if (isset($settings->required) && !$settings->required && (is_string($value) && !strlen($value))) {
          unset($inputs[$name]);
          continue;
        }
        
        $inputs[$name] = $value;
      }
    }
    
    // Testing
    if ($webhook->test) {
      $protect = [
        'lastname',
        'email',
        'phone',
        'phone2',
        'phone3',
        'address1'
      ];
      
      $json = $webhook->protectArray($inputs, 4, $protect);
      $json = json_encode($json, JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT);
      $webhook->info($json);
    }
    
    // Validate credentials
    if (empty($config['server'])
      || empty($config['user'])
      || empty($config['password'])
      || empty($config['database'])
      || empty($config['procedure'])) {
        
      $error = 'Credentials is invalid';
    }
    
    // Validate srs_id
    if ((!isset($config['srs_id']) || $config['srs_id']) && empty($inputs['srs_id'])) {
      $error = 'srs_id is missing';
    }
    
    // Send lead
    if (!$error && $webhook->send) {
      // Connect to the remote MSSQL database
      $conn = mssql_connect($config['server'], $config['user'], $config['password'])
        or $error = "Couldn't connect to SQL Server {$config['server']}";

      if (!$error) {
        // Select the appropriate database
        mssql_select_db($config['database'], $conn);

        // Initialize Lead Perfection stored procedure for storing leads
        $stmt = mssql_init($config['procedure'], $conn);

        // Mandatory MSSQL settings that must be set to what is below
        mssql_query('SET CONCAT_NULL_YIELDS_NULL ON', $conn);
        mssql_query('SET ANSI_WARNINGS ON', $conn);
        mssql_query('SET ANSI_PADDING ON', $conn);
        
        // Bind the MSSQL query with the proper variables to the right database columns with the proper variable type and length
        if (isset($inputs['firstname'])) { mssql_bind($stmt, '@firstname', $inputs['firstname'], SQLVARCHAR, false, false, $params['firstname']->maxlen); }
        if (isset($inputs['lastname'])) { mssql_bind($stmt, '@lastname', $inputs['lastname'], SQLVARCHAR, false, false, $params['lastname']->maxlen); }
        if (isset($inputs['email'])) { mssql_bind($stmt, '@email', $inputs['email'], SQLVARCHAR, false, false, $params['email']->maxlen); }
        if (isset($inputs['phone'])) { mssql_bind($stmt, '@phone', $inputs['phone'], SQLVARCHAR, false, false, $params['phone']->maxlen); }
        if (isset($inputs['phonetype'])) { mssql_bind($stmt, '@phonetype', $inputs['phonetype'], SQLVARCHAR, false, false, $params['phonetype']->maxlen); }
        if (isset($inputs['phone2'])) { mssql_bind($stmt, '@phone2', $inputs['phone2'], SQLVARCHAR, false, false, $params['phone2']->maxlen); }
        if (isset($inputs['phonetype2'])) { mssql_bind($stmt, '@phonetype2', $inputs['phonetype2'], SQLVARCHAR, false, false, $params['phonetype2']->maxlen); }
        if (isset($inputs['phone3'])) { mssql_bind($stmt, '@phone3', $inputs['phone3'], SQLVARCHAR, false, false, $params['phone3']->maxlen); }
        if (isset($inputs['phonetype3'])) { mssql_bind($stmt, '@phonetype3', $inputs['phonetype3'], SQLVARCHAR, false, false, $params['phonetype3']->maxlen); }
        if (isset($inputs['address1'])) { mssql_bind($stmt, '@address1', $inputs['address1'], SQLVARCHAR, false, false, $params['address1']->maxlen); }
        if (isset($inputs['city'])) { mssql_bind($stmt, '@city', $inputs['city'], SQLVARCHAR, false, false, $params['city']->maxlen); }
        if (isset($inputs['state'])) { mssql_bind($stmt, '@state', $inputs['state'], SQLVARCHAR, false, false, $params['state']->maxlen); }
        if (isset($inputs['zip'])) { mssql_bind($stmt, '@zip', $inputs['zip'], SQLVARCHAR, false, false, $params['zip']->maxlen); }
        if (isset($inputs['notes'])) { mssql_bind($stmt, '@notes', $inputs['notes'], SQLVARCHAR, false, false, $params['notes']->maxlen); }
        if (isset($inputs['productID'])) { mssql_bind($stmt, '@productID', $inputs['productID'], SQLVARCHAR, false, false, $params['productID']->maxlen); }
        if (isset($inputs['proddescr'])) { mssql_bind($stmt, '@proddescr', $inputs['proddescr'], SQLVARCHAR, false, false, $params['proddescr']->maxlen); }
        if (isset($inputs['sender'])) { mssql_bind($stmt, '@sender', $inputs['sender'], SQLVARCHAR, false, false, $params['sender']->maxlen); }
        if (isset($inputs['sentto'])) { mssql_bind($stmt, '@sentto', $inputs['sentto'], SQLVARCHAR, false, false, $params['sentto']->maxlen); }
        if (isset($inputs['callmorning'])) { mssql_bind($stmt, '@callmorning', $inputs['callmorning'], SQLBIT, false, false); }
        if (isset($inputs['callafternoon'])) { mssql_bind($stmt, '@callafternoon', $inputs['callafternoon'], SQLBIT, false, false); }
        if (isset($inputs['callevening'])) { mssql_bind($stmt, '@callevening', $inputs['callevening'], SQLBIT, false, false); }
        if (isset($inputs['callweekend'])) { mssql_bind($stmt, '@callweekend', $inputs['callweekend'], SQLBIT, false, false); }
        if (isset($inputs['datereceived'])) { mssql_bind($stmt, '@datereceived', $inputs['datereceived'], SQLVARCHAR, false, false); }
        if (isset($inputs['srs_id'])) { mssql_bind($stmt, '@srs_id', $inputs['srs_id'], SQLINT2, false, false); }
        
        // Not required params
        if (isset($inputs['Source'])) { mssql_bind($stmt, '@Source', $inputs['Source'], SQLVARCHAR, false, false, $params['Source']->maxlen); }
        if (isset($inputs['ForceSource'])) { mssql_bind($stmt, '@ForceSource', $inputs['ForceSource'], SQLBIT, false, false); }
        if (isset($inputs['LogNumber'])) { mssql_bind($stmt, '@LogNumber', $inputs['LogNumber'], SQLINT2, false, false); }
        if (isset($inputs['adword'])) { mssql_bind($stmt, '@adword', $inputs['adword'], SQLVARCHAR, false, false, $params['adword']->maxlen); }
        if (isset($inputs['pro_id'])) { mssql_bind($stmt, '@pro_id', $inputs['pro_id'], SQLINT2, false, false); }
        if (isset($inputs['hear_about_us'])) { mssql_bind($stmt, '@User18', $inputs['hear_about_us'], SQLINT2, false, false); }
        
        // Execute MSSQL procedure
        $success = mssql_execute($stmt) or $error = mssql_get_last_message();

        //Reset and close MSSQL connection
        mssql_free_statement($stmt);
        
        mssql_close($conn);
      }
    }
    
    // Log status
    if ($success) {
      $webhook->success("Lead: \"{$inputs['firstname']}\" | Lead successfully submitted to Lead Perfection");
    } else {
      $webhook->error("Lead: \"{$inputs['firstname']}\" | Unable to submit lead to Lead Perfection", true, $inputs);
      $webhook->error("Result: " . print_r($success, 1));
      $webhook->error("Error: " . print_r($error, 1));
      
      if ($die) {
        echo "Error: Unable to submit lead to Lead Perfection\n\n";
        echo print_r($error, 1) . "\n\n";
        
        http_response_code(500);
        die();
      }
    }
    
    return $success;
  }
}
