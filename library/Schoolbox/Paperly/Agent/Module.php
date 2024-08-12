<?php

/**
 * @copyright Copyright 2024, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\Paperly\Agent;

use Alaress\Authorizer\Rule\IsConfigSetTo;
use Alaress\Database\Factory;
use Alaress\Module\Module as BaseModule;
use Alaress\Web\Controller\Base;
use Schoolbox\Config\ConfigMapper;

/**
 * Module for the Paperly agent.
 */
class Module extends BaseModule
{
    /**
     * @inheritdoc
     */
    protected $basename = 'paperlyAgent';

    /**
     * @inheritdoc
     */
    protected function registerRoutes(): void
    {
        // The agent is currently only for Synergetic instances.
        $permission = new IsConfigSetTo(ConfigMapper::NAME_EXTERNAL_TYPE, 'synergetic', $this->getConfig());
        $this->register(
            self::METHOD_POST,
            '',
            'postAgentRequest',
            $permission,
        );
        $this->register(
            self::METHOD_GET,
            '',
            'getAgentRequest',
            $permission,
        );
    }

    /**
     * @inheritdoc
     */
    public function buildController(): Base
    {
        $ipWhitelist = array_filter(
            array_map(
                trim(...),
                explode(',', $this->getConfig()->get(ConfigMapper::NAME_PAPERLY_AGENT_IP_WHITELIST) ?? '')
            ),
        );

        // Create the database connection for the Paperly agent
        $connectorFactory = new Factory();
        $connector        = $connectorFactory->createSqlsrv();
        $connector->connect(
            $this->getConfig()->get(ConfigMapper::NAME_PAPERLY_AGENT_EXTERNAL_DB_HOST_OVERRIDE) ?: $this->getConfig()->get(ConfigMapper::NAME_EXTERNAL_DB_HOST),
            $this->getConfig()->get(ConfigMapper::NAME_PAPERLY_AGENT_EXTERNAL_DB_NAME_OVERRIDE) ?: $this->getConfig()->get(ConfigMapper::NAME_EXTERNAL_DB_NAME),
            $this->getConfig()->get(ConfigMapper::NAME_PAPERLY_AGENT_EXTERNAL_DB_USER_OVERRIDE) ?: $this->getConfig()->get(ConfigMapper::NAME_EXTERNAL_DB_USER),
            $this->getConfig()->get(ConfigMapper::NAME_PAPERLY_AGENT_EXTERNAL_DB_PASSWORD_OVERRIDE) ?: $this->getConfig()->get(ConfigMapper::NAME_EXTERNAL_DB_PASSWORD),
            ['Encrypt' => $this->getConfig()->get(ConfigMapper::NAME_EXTERNAL_DB_ENCRYPTION)],
        );

        return new Controller(
            $ipWhitelist,
            $this->getConfig()->get(ConfigMapper::NAME_PAPERLY_AGENT_SECRET) ?? '',
            $connector,
        );
    }
}
