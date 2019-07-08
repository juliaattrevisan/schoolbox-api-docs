<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Synchronise\Synergetic\Diocesan\Hydrator;


use Synchronise_Base_Hydrator_UnsetOnUpdate;

class UnsetOnUpdate extends Synchronise_Base_Hydrator_UnsetOnUpdate
{
    /** @var array */
    protected $clearFields = array('su', 'enabled');
} 
