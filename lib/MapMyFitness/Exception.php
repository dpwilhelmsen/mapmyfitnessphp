<?php
/**
 * Fitbit API communication exception
 *
 */

namespace MapMyFitness;

class Exception extends \Exception
{
    public $mmfMessage = '';
    public $httpcode;

    public function __construct($code, $mmfMessage = null, $message = null)
    {
        $this->mmfMessage = $mmfMessage;
        $this->httpcode = $code;

        if (isset($mmfMessage) && !isset($message))
            $message = $mmfMessage;

        try {
            $code = (int)$code;
        } catch (Exception $E) {
            $code = 0;
        }

        parent::__construct($message, $code);
    }
}