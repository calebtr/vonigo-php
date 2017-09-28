<?php

namespace VonigoPHP;

define('VONIGO_DATEMODE_CREATED', 1);
define('VONIGO_DATEMODE_EDITED', 2);
define('VONIGO_DATEMODE_SCHEDULED', 3);

define('VONIGO_DEBUG_NONE', 0);
define('VONIGO_DEBUG', 1);
define('VONIGO_DEBUG_HIGH', 2);
define('VONIGO_DEBUG_SCREEN', 4);
define('VONIGO_DEBUG_ALL', 7);

define('VONIGO_METHOD_LIST', 0);
define('VONIGO_METHOD_READ', 1);
define('VONIGO_METHOD_CREATE', 2);
define('VONIGO_METHOD_UPDATE', 3);
define('VONIGO_METHOD_DELETE', 4);

define('VONIGO_OBJECT_ID_FRANCHISE', 1);
define('VONIGO_OBJECT_ID_ROUTE', 5);
define('VONIGO_OBJECT_ID_VEHICLE', 6);
define('VONIGO_OBJECT_ID_CLIENT', 7);
define('VONIGO_OBJECT_ID_CONTACT', 8);
define('VONIGO_OBJECT_ID_JOB', 10);
define('VONIGO_OBJECT_ID_WORKORDER', 12);
define('VONIGO_OBJECT_ID_INVOICE', 13);
define('VONIGO_OBJECT_ID_USER', 15);
define('VONIGO_OBJECT_ID_REQUEST', 17);
define('VONIGO_OBJECT_ID_PAYMENT', 18);
define('VONIGO_OBJECT_ID_CASE', 19);
define('VONIGO_OBJECT_ID_LOCATION', 20);
define('VONIGO_OBJECT_ID_TASK', 21);
define('VONIGO_OBJECT_ID_PRICE LIST', 23);
define('VONIGO_OBJECT_ID_LEAD', 28);
define('VONIGO_OBJECT_ID_QUOTE', 31);
define('VONIGO_OBJECT_ID_TAX', 36);
define('VONIGO_OBJECT_ID_NOTE', 48);
define('VONIGO_OBJECT_ID_CREW', 50);
define('VONIGO_OBJECT_ID_CHARGE', 57);
define('VONIGO_OBJECT_ID_EMAIL', 61);
define('VONIGO_OBJECT_ID_PRICELIST', 68);
define('VONIGO_OBJECT_ID_PRICEBLOCK', 69);
define('VONIGO_OBJECT_ID_PRICEITEM', 70);
define('VONIGO_OBJECT_ID_SIGNATURE', 71);
define('VONIGO_OBJECT_ID_BOOKOFF', 74);
define('VONIGO_OBJECT_ID_SERVICE_TYPE', 78);
define('VONIGO_OBJECT_ID_WAYPOINT', 84);

class Vonigo {

    /**
     * @var string - Vonigo base URL
     */
    public $base_url = '';

    /**
     * @var - cURL handle object
     */
    private $ch = NULL;

    /**
     * @var string - Vonigo company setting
     *
     * TODO: deprecate the use of this value
     */
    private $company = '';

    /**
     * @var int - debug setting
     */
    protected $debug = 0;

    /**
     * @var string - Vonigo password
     */
    private $password = '';

    /**
     * @var string - security token
     */
    private $securityToken = '';

    /**
     * @var string - string sent as user agent header in http requests
     */
    protected $userAgent = 'Vonigo API library for PHP';

    /**
     * @var string Vonigo username.
     */
    private $username = '';

    /**
     * Vonigo constructor.
     *
     * @param $settings
     */
    public function __construct($settings) {
        $this->username = $settings['username'];
        $this->password = $settings['password'];
        $this->company = $settings['company'];
        $this->base_url = $settings['base_url'];
    }

    /**
     * Gets security token value.
     *
     * @return string
     */
    protected function getSecurityToken() {
        return $this->securityToken;
    }

    /**
     * Sets security token value.
     *
     * @param $token
     */
    protected function setSecurityToken($token) {
        $this->securityToken = $token;
    }

    /**
     * Helper function to set cURL handle.
     */
    protected function set_curl_handle() {
        if (!$this->ch) {
            $this->ch = curl_init();
            curl_setopt($this->ch, CURLOPT_USERAGENT, $this->userAgent);
        }
    }

