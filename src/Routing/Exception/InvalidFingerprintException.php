<?php

namespace RestApi\Routing\Exception;

use Cake\Core\Exception\Exception;

class InvalidFingerprintException extends Exception
{

    public function __construct($message = null, $code = 401, $previous = null)
    {
        if (empty($message)) {
            $message = 'Invalid fingerprint.';
        }
        parent::__construct($message, $code, $previous);
    }
}
