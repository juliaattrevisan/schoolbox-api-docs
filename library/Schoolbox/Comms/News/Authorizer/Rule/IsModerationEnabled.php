<?php

/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Comms\News\Authorizer\Rule;

use Alaress\Authorizer\Failure\Unauthorized;
use Alaress\Authorizer\Rule\IAuthorizationRule;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\IConfigLoader;
use Schoolbox\User;

class IsModerationEnabled implements IAuthorizationRule
{

    /** @var IConfigLoader */
    private $config;


    /**
     * @param IConfigLoader $config
     */
    public function __construct(IConfigLoader $config)
    {
        $this->config = $config;
    }


    /**
     * Checks that global moderation is currently enabled
     *
     * @inheritdoc
     * @throws \Alaress\Database\Predicate\InvalidColumnException
     */
    public function authorize(array $params = [], User $user = null): void
    {
        if (!$this->config->get(ConfigMapper::_GLOBAL_MODERATION)) {
            throw new Unauthorized('Global moderation is disabled');
        }
    }
}
