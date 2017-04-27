<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Alaress\Async\Broker;

/**
 * The resolver is a helper class for acknowledging or rejecting a payload as handled or not handled (respectively).
 *
 * @package Alaress\Async\Broker
 */
interface IResolver
{

    /**
     * @throws DuplicateResolutionException
     * @return void
     */
    public function acknowledge();


    /**
     * @throws DuplicateResolutionException
     * @return void
     */
    public function reject();

}