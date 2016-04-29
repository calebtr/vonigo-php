# vonigo-php
A PHP library for the Vonigo API


# Requirements

Vonigo PHP requires the cURL php library.


# Usage

    $settings = array($username, $password, $company, $url);
    $co = new VonigoSimple($settings);
    $result = $co->frachises();
    print_r($result);


# VonigoSimple

The VonigoSimple class extends the Vonigo class and implements the VonigoInterface interface.

A future version of this library could use a different HTTP handler but still utilize the VonigoInterface interface.  


# VonigoRecord

The VonigoRecord class provides methods for consistently creating, reading, updating and deleting records.
