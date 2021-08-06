<?php

use Schoolbox\Config\Loader;

/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

class External_Ping implements External_PingInterface
{

    /**
     * @var External_PingInterface
     */
    private $database;

    /**
     * Constructor
     *
     * @throws Exception
     * @param String $databaseName
     */
    public function __construct()
    {
        $this->database = (new External_DatabaseFactory())->buildPingConnector(Loader::getInstance());
    }

    public function isResponsive(): bool
    {
        return $this->database->isResponsive();
    }

    /**
     * @inheritdoc
     */
    public function getVersion(): string
    {
        return $this->database->getVersion();
    }
}
