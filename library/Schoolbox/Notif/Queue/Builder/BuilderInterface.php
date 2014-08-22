<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Notif\Queue\Builder;


use Alaress\Async\Queue\QueueInterface;
use Schoolbox\User;

interface BuilderInterface
{

    /**
     * @param string $label
     * @param int    $id
     * @return string
     */
    public function createQueueName($label, $id);


    /**
     * @param Plan $plan
     * @param User $user
     * @return QueueInterface[]
     */
    public function generateQueues(Plan $plan, User $user);


    /**
     * @param Plan $plan
     * @param int  $userId
     * @return array
     */
    public function generateQueueNames(Plan $plan, $userId);

}