<?php
/**
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Comms\News\Authorizer\Rule;

use Alaress\Authorizer\Failure\Unauthorized;
use Alaress\Authorizer\Rule\IAuthorizationRule;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\IConfigLoader;
use Schoolbox\User;

class InstructorsCanEditOthersArticles implements IAuthorizationRule
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
     * @inheritdoc
     */
    public function authorize(array $params = [], User $user = null): void
    {
        if (!$this->config->get(ConfigMapper::_NEWS_EDIT_OTHERS)) {
            throw new Unauthorized(ConfigMapper::_NEWS_EDIT_OTHERS . ' is disabled');
        }
    }
}
