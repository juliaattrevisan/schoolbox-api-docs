<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2016, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Alaress\Web\Controller;


interface IExceptionGenerator
{

    /**
     * @param int        $errorCode
     * @param \Exception $previousException
     * @param string     $additionalInfo
     * @return Exception
     */
    public function generateException($errorCode, \Exception $previousException = null, $additionalInfo = '');

}
