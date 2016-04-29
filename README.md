# vonigo-php
A PHP library for the Vonigo API

# usage

    $settings = array($username, $password, $company, $url);
    $co = new Vonigo($settings);
    $result = $co->frachises();
    print_r($result);
