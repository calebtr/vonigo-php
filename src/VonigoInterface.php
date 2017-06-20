<?php

namespace VonigoPHP;

interface VonigoInterface {

    /**
     *  @param $objectID = objectID of a client record
     **/
    public function activateClient($objectID);

    public function createClient($fields);

    /**
     * @param $objectID
     * @return mixed
     */
    public function deactivateClient($objectID);

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
