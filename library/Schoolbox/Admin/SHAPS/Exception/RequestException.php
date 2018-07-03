<?php

namespace Schoolbox\Admin\SHAPS\Exception;

class RequestException extends \Guzzle\Http\Exception\RequestException {

    public function __construct(string $message = null) {
        $defaultMessage = \Schoolbox\Error\Mapper::getInstance()::ERROR_SHAPS_GENERIC_CONNECTION_FAILURE;
        $this->$message = $message ? $defaultMessage . ": " . $message : $defaultMessage;
    }
}