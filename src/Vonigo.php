<?php

namespace Vonigo;

define('VONIGO_DATEMODE_CREATED', 1);
define('VONIGO_DATEMODE_EDITED', 2);
define('VONIGO_DATEMODE_SCHEDULED', 3);

define('VONIGO_DEBUG_NONE', 0);
define('VONIGO_DEBUG', 1);
define('VONIGO_DEBUG_HIGH', 2);
define('VONIGO_DEBUG_SCREEN', 4);
define('VONIGO_DEBUG_ALL', 7);

define('VONIGO_METHOD_DELETE', 4);


class vonigo {
  private $debug = false;
  private $company = '';
  private $username = '';
  private $password = '';
  public $base_url = '';
  private $ch = NULL;
  private $securityToken = '';
  private $userAgent = 'Vonigo API library for PHP';

  public function __construct($settings) {
    $this->username = $settings['username'];
    $this->password = $settings['password'];
    $this->company = $settings['company'];
    $this->base_url = $settings['base_url'];
  }

  protected function getSecurityToken() {
    return $this->securityToken;
  }

  protected function setSecurityToken($token) {
    $this->securityToken = $token;
  }

  private function set_curl_handle() {
    if (!$this->ch) {
      $this->ch = curl_init();
      curl_setopt($this->ch, CURLOPT_USERAGENT, $this->userAgent);
    }
  }

  private function request($method, $params = array(), $retry = true) {
    $url = $this->base_url . '/' . $method . '/';

    // remove double slash at end
    $url = preg_replace('|//$|', '/', $url);

    if ($this->debug & VONIGO_DEBUG) {
      $this->showDebug('get-params: ', print_r($params, true));
    }
    if (sizeof($params)) {
      $url .= '?' . http_build_query($params, NULL, '&');
    }
    if ($this->debug & VONIGO_DEBUG) {
      $this->showDebug('url:' . $url);
    }
    curl_setopt($this->ch, CURLOPT_URL, $url);
    curl_setopt($this->ch, CURLOPT_RETURNTRANSFER, 1);
    $return = new stdClass();
    $return->body = curl_exec($this->ch);
    $return->info = curl_getinfo($this->ch);
    if ($this->debug & VONIGO_DEBUG) {
      $return->rawbody = $return->body;
    }
    $decoded = json_decode($return->body);

    if (is_null($decoded)) {
      $decoded = (object) array('errNo' => -400, 'errMsg' => 'invalid JSON response from server', 'body' => $return->body);
      if ($this->debug & VONIGO_DEBUG) {
        $this->showDebug('invalid response: ' . print_r($return->body, true));
      }
    }
  
    if (empty($decoded->errNo)) {
      $decoded->errNo = 0;
    }
      
    if (!empty($decoded->errNo) && $decoded->errNo == -421) {
      $this->setSecurityToken(null);
      $auth = $this->authenticate();
      if ($auth && $retry) {
        $this->request($method, $params, false);
      }
    }

    // if there is one validation error and it is -5213, pretend there are no errors
    // -5213 -> "Same franchise ID supplied."
    if ($decoded->errNo == -600) {
      if (!empty($decoded->Errors) && count($decoded->Errors) == 1 && $decoded->Errors[0]->errNo == -5213) {
        $decoded->errNo = 0;
      }
    }

    if ($decoded->errNo == -421) {
      $this->setSecurityToken(null);
    }

    // check for http errors
    if ($return->info['http_code'] != 200) {
      $decoded->httpCode = $return->info['http_code'];
    }
    else {
      // add URL to error messages
      if ($decoded->errNo != 0) {
        $decoded->errURL = $url;
      }
    }

    $return->body = json_encode($decoded);

    if ($this->debug & VONIGO_DEBUG_HIGH) {
      $this->showDebug('info: ' . print_r($return, true));
    }
    if ($this->debug & VONIGO_DEBUG) {
      $this->showDebug('body: ' . print_r($return->body, true));
    }
    return $return;
  }

