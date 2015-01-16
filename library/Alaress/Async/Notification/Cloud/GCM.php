<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Alaress\Async\Notification\Cloud;


/**
 * Google Cloud Messaging
 *
 * Class GCM
 *
 * @package Alaress\Async\Notification\Cloud
 */
class GCM implements ServiceInterface
{

    /**
     * @return string
     */
    public function getProviderName()
    {
        return 'gcm';
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