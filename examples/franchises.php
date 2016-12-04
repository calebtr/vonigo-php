<?php

/**
 * @file examples/franchises.php - iterate through franchises
 */

require __DIR__ . '/../vendor/autoload.php';

// create a settings array
$settings = array(
    'username' => 'username',
    'password' => 'password',
    'company' => 'company',
    'base_url' => 'https://example.vonigo.com/api/v1', // no trailing slash
);

// create the Vonigo object
$co = new VonigoPHP\Vonigo($settings);

// authenticate
$auth = $co->authenticate();

// make sure we are authorized before continuing
if (!empty($auth->errNo)) {
    echo 'could not authenticate these credentials' . PHP_EOL;
    die;
}

// request a franchise list
$franchises = $co->franchises();

// iterate through franchises
foreach ($franchises->Franchises as $franchise) {

    // set the session to use this franchise
    $co->session($franchise->franchiseID);

    // do more stuff
}
