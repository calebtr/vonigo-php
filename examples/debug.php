<?php

/**
 * @file examples/debug.php - debug a request
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

/**
 * set debug level
 *
 * 0 | VONIGO_DEBUG_NONE   - no debugging (default)
 * 1 | VONIGO_DEBUG        -  debug
 * 2 | VONIGO_DEBUG_HIGH   - more debugging
 * 4 | VONIGO_DEBUG_SCREEN - write logs to screen
 * 7 | VONIGO_DEBUG_ALL    - write logs to error_log() function
 *
 */

// turn on debugging
$co->setDebug(VONIGO_DEBUG_ALL);

// authenticate
$auth = $co->authenticate();

// turn off debugging
$co->setDebug(VONIGO_DEBUG_NONE);

