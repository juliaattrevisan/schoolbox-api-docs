<?php

/**
 * Base mapper specific to objects stored outside of the Schoolbox database,
 * such as timetables, subjects and class lists.
 *
 * @author      Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\External;

use Schoolbox;

abstract class Mapper extends Schoolbox\Mapper
{

    /**
     * Interface for connecting to external database.
     *
     * @var \External_User()
     */
    protected $external_user;

    /**
     * Creates mapper, and sets up external database interface.
     */
    public function __construct()
    {
        parent::__construct();
        $this->external_user = new \External_User();
    }
}
