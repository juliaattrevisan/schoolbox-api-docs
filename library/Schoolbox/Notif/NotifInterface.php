<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Notif;


use Psr\Log\LoggerInterface;

interface NotifInterface extends LoggerInterface
{

    /**
     * @return string
     */
    public function getTopic();


    /**
     * @param string $topic
     * @return self
     */
    public function setTopic($topic);

}
