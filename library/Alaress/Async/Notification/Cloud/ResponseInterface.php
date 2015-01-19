<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Alaress\Async\Notification\Cloud;

use Guzzle\Http\Message\Response as HttpResponse;
use OutOfBoundsException;
use RangeException;


interface ResponseInterface
{

    /**
     * Append an array of tokens and the associated http response
     *
     * @param array        $tokens
     * @param HttpResponse $response
     * @throws OutOfBoundsException  if the API key used was invalid
     * @throws RangeException        if the upstream server needs us to back off
     * @return void
     */
    public function append(array $tokens, HttpResponse $response);


    /**
     * @return int
     */
    public function getSuccessCount();


    /**
     * @return int
     */
    public function getFailureCount();


    /**
     * @return int
     */
    public function getCanonicalIdCount();


    /**
     * @return array
     */
    public function getReplacements();


    /**
     * @return array
     */
    public function getErrors();

}