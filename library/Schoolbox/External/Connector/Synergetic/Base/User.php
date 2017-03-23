<?php
/**
 * Synergetic implementation of user connector
 *
 * @author    Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright Copyright 2017, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\External\Connector\Synergetic\Base;

use Alaress\Database\Connector\IConnector;
use Schoolbox\External\Connector\UserInterface;
use Schoolbox\IConfigLoader;

class User implements UserInterface
{
    /**
     * @var IConnector
     */
    protected $databaseExternal;

    /**
     * @var IConfigLoader
     */
    protected $config;

    /**
     * Constructor
     */
    public function __construct()
    {
        // Grab a connection to the external database

        var_dump(debug_backtrace());
        exit;
    }
}
