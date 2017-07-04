<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\MobileApp;

interface IServiceManager
{

    /**
     * @param int $userId
     * @param array $data
     * @return void
     */
    public function push($userId, $data);

}