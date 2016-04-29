# vonigo-php
A PHP library for the Vonigo API

by Caleb Tucker-Raymond http://calebtr.com

# Requirements

Vonigo PHP requires the cURL php library.


# Usage

    <?php

    require_once('Vonigo.php');
    require_once('VonigoInterface.php');
    require_once('VonigoSimple.php');

    $settings = array(
        'username' => 'username',
        'password' => 'password',
        'company' => 'company',
        'base_url' => 'https://url', // no trailing slash
    );

    $co = new VonigoSimple($settings);
    $result = $co->franchises();
    print_r($result);

    ?>


# VonigoSimple

The VonigoSimple class extends the Vonigo class and implements the VonigoInterface interface.

A future version of this library could use a different HTTP handler but still utilize the VonigoInterface interface.  


# VonigoRecord

The VonigoRecord class provides methods for consistently creating, reading, updating and deleting records.
