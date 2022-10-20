<?php

/**
 * @copyright Copyright 2022, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Notif\MQ;

use Schoolbox\IConfigLoader;

/**
 * Factory for building an AMQP connector from configuration, settings and
 * connection options.
 */
class ConnectorFactory
{
    /**
     * Constructor
     *
     * @param IConfigLoader $config
     */
    public function __construct(private IConfigLoader $config)
    {
    }

    /**
     * Build a connector from the given connection options.
     *
     * @param array $settings
     * @param array $connectionOptions
     * @return Connector
     */
    public function build(array $settings = [], array $connectionOptions = []): Connector
    {
        $baseConnectionOptions = [
            Connector::AMQP_CONNECTION_OPTION_HOST     => $this->config->get('amqp_host'),
            Connector::AMQP_CONNECTION_OPTION_PORT     => $this->config->get('amqp_port'),
            Connector::AMQP_CONNECTION_OPTION_USER     => $this->config->get('amqp_user'),
            Connector::AMQP_CONNECTION_OPTION_PASSWORD => $this->config->get('amqp_pass'),
            Connector::AMQP_CONNECTION_OPTION_VHOST    => $this->config->get('amqp_vhost'),
        ];
        return new Connector(
            $settings,
            $connectionOptions + $baseConnectionOptions,
        );
    }
}
