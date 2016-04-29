<?php

interface VonigoInterface {

    /**
     *  @param $objectID = objectID of a client record
     **/
    public function activateClient($objectID);

    /**
     * @param $objectID
     * @return mixed
     */
    public function activateContact2($objectID);

    public function createClient($fields);

    /**
     * @param $objectID
     * @return mixed
     */
    public function deactivateClient($objectID);

    /**
     * @param $objectID
     * @return mixed
     */
    public function deactivateContact2($objectID);

    /**
     * @param $workorderID
     * @return mixed
     */
    public function getWorkorderCharges($workorderID);

    /**
     * @param $workorderID
     * @return mixed
     */
    public function getCharge($chargeID);

    /**
     * @param $clientID
     * @return mixed
     */
    public function getClient($clientID);

    /**
     * @param $contactID
     * @return mixed
     */
    public function getContact($contactID);

    /**
     * @param $contactID
     * @return mixed
     */
    public function getContact2($contactID);

    /**
     * @param $locationID
     * @return mixed
     */
    public function getLocation($locationID);

    /**
     * @param $quoteID
     * @return mixed
     */
    public function getQuote($quoteID);

    public function getServiceTypes();

    /**
     * @param $workorderID
     * @return mixed
     */
    public function getWorkOrder($workorderID);

    public function updateClient($clientID, $fields);
}

?>