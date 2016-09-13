<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Alaress\Async\Notification\Cloud;


use Psr\Log\LoggerInterface;

interface ServiceInterface
{

    /**
     * @return string
     */
    public function getProviderName();


    /**
     * @param array $tokens
     * @param array $data
     * @param int   $unreadCount
     * @return bool
     */
    public function send(array $tokens, array $data, $unreadCount = 0);


    /**
     * @param LoggerInterface $log
     * @return self
     */
    public function setLog(LoggerInterface $log);

}
