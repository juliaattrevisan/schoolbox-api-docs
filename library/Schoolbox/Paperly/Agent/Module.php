<?php

/**
 * @copyright Copyright 2024, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\Paperly\Agent;

use Alaress\Authorizer\Rule\IsConfigSetTo;
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
        return new Controller(
            $this->getConfig()->get(ConfigMapper::NAME_PAPERLY_AGENT_IP_WHITELIST) ?? '',
            $this->getConfig()->get(ConfigMapper::NAME_PAPERLY_AGENT_SECRET) ?? '',
            // This should use a differently configured external DB:

            $this->injector->get('databaseExternal'),
        );
    }
}