    /**
     * Helper function to make HTTP request to Vonigo.
     *
     * @param $method
     * @param array $params
     * @param bool $retry
     * @return \stdClass
     */
    protected function request($method, $params = array(), $retry = true) {
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
        $return = new \stdClass();
        $return->body = curl_exec($this->ch);
        $return->info = curl_getinfo($this->ch);
        if ($this->debug & VONIGO_DEBUG) {
            $return->rawbody = $return->body;
        }
        $decoded = json_decode($return->body);

        if (is_null($decoded)) {
            $decoded = (object) array('errNo' => -400, 'errMsg' => 'invalid JSON response from server', 'body' => $return->body);
            throw new \Exception(sprintf('Invalid JSON response from Vonigo from url %s: ' . PHP_EOL . ' %s', $url, $return->body));
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

    /**
     * Helper function to set POST options when HTTP POST is used.
     *
     * @param $params
     */
    protected function set_post_options($params) {
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

    /**
     * Helper function to make an HTTP or HTTPS GET request.
     *
     * @param $method
     * @param array $params
     * @return bool|\stdClass
     */
    protected function get($method, $params = array()) {
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

    /**
     * Authenticates a session.
     *
     * @return bool|\stdClass
     */
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

    /**
     * Helper function to send HTTP post requests to Vonigo.
     *
     * @param $method
     * @param array $params
     * @return bool|\stdClass
     */
    protected function post($method, $params = array()) {
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

    /**
     * Closes cURL connections.
     */
    public function close() {
        curl_close($this->ch);
    }

    /**
     * Helper function for methods using the 'data' endpoint.
     *
     * Sets GET or POST depending on needs of the request:
     *
     *   - GET if there are no fields being requested or updated;
     *   - POST if there are fields being requested or updated.
     */
    protected function data($method, $params = array(), $fields = NULL) {
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
        $result = $this->{$action}('data/' . $method, $params);
        if (!empty($result->body)) {
            return json_decode($result->body);
        }
    }

    /**
     * Creates, reads, updates or deletes clients records.
     *
     * @param array $params
     * @param array $fields
     * @return bool|mixed|\stdClass
     */
    public function clients($params = array(), $fields = array()) {
        if (empty($fields)) {
            $fields = null;
        }
        return $this->data('clients', $params, $fields);
    }

    /**
     * Creates, reads, updates or deletes contacts records.
     *
     * @param array $params
     * @param array $fields
     * @return bool|mixed|\stdClass
     */
    public function contacts($params = array(), $fields = array()) {
        if (!isset($params['method'])) {
            $params = array('method' => 1);
        }
        return $this->data('contacts', $params, $fields);
    }

    /**
     * Creates, reads, updates or deletes asset records.
     *
     * Replaces the contacts2 method.
     *
     * @param array $params
     * @param array $fields
     * @return bool|mixed|\stdClass
     */
    public function assets($params = array(), $fields = array()) {
        if (!isset($params['method'])) {
            $params = array('method' => 0);
        }
        return $this->data('assets', $params, $fields);
    }

    /**
     * Creates, reads, updates or deletes email records.
     *
     * @param array $params
     * @param array $fields
     * @return bool|mixed|\stdClass
     */
    public function emails($params = array(), $fields = array()) {
        return $this->data('emails', $params, $fields);
    }

    /**
     * Creates, reads, updates or deletes leads records.
     *
     * @param array $params
     * @param array $fields
     * @return bool|mixed|\stdClass
     */
    public function leads($params = array(), $fields = array()) {
        return $this->data('leads', $params, $fields);
    }

    /**
     * Creates, reads, updates or deletes location objects.
     *
     * @param $params
     * @param array $fields
     * @return bool|mixed|\stdClass
     */
    public function locations($params, $fields = array()) {
        return $this->data('locations', $params, $fields);
    }

    /**
     * Creates, reads, updates or deletes workorder objects.
     *
     * @param $params
     * @param array $fields
     * @return bool|mixed|\stdClass
     */
    public function workorders($params = array(), $fields = array()) {
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

    /**
     * Creates, reads, updates or deletes charge objects.
     *
     * @param $params
     * @param array $fields
     * @return bool|mixed|\stdClass
     */
    public function charges($params, $fields = array()) {
        return $this->data('charges', $params, $fields);
    }

    /**
     * Creates, reads, updates or deletes job objects.
     *
     * @param $params
     * @param array $fields
     * @return bool|mixed|\stdClass
     */
    public function jobs($params = array(), $fields = array()) {
        return $this->data('jobs', $params, $fields);
    }

    /**
     * Creates, reads, updates or deletes invoice objects.
     *
     * @param $params
     * @param array $fields
     * @return bool|mixed|\stdClass
     */
    public function invoices($params, $fields = array()) {
        return $this->data('invoices', $params, $fields);
    }

    /**
     * Creates, reads, updates or deletes payment objects.
     *
     * @param $params
     * @param array $fields
     * @return bool|mixed|\stdClass
     */
    public function payments($params=array(), $fields=array()) {
        if (empty($params['method'])) {
          $params['method'] = 0;
        }
        return $this->data('payments', $params, $fields);
    }

    /**
     * Creates, reads, updates or deletes quote objects.
     *
     * @param $params
     * @param array $fields
     * @return bool|mixed|\stdClass
     */
    public function quotes($params, $fields = array()) {
        return $this->data('quotes', $params, $fields);
    }

    /**
     * Creates, reads, updates or deletes case objects.
     *
     * @param $params
     * @param array $fields
     * @return bool|mixed|\stdClass
     */
    public function cases($params, $fields = array()) {
        return $this->data('cases', $params, $fields);
    }

    /**
     * Helper function for methods using the 'security' endpoint.
     */
    private function security($method, $params = array()) {
        $auth = $this->authenticate();
        if (!$auth) {
            $this->setSecurityToken(null);
            return $auth;
        }

        $params['securityToken'] = $this->getSecurityToken();
        $result = $this->get('security/' . $method . '/', $params);
        if (!empty($result->body)) {
            return json_decode($result->body);
        }
    }

    /**
     * Helper function for methods using the 'resources' endpoint.
     */
    protected function resources($method, $params = array()) {
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
        $result = $this->get('resources/' . $method . '/', $params);
        if (!empty($result->body)) {
            return json_decode($result->body);
        }
    }

    /**
     * Validates a promo code for a given zip/postal ocde and service type.
     *
     * TODO: move to helper class.
     *
     * @param $promo
     * @param $serviceTypeID
     * @param $clientTypeID
     * @param $zoneID
     * @return bool|mixed|\stdClass
     */
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

    /**
     * Validates a zip or postal code.
     *
     * TODO: move to helper class.
     *
     * @param $zip
     * @return bool|mixed|\stdClass
     */
    public function validateZipCode($zip) {
        $params = array(
            'method' => 1,
            'zip' => $zip,
        );
        return $this->availability($params);
    }

    /**
     * Lists avialable times.
     *
     * Todo: move to helper class.
     *
     * @param $params
     * @return bool|mixed|\stdClass
     */
    public function availableTimes($params) {
        $params['method'] = 0;
        return $this->availability($params);
    }

    /**
     * Locks a time.
     *
     * Todo: move to helper class.
     *
     * @param $params
     * @return bool|mixed|\stdClass
     */
    public function lockTimes($params) {
        $params['method'] = 2;
        return $this->availability($params);
    }

    /**
     * Lists availability.
     *
     * @param $params
     * @return bool|mixed|\stdClass
     */
    private function availability($params) {
        return $this->resources('availability', $params);
    }

    /**
     * Lists taxes.
     *
     * @param $zip
     * @return bool|mixed|\stdClass
     */
    public function taxes($zip) {
        $params = array(
            'method' => 0,
            'zipCode' => $zip,
        );
        return $this->resources('taxes', $params);
    }

    /**
     * Lists proiceList objects.
     *
     * @param int $pageNo
     * @param int $pageSize
     * @return bool|mixed|\stdClass
     */
    public function priceLists($pageNo=1, $pageSize=1000) {
        $params = array(
            'pageNo' => $pageNo,
            'pageSize' => $pageSize,
        );
        return $this->resources('priceLists', $params);
    }

    /**
     * Lists priceBlock objects.
     *
     * @param null $priceListID
     * @param null $pageNo
     * @param int $pageSize
     * @return bool|mixed|\stdClass
     */
    public function priceBlocks($priceListID = NULL, $pageNo=NULL, $pageSize=500) {
        $params = array(
            'objectID' => $priceListID,
            'pageNo' => $pageNo,
            'pageSize' => $pageSize,
        );
        return $this->resources('priceBlocks', $params);
    }

    /**
     * Lists priceItem objects.
     *
     * @param null $priceListID
     * @param null $priceBlockID
     * @param null $pageNo
     * @param null $pageSize
     * @return bool|mixed|\stdClass
     */
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

    /**
     * Lists service types.
     *
     * @return bool|mixed|\stdClass
     */
    public function serviceTypes() {
        return $this->resources('serviceTypes');
    }

    /**
     * Lists servicable postal codes.
     */
    public function zips($params = array()) {
        return $this->resources('zips', $params);
    }

    /**
     * Helper function for methods using the 'system' endpoint.
     *
     * @param $method
     * @param array $params
     * @return bool|mixed|\stdClass
     */
    protected function system($method, $params = array()) {
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
        $result = $this->get('system/' . $method . '/', $params);
        if (!empty($result->body)) {
            return json_decode($result->body);
        }
    }

    /**
     * Lists system movules.
     *
     * @return bool|mixed|\stdClass
     */
    public function modules() {
        return $this->system('modules');
    }

    /**
     * Lists system database.
     *
     * @return bool|mixed|\stdClass
     */
    public function database() {
        return $this->system('database');
    }

    /**
     * Lists forms.
     *
     * @param null $moduleID
     * @param null $formID
     * @return bool|mixed|\stdClass
     */
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

    /**
     * Lists system objects.
     *
     * @param null $objectID
     * @param array $params
     * @return bool|mixed|\stdClass
     */
    public function objects($objectID = null, $params = array()) {
        if (empty($params) && !empty($objectID)) {
            $params['method'] = 2;
        }
        $params['objectID'] = $objectID;
        return $this->system('objects', $params);
    }

    /**
     * Lists franchises.
     *
     * @return bool|mixed|\stdClass
     */
    public function franchises() {
        return $this->security('franchises');
    }

    /**
     * Sets franchise.
     *
     * @param null $franchiseID
     * @return bool|mixed|\stdClass
     */
    public function session($franchiseID = NULL) {
        if (isset($franchiseID)) {
            $params = array(
                'franchiseID' => $franchiseID,
            );
        }
        return $this->security('session', $params);
    }

    /**
     * Logs out the authenticated user and invalidates security token.
     *
     * @return bool|mixed|\stdClass
     */
    public function logout() {
        return $this->security('logout');
    }

    /**
     * Lists groups.
     *
     * @return bool|mixed|\stdClass
     */
    public function groups() {
        return $this->security('groups');
    }

    /**
     * Lists offices.
     *
     * @return bool|mixed|\stdClass
     */
    public function offices() {
        return $this->security('offices');
    }

    /**
     * Lists routes.
     *
     * @return bool|mixed|\stdClass
     */
    public function routes() {
        return $this->security('routes');
    }

    /**
     * Lists flags.
     *
     * @return bool|mixed|\stdClass
     */
    public function flags() {
        return $this->security('flags');
    }

    /**
     * Sets a debug option to show information about the request.
     *
     * @param $value - bitwise operator
     *
     *   VONIGO_DEBUG_NONE   - turn off debugging
     *   VONIGO_DEBUG        - turn on debugging
     *   VONIGO_DEBUG_HIGH   - turn on detailed debugging
     *   VONIGO_DEBUG_SCREEN - print errors to the screen
     *   VONIGO_DEBUG_ALL    - print errors to the logger and screen
     *
     */
    public function setDebug($value) {
        $this->debug = $value;
    }

    /**
     * Displays debug information.
     *
     * @param $info
     */
    protected function showDebug($info) {
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
     *
     * TODO: move to a helper class
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
     * Pages through a multi-page result set.
     *
     * TODO: move to a helper class
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
     *
     *  TODO: move to a helper class
     */
    public static function isTrue($value) {
        if (strtolower($value) === 'true' || $value == 1)  {
            return true;
        }
        if (strtolower($value) === 'false' || $value == 0) {
            return false;
        }
    }

}
