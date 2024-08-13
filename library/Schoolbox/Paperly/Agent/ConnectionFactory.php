<?php

/**
 * @copyright Copyright 2024, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\Paperly\Agent;

use Alaress\Database\Connector\IConnector;
use Alaress\Database\Factory;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\IConfigLoader;

/**
 * Factory for Paperly's database connection.
 */
class ConnectionFactory
{
    /**
     * Constructor
     *
     * @param Factory       $factory
     * @param IConfigLoader $config
     */
    public function __construct(private Factory $factory, private IConfigLoader $config)
    {
    }

    /**
     * Build the database connection.
     *
     * This uses overridden database host/name/user/password settings if
     * provided, otherwise it uses the default settings.
     *
     * @return IConnector
     */
    public function build(): IConnector
    {
        $connector = $this->factory->createSqlsrv();
        $connector->connect(
            $this->config->get(ConfigMapper::NAME_PAPERLY_AGENT_EXTERNAL_DB_HOST_OVERRIDE) ?: $this->config->get(ConfigMapper::NAME_EXTERNAL_DB_HOST),
            $this->config->get(ConfigMapper::NAME_PAPERLY_AGENT_EXTERNAL_DB_NAME_OVERRIDE) ?: $this->config->get(ConfigMapper::NAME_EXTERNAL_DB_NAME),
            $this->config->get(ConfigMapper::NAME_PAPERLY_AGENT_EXTERNAL_DB_USER_OVERRIDE) ?: $this->config->get(ConfigMapper::NAME_EXTERNAL_DB_USER),
            $this->config->get(ConfigMapper::NAME_PAPERLY_AGENT_EXTERNAL_DB_PASSWORD_OVERRIDE) ?: $this->config->get(ConfigMapper::NAME_EXTERNAL_DB_PASSWORD),
            ['Encrypt' => $this->config->get(ConfigMapper::NAME_EXTERNAL_DB_ENCRYPTION)],
        );
        return $connector;
    }
}
