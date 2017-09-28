<?php

namespace VonigoPHP;

/**
 * File
 *
 * handles some common functions related to Vonigo records
 */

abstract class VonigoRecord {

	/**
	 * @var string | bool| int
	 * The value of this record's isActive property
	 */
	protected $active;

	/**
	 * @var Vonigo
	 *
	 * The Vonigo connection object
	 */
	public $co;

	/**
	 * @var array
	 *
	 * Validation errors related to this record.
	 */
	public $errors = array();

	/**
	 * @var array
	 * A simple array of fields related to this record
	 */
	public $fields;

	/**
	 * @var int
	 *
	 * The franchise ID associated with this record
	 */
	public $franchiseID;

	/**
	 * @var array
	 *
	 * An array that maps the simple array of fields ($this->fields) to a Vonigo Fields array
	 */
	public $map;

	/**
	 * @var string
	 *
	 * The Vonigo method used to create, read, update or delete this record
	 */
	protected $method;

	/**
	 * @var int
	 *
	 * The Vonigo objectID of this record
	 */
	public $objectID;

	/**
	 * @var int
	 *
	 * The Vonigo objectTypeID associated with this record
	 */
	public $objectTypeID;

	/**
	 * @var array
	 *
	 * An array of parameters to be sent when creating, reading, updating or deleting this record
	 */
	public $params;

	/**
	 * @var string The name of the Vonigo result property associated with this
	 * type of record when creating, reading or updating this record.
	 */
	protected $propertySingle;

	/**
	 * @var string
	 *
	 * The name of the Vonigo result property associated with this type of
	 * record when requesting a list of records.
	 */
	protected $property;

	/**
	 * @var array
	 *
	 * An array of Vonigo records that are related to this one
	 */
	public $relations;

	/**
	 * @var
	 *
	 * The result of the most recent request made to Vonigo.
	 */
	public $request;

	/**
	 * "Constants" representing Vonigo methods for creating, reading,
	 * updating and deleting Vonigo records.
	 */
	protected $createMethod = 3;
	protected $readMethod = 1;
	protected $updateMethod = 2;
	protected $deleteMethod = 4;

	/**
	 * reset object ID, request and errors when object is cloned
	 */
	public function __clone() {
		$this->objectID = null;
		$this->request = null;
		$this->errors = array();
	}

	/**
	 * Submit the request to Vonigo
	 *
	 * @param $methodID
	 *
	 * @return bool|mixed
	 */
	protected function request($methodID) {

		// make sure that the method is set and we have a connection object
		if (empty($this->method) || empty($this->co)) {
			echo 'boo!';
			return false;
		}

		// authenticate
		$auth = $this->co->authenticate();
		if (!$auth) {
			return false;
		}

		// set the franchise for the next request
		$this->setFranchise();

		// set the method parameter and make the request
		$this->params['method'] = $methodID;
		$this->request = call_user_func(array($this->co, $this->method), $this->params, $this->prepareFields());

		// return false if there was an error
		if ($this->request->errNo != 0) {
			$this->errors = $this->request;
			return false;
		}

		return $this->request;
	}

	public function create() {
		$request = $this->request($this->createMethod);
		if (!empty($request->{$this->propertySingle}->objectID)) {
			$this->objectID = $request->{$this->propertySingle}->objectID;
		}
		return $request;
	}

	public function read() {
		if (empty($this->objectID)) {
			return false;
		}
		$this->params['objectID'] = $this->objectID;
		$request = $this->request($this->readMethod);
		$this->fields = $this->mapVonigoFields($request);
		if (!empty($request->Relations)) {
			$this->relations = $request->Relations;
		}
		if (!empty($request->{$this->propertySingle}->isActive)) {
			$this->active = $request->{$this->propertySingle}->isActive;
		}
		return $request;
	}

	public function update() {
		$this->params['objectID'] = $this->objectID;
		return $this->request($this->updateMethod);
	}

	public function delete() {
		$this->params = array('objectID' => $this->objectID);
		return $this->request($this->deleteMethod);
	}

	public function addRelation($type, $objectID) {
		$newRelation = (object) array('objectTypeID' => $type, 'objectID' => $objectID);
		$this->relations[] = $newRelation;
	}

	public function getRelations($type) {
		$result = array();
		if (!empty($this->relations)) {
			foreach ($this->relations as $relation) {
				if ($relation->objectTypeID == $type) {
					$result[] = $relation->objectID;
				}
			}
		}
		return array_unique($result);
	}

	public function getOption($fieldID, $optionID) {
		$object = $this->co->objects($this->objectTypeID);

                if (!empty($object->Options)) {
    			foreach ($object->Options as $option) {
				if ($option->fieldID == $fieldID && $option->optionID == $optionID) {
					return $option->name;
				}
			}
		}
	}

	public function isActive() {
		return Vonigo::isTrue($this->active);
	}

	/**
	 * Sets the active value of the record.
	 *
	 * @param $value
	 */
	public function setActive($value) {
		$this->active = $value;
	}