  private function set_post_options($params) {
    $auth = $this->authenticate();
    if ($auth === TRUE) {
      $params['securityToken'] = $this->getSecurityToken();
      curl_setopt($this->ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
      curl_setopt($this->ch, CURLOPT_POST, 1);
      curl_setopt($this->ch, CURLOPT_POSTFIELDS, json_encode($params));
      if ($this->debug & VONIGO_DEBUG) {
        $this->showDebug('post-params: ' . print_r($params, true));
        if (defined('JSON_PRETTY_PRINT')) {
          $this->showDebug('post-params as json: ' . json_encode($params, JSON_PRETTY_PRINT));
        }
        else {
          $this->showDebug('post-params as json: ' . json_encode($params));
        }
      }
    }
    else {
      $this->setSecurityToken(null);
    }
  }

  private function get($method, $params = array()) {
    $this->set_curl_handle();
    $auth = $this->authenticate();
    if ($auth === TRUE) {
      $params['securityToken'] = $this->getSecurityToken();
      // set options; remove post options
      curl_setopt($this->ch, CURLOPT_HTTPHEADER, array('Content-Type: text/html'));
      curl_setopt($this->ch, CURLOPT_POST, 0);
      curl_setopt($this->ch, CURLOPT_HTTPGET, 1);
      return $this->request($method, $params);
    }
    else {
      $this->setSecurityToken(null);
    }
    return $auth;
  } 

  public function authenticate() {    
    if ($this->getSecurityToken()) {
      return TRUE;
    }
    $this->set_curl_handle();
    $params = array(
      'company' => $this->company,
      'userName' => $this->username,
      'password' => md5($this->password),
      'appVersion' => 1,
    );
    if ($this->debug & VONIGO_DEBUG) {
      $this->showDebug('authentication parameters: ', print_r($params, true));
    }
    $return = $this->request('security/login', $params);

    if ($return->info['http_code'] != 200) {
      if ($this->debug & VONIGO_DEBUG) {
        $this->showDebug('response info: ' . print_r($return->info, true));
      }
      return $return;
    } 

    $result = json_decode($return->body);
    if (!$result->securityToken) {
      return $return;
    }

    $this->setSecurityToken($result->securityToken);
    return TRUE;
  }

  private function post($method, $params = array()) {
    $this->set_curl_handle();
    $auth = $this->authenticate();
    if ($auth === true) {
      curl_setopt($this->ch, CURLOPT_HTTPGET, 0);
      $this->set_post_options($params);
      return $this->request($method);
    }
    else {
      $this->setSecurityToken(null);
    }
    return $auth;
  } 

  public function close() {
    curl_close($this->ch);
  }

  private function data($method, $params = array(), $fields = NULL) {
    $action = 'get';
    if (($method == 'charges' && $params['method'] == 2) || ($method == 'payments' && $params['method'] == 3)){
      $action = 'post';
    }
    $auth = $this->authenticate();
    if (!$auth) {
      $this->setSecurityToken(null);
      return $auth;
    }
    $token = $this->getSecurityToken();
    $params['securityToken'] = $token;

    if (isset($id) && !isset($params['method'])) {
      $params['method'] = '1';
      $params['objectID'] = (string) $id;
    }
    if (!isset($params['method'])) {
      $params['method'] = 0;
    }
    if (isset($fields)) {
      $params['Fields'] = $fields;
      $action = 'post';
    }
    return json_decode($this->{$action}('data/' . $method, $params)->body);
  }

  public function clients($params = array(), $fields = array()) {
    if (empty($fields)) {
      $fields = null;
    }
    return $this->data('clients', $params, $fields);
  }

  public function contacts($params = array(), $fields = null) {
    if (!isset($params['method'])) {
      $params = array('method' => 1);
    }
    return $this->data('contacts', $params, $fields);
  }

  public function contacts2($params = array(), $fields = null) {
    if (!isset($params['method'])) {
      $params = array('method' => 1);
    }
    return $this->data('contacts2', $params, $fields);
  }

  public function emails($params = array(), $fields = null) {
    return $this->data('emails', $params, $fields);
  }

  public function workorders($params = array(), $fields = null) {
    if (isset($params['objectID']) &! isset($params['method'])) {
      $params['method'] = 1;
    }
    if (isset($params['dateStart'])) {
      $params['pageSize'] = 100;
    }
    if (isset($params['pageNo'])) {
      $params['pageSize'] = 100;
    }
    return $this->data('workorders', $params, $fields);
  }

  public function charges($params) {
    return $this->data('charges', $params);
  }

  public function jobs($params = array(), $fields = null) {
    return $this->data('jobs', $params, $fields);
  }

  public function invoices($params) {
    return $this->data('invoices', $params);
  }

  public function payments($params, $fields) {
    return $this->data('payments', $params, $fields);
  }

  public function quotes($params) {
    return $this->data('quotes', $params);
  }

  public function cases($params) {
    return $this->data('cases', $params);
  }

  private function security($method, $params = array()) {
    $auth = $this->authenticate();
    if (!$auth) {
      $this->setSecurityToken(null);
      return $auth;
    }
    
    $params['securityToken'] = $this->getSecurityToken();
    return json_decode($this->get('security/' . $method . '/', $params)->body);
  }

  private function resources($method, $params = array()) {
    $auth = $this->authenticate();
    if (!$auth) {
      $this->setSecurityToken(null);
      return $auth;
    }
    
    if (isset($params)) {
      $params['securityToken'] = $this->getSecurityToken();
    }
    else {
      $params = array(
        'securityToken' => $this->getSecurityToken(),
      );
    }
    foreach ($params as $key => $value) {
      if ($value === NULL) {
        unset($params[$key]);
      }
    }
    return json_decode($this->get('resources/' . $method . '/', $params)->body);
  }

  public function validatePromo($promo, $serviceTypeID, $clientTypeID, $zoneID) {
    $params = array(
      'method' => 3,
      'serviceTypeID' => $serviceTypeID,
      'clientTypeID' => $clientTypeID,
      'zoneID' => $zoneID,
      'promo' => $promo,
    );
    return $this->availability($params);
  }

  public function validateZipCode($zip) {
    $params = array(
      'method' => 1,
      'zip' => $zip,
    );
    return $this->availability($params);
  }
  
  public function availableTimes($params) {
    $params['method'] = 0;
    return $this->availability($params);
  }
  
  public function lockTimes($params) {
    $params['method'] = 2;
    return $this->availability($params);
  }

  private function availability($params) {
    return $this->resources('availability', $params);
  }

  public function taxes($zip) {
    $params = array(
      'method' => 0,
      'zipCode' => $zip,
    );
    return $this->resources('taxes', $params);
  }

  public function priceLists($pageNo=1, $pageSize=1000) {
    $params = array(
      'pageNo' => $pageNo,
      'pageSize' => $pageSize,
    );
    return $this->resources('priceLists', $params);
  }

  public function priceBlocks($priceListID = NULL, $pageNo=NULL, $pageSize=500) {
    $params = array(
      'objectID' => $priceListID,
      'pageNo' => $pageNo,
      'pageSize' => $pageSize,
    );
    return $this->resources('priceBlocks', $params);
  }

  public function priceItems($priceListID=NULL, $priceBlockID=NULL, $pageNo=NULL, $pageSize=NULL) {
    $params = array(
      'method' => 1,
      'pageSize' => 1000,
    );
    if (!empty($priceListID)) {
      $params['priceListID'] = $priceListID;
    }
    if (!empty($priceBlockID)) {
      $params['priceBlockID'] = $priceBlockID;
    }
    if (!empty($pageNo)) {
      $params['pageNo'] = $pageNo;
    }
    if (!empty($pageSize)) {
      $params['pageSize'] = $pageSize;
    }
    return $this->resources('priceItems', $params);
  }

  public function serviceTypes() {
    return $this->resources('serviceTypes');
  }

  private function system($method, $params = array()) {
    $auth = $this->authenticate();
    if (!$auth) {
      $this->setSecurityToken(null);
      return $auth;
    }

    if (isset($params)) {
      $params['securityToken'] = $this->getSecurityToken();
    }
    else {
      $params = array(
        'securityToken' => $this->getSecurityToken(),
      );
    }
    return json_decode($this->get('system/' . $method . '/', $params)->body);
  }

  public function modules() {
    return $this->system('modules');
  }

  public function database() {
    return $this->system('database');
  }

  public function forms($moduleID=NULL, $formID=null) {
    $params = array();
    if (isset($moduleID)) {
      $params['moduleID'] = $moduleID;
    }
    if (isset($formID)) {
      $params['formID'] = $formID;
    }
    return $this->system('forms', $params);
  }

  public function objects($objectID = NULL, $params = array()) {
    if (isset($objectID)) {
      $params['method'] = 2;
      $params['objectID'] = $objectID;
    }
    return $this->system('objects', $params);
  }

  public function franchises() {
    return $this->security('franchises');
  }

  public function session($franchiseID = NULL) {
    if (isset($franchiseID)) {
      $params = array(
        'franchiseID' => $franchiseID,
      );
    }
    return $this->security('session', $params);
  }

  public function logout() {
    return $this->security('logout');
  }

  public function groups() {
    return $this->security('groups');
  }

  public function offices() {
    return $this->security('offices');
  }

  public function routes() {
    return $this->security('routes');
  }

  public function flags() {
    return $this->security('flags');
  }

  public function setDebug($value) {
    $this->debug = $value;
  }

  public function locations($params, $fields = null) {
    return $this->data('locations', $params, $fields);
  }
  
  private function showDebug($info) {
    if ($this->debug & VONIGO_DEBUG_SCREEN) {
      echo '<pre>' . $info . '</pre>';
    }
    else {
      error_log($info);
    }
  }

  /**
   * Converts a vonigo object with a fields array to an associative array
   * indexed by field ID.
   *
   * @param stdClass $object - a vonigo result object
   *
   * @return array $fields - an associative array of the Fields property,
   * indexed by field ID
   */
  public static function mapFieldsArray($object) {
    $fields = array();
    if (isset($object->Fields)) {
      foreach($object->Fields as $field) {
        $fields[$field->fieldID] = $field;
      }
    }
    return $fields;
  }

  /**
   * Pages through a large result set.
   */
  public function pageRequest($method, $params, $property, $fields = null) {
    $results = array();
    $done = false;

    if (empty($params['pageSize'])) {
      $params['pageSize'] = 100;
    }
    $params['pageNo'] = 1;

    while (!$done) {
      $request = call_user_func(array($this, $method), $params, $fields);
      if ($request->errNo == 0 && !empty($request->{$property})) {
        $results = array_merge($results, $request->{$property});
        if (sizeof($request->{$property}) < $params['pageSize']) {
          $done = true;
        }
        $params['pageNo']++;
      }
      else {
        $done = true;
      }
    }
    return $results;
  }

  /**
   *  Sometimes vonigo will use the word 'True' to indicate a true value,
   *  other times, a 1.
   */
  public static function isTrue($value) {
    if ($value === 'True' || $value == 1)  {
      return true;
    }
    if ($value === 'False' || $value == 0) {
      return false;
    }
  }

}
