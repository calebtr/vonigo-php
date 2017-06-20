<?php

namespace VonigoPHP;

class VonigoSimple extends Vonigo implements VonigoInterface {

    /**
     * @param $objectID = objectID of a record to be deactivated
     * @param method = method to call to deactivate a record
     */
    private function activateRecord($objectID, $method) {
        $params = array(
            'method' => 6,
            'objectID' => $objectID,
        );
        return call_user_func(array($this, $method), $params);
    }

    /**
     * @param $objectID = objectID of a client record
     **/
    public function activateClient($objectID) {
        return $this->activateRecord($objectID, 'clients');
    }

    /**
     *
     */
    public function createClient($fields) {
        return $this->createRecord('clients', $fields);
    }

    /**
     *
     */
    public function createRecord($method, $fields) {
        $params = array('method' => 2);
        return call_user_func(array($this, $method), $params, $fields);
    }

    /**
     * @param $objectID = objectID of a record to be deactivated
     * @param method = method to call to deactivate a record
     */
    private function deactivateRecord($objectID, $method) {
        $params = array(
            'method' => 5,
            'objectID' => $objectID,
        );
        return call_user_func(array($this, $method), $params);
    }

    /**
     * @param $objectID = objectID of a client record
     **/
    public function deactivateClient($objectID) {
        return $this->deactivateRecord($objectID, 'clients');
    }

    /**
     * @param $workorderID = objectID of a workorder record
     */
    public function getWorkorderCharges($workorderID) {
        $params = array(
            'method' => 0,
            'workOrderID' => $workorderID,
        );
        return $this->charges($params);
    }

    public function getCharge($chargeID) {
        return $this->getRecordbyID('charges', $chargeID);
    }

    /**
     * @param $clientID
     */
    public function getClient($clientID) {
        return $this->getRecordbyID('clients', $clientID);
    }

    /**
     * @param $contactID
     * @return mixed
     */
    public function getContact($contactID) {
        return $this->getRecordbyID('contacts', $contactID);
    }

    /**
     * @param $locationID
     */
    public function getLocation($locationID) {
        return $this->getRecordbyID('locations', $locationID);
    }

    /**
     * @param $quoteID
     */
    public function getQuote($quoteID) {
        return $this->getRecordbyID('quotes', $quoteID);
    }

    /**
     * @param $method
     * @param $objectID
     * @return mixed
     */
    private function getRecordByID($method, $objectID) {
        $params = array('method' => 1, 'objectID' => $objectID);
        return call_user_func(array($this, $method), $params);
    }

    /**
     * Returns an associative array of service types for this instance.
     */
    public function getServiceTypes() {
        $serviceTypes = $this->serviceTypes();
        $return = array();
        foreach ($serviceTypes->ServiceTypes as $serviceType) {
            if ($this->isTrue($serviceType->isActive)) {
                $return[$serviceType->serviceTypeID] = $serviceType->serviceType;
            }
        }
        return $return;
    }

    /**
     * @param $workOrderID
     */
    public function getWorkOrder($workOrderID) {
        return $this->getRecordByID('workorders', $workOrderID);
    }

    public function updateClient($clientID, $fields) {
        return $this->updateRecord('clients', $clientID, $fields);
    }

    public function updateRecord($method, $objectID, $fields) {
        $params = array(
            'method' => 2,
            'objectID' => $objectID
        );
        return call_user_func(array($this, $method), $params, $fields);
    }


}
