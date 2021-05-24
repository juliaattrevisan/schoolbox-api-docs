<?php

/**
 * DAV_Error
 *
 * Sabre WebDav implementation throws back HTTP response errors via curl.
 *
 * This interpretation class makes the error messages more meaningful and
 * is usable with Schoolbox error() function
 *
 * @author    Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
class DAV_Error
{
    public static $map = array(
        '200' => 'OK',
        '201' => 'Created',
        '202' => 'Accepted',
        '203' => 'Non-Authoritative Information',
        '204' => 'No Content',
        '205' => 'Reset Content',
        '206' => 'Partial Content',
        '300' => 'Multiple Choices',
        '301' => 'Moved Permanently',
        '302' => 'Found',
        '303' => 'See Other',
        '304' => 'Not Modified',
        '305' => 'Use Proxy',
        '307' => 'Temporary Redirect',
        '400' => 'Bad Request',
        '401' => 'Unauthorized',
        '402' => 'Payment Required',
        '403' => 'Forbidden',
        '404' => 'Not Found',
        '405' => 'Method Not Allowed',
        '406' => 'Not Acceptable',
        '407' => 'Proxy Authentication Required',
        '408' => 'Request Timeout',
        '409' => 'Conflict',
        '410' => 'Gone',
        '411' => 'Length Required',
        '412' => 'Precondition Failed',
        '413' => 'Request Entity Too Large',
        '414' => 'Request-URI Too Large',
        '415' => 'Unsupported Media Type',
        '416' => 'Requested Range Not Satisfiable',
        '417' => 'Expectation Failed',
        '500' => 'Internal Server Error',
        '501' => 'Not Implemented',
        '502' => 'Bad Gateway',
        '503' => 'Service Unavailable',
        '504' => 'Gateway Timeout',
        '505' => 'HTTP Version not supported',
    );

    /**
     * Get the meaning
     *
     * @param string $message
     *
     * @internal param $code
     * @return mixed
     */
    public static function getMeaning($message)
    {
        $found = preg_match('/errorcode (\d+)/', $message, $matches);

        $errorCode = $matches[1];

        if ($found && array_key_exists($errorCode, self::$map)) {
            return $errorCode . ' - ' . self::$map[$errorCode];
        }

        return 'Unspecified error.';
    }
}
