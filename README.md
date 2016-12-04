# vonigo-php
A PHP library for the Vonigo API

by Caleb Tucker-Raymond http://calebtr.com

# Requirements

Vonigo PHP requires the cURL php library.

# Install with composer

    {
        "require": {
            "calebtr/vonigo-php": "dev-master"
        }
    }

# Examples

Examples are included in the examples directory.


# VonigoSimple

The VonigoSimple class extends the Vonigo class and implements the VonigoInterface interface.

A future version of this library could use a different HTTP handler but still utilize the VonigoInterface interface.  


# VonigoRecord

The VonigoRecord class provides methods for consistently creating, reading, updating and deleting records.
