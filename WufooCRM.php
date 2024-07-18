<?php

class WufooCRM
{
  /**
   * Send form to Wufoo
   *
   * $config = [
   *   'url' => '{WUFOO_FORM_URL}'
   * ]
   *
   * @note Used by Schedule an Appointment
   * @param array $inputs
   * @param array|string $config|$url
   * @param array [$protect]
   * @return boolean
   */
  public function send($inputs, $config, $protect = [])
  {
    global $webhook;
    
    $success = false;
    $result = null;
    $status = null;
    $error = null;

    $config = is_string($config) ? ['url' => $config] : $config;
    $config = is_array($config) ? $config : [];

    // Prep inputs
    foreach ($inputs as $key => &$value) {
      if (is_string($value)) {
        $value = htmlspecialchars($value);
      }
    }

    if ($webhook->test) {
      $json = $webhook->protectArray($inputs, 4, $protect);
      $json = json_encode($json, JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT);
      $webhook->info($json);
    }
    
    // Validate URL
    if (empty($config['url'])) {
      $error = 'Wufoo form url missing';
    }

    if (!$error && $webhook->send) {
      $query = http_build_query($inputs);
      
      $ch = curl_init();
      curl_setopt($ch, CURLOPT_URL, $config['url']);
      curl_setopt($ch, CURLOPT_POST, count($inputs));
      curl_setopt($ch, CURLOPT_POSTFIELDS, $query);
      curl_setopt($ch, CURLOPT_TIMEOUT, 30);
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

      $result = curl_exec($ch);
      $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
      $error = curl_error($ch);

      curl_close($ch);

      $success = $status && ($status == 200 || $status == 302);
    }
    
    // Log status
    if ($success) {
      $webhook->success("Lead successfully submitted to Wufoo");
    } else {
      $webhook->error("Unable to submit lead to Wufoo", true, $inputs);
      $webhook->error("Result: " . print_r($result, 1));
      $webhook->error("Status: " . print_r($status, 1));
      $webhook->error("Error: " . print_r($error, 1));
    }
    
    return $success;
  }
}
