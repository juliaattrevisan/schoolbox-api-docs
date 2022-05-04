<?php

use Alaress\Database\Connector\IConnector;
use Schoolbox\Database\Factory;

/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

class External_Synergetic_Ping implements External_PingInterface
{
    use External_MssqlPingTrait;

    /**
     * @var IConnector|null
     */
    private $database;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->database = Factory::getInstanceExternal();
    }

    /**
     * @inheritdoc
     */
    public function isResponsive(): bool
    {
        return $this->database !== null;
    }

    /**
     * @inheritdoc
     */
    public function getVersion(): string
    {
        if (!$this->database) {
            return '';
        }
        try {
            return $this->database->getOne("SELECT TOP 1 [VALUE] FROM Config WHERE Key1 = 'System' AND Key2 = 'Network' AND Key3 = 'Version' AND Key4 = 'DataStructure'");
        } catch (\Exception $e) {
            // If all else fails we will default to reporting version 67 as this is the
            // baseline functionality level for the Synergetic connector
            return '67';
        }
    }
}