	/**
	 * Helper function maps some fields.
	 */
	protected function mapFields() {
		$fields = new \stdClass;
		foreach ($this->map as $v => $i) {
			if (!empty($this->fields->{$v}->fieldValue)) {
				$fields->{$i} = $this->fields->{$v}->fieldValue;
			}
		}
		return $fields;
	}

	public function mapVonigoFields($object) {
		$reverseMap = $this->reverseMap();
		$return = new \stdClass;
		if (!empty($object->Fields)) {
			foreach ( $object->Fields as $field ) {
				$fieldKey = 'field' . $field->fieldID;
				$return->{$fieldKey} = $field;
				if (!empty($reverseMap[$fieldKey])) {
					$return->{$reverseMap[$fieldKey]} = $field;
				}
			}
		}
		return $return;
	}

	public function prepareFields() {
		$vonigoFields = array();

		foreach ($this->map as $field => $info) {
			$fieldObj = new \stdClass;
			$fieldObj->fieldID = $this->map[$field]['fieldID'];
			if (!empty($this->map[$field]['optionID'])) {
				$fieldObj->optionID = $this->map[$field]['optionID'];
			}
			if (!empty($this->map[$field]['fieldValue'])) {
				$fieldObj->fieldValue = $this->map[$field]['fieldValue'];
			}
			if (!empty($this->fields[$field])) {
				$fieldObj->fieldValue = $this->fields[$field];
			}
			$vonigoFields[] = $fieldObj;
		}
		return $vonigoFields;
	}

	public function theme() {
		return print_r($this->fields, true);
	}

	protected function reverseMap() {
		$fields = array();
		foreach ($this->map as $fieldName => $info) {
			$fieldKey = 'field' . $info['fieldID'];
			$fields[$fieldKey] = $fieldName;
		}
		return $fields;
	}

	public function setFields($fields) {
		$this->fields = $fields;
	}

	/**
	 * Sets the franchise in the Vonigo session
	 */
	public function setFranchise() {
		$session = (object) array('errNo' => -1);
		if (!empty($this->franchiseID)) {
			$session = $this->co->session($this->franchiseID);
		}
		else {
			throw new \Exception('No franchise set: ' . PHP_EOL . print_r($this, true));
		}
		if ($session->errNo == 0) {
			return $session;
		}
		else {
			throw new \Exception('Could not set franchise (' . $this->franchiseID . '):' . PHP_EOL );
		}
	}

	/**
	 * Returns true if no errors have found on this object.
	 *
	 * @return bool
	 */
	public function valid() {
		return empty($this->errors);
	}

	/**
	 * Validates that an integer is divisible by another integer
	 */
	protected function validateDivisible($numerator, $divisor) {
		$mod = (int) $numerator % $divisor;
		if ($mod === 0 && (int) $numerator > 0) {
			return $numerator;
		}
		return (object) array('error' => '%s field is not a valid %s');
	}

	/**
	 * Validates an email address.
	 */
	protected function validateEmail($value) {
		// first check that it is a valid email
		$value = filter_var($value, FILTER_VALIDATE_EMAIL);
		if ($value) {
			// remove illegal characters
			$sanitized = filter_var( $value, FILTER_SANITIZE_EMAIL );
			if ($sanitized == $value) {
				return $value;
			}
		}
		return (object) array('error' => '%s field is not a valid email address');
	}

	abstract public function validate();

	/**
	 * Validates that a value is not empty
	 *
	 * @param $value
	 *
	 * @return object $value or object
	 */
	protected function validateNotEmpty($value) {
		if (!empty($value)) {
			return $value;
		}
		return (object) array('error' => '%s field is required');
	}

	/**
	 * Validates that a value has a valid option for a specific Vonigo object
	 * type and field.
	 *
	 * @param int $objectID - the object type ID
	 * @param int $fieldID - the field ID
	 * @param string $value - the value
	 *
	 * @return mixed; object if there is an error, integer if there is not
	 */
	protected function validateOption($objectID, $fieldID, $value) {
		$object = $this->co->objects($objectID);
        if (empty($object->Options)) {
            return (object) array('error' => 'could not look up option in vonigo object: ' . print_r($object, true));
        }

		foreach ($object->Options as $option) {
			if ($option->fieldID == $fieldID && $option->name == $value) {
				return $option->optionID;
			}
		}
		return (object) array('error' => '%s is not a valid option for %s');
	}

	/**
	 * Returns phone number in \d[10] format if a phone number string contains 10 digits.
	 */
	protected function validatePhone($phone) {
		$phone = filter_var(str_replace(array('+', '-'), '', $phone), FILTER_SANITIZE_NUMBER_INT);
		if (strlen($phone) == 10) {
			return $phone;
		}
		return (object) array('error' => '%s field must have 10 digits');
	}

	/**
	 * Returns an integer if it is positive or an error if it is not
	 */
	protected function validatePositive($value) {
		if ($value > 0) {
			return $value;
		}
		return (object) array('error' => '%s field must be bigger than zero');
	}

}
