<?php
/**
 * Base external connector class
 *
 * @author    Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright Copyright 2017, Alaress Pty Ltd
 * @link      http://www.alaress.com.au
 */

namespace Schoolbox\External\Connector;

use Alaress\Database\Connector\IConnector;
use Schoolbox\IConfigLoader;

abstract class Base
{
    /** @var null|IConnector */
    protected $databaseExternal;

    /** @var IConfigLoader */
    protected $config;

    /**
     * Constructor
     *
     * @param IConfigLoader $config
     * @param null|IConnector $databaseExternal External database connector(s)
     */
    public function __construct(IConfigLoader $config, ?IConnector $databaseExternal)
    {
        $this->config = $config;
        $this->databaseExternal = $databaseExternal;
    }
}
