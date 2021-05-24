<?php

/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\InlineGrader\Kami\Authentication;

use Alaress\Authorizer\Failure\Unauthorized;
use Alaress\Authorizer\Rule\IAuthorizationRule;
use Schoolbox\Learning\InlineGrader\Kami;
use Schoolbox\User;

class IsEnabled implements IAuthorizationRule
{

    /** @var Kami\IConnector */
    private $kamiConnector;


    /**
     * @param Kami\IConnector $kamiConnector
     */
    public function __construct(Kami\IConnector $kamiConnector)
    {
        $this->kamiConnector = $kamiConnector;
    }


    /**
     * @param array     $params
     * @param User|null $user
     * @return bool
     * @throws Unauthorized
     */
    public function authorize(array $params = [], User $user = null): bool
    {
        if (!$this->kamiConnector->isEnabled()) {
            throw new Unauthorized('Kami integration is not enabled');
        }
        return true;
    }
}
