<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Alaress\Async\Notification\Cloud\Apple;

use Alaress\Async\Notification\Cloud\ServiceInterface;


/**
 * Apple Push Notification Service
 *
 * Class APNS
 *
 * @package Alaress\Async\Notification\Cloud\Apple
 */
class APNS implements ServiceInterface
{

    /**
     * @return string
     */
    public function getProviderName()
    {
        return 'apns';
    }


    /**
     * @param array $tokens
     * @param array $data
     * @return bool
     */
    public function send(array $tokens, array $data)
    {
        // TODO: Implement send() method.
    }

} 