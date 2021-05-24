<?php

/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\User\Token;

use Alaress\Web\Controller\Base;
use Schoolbox\API\Token\Jwt;
use Schoolbox\User;

/**
 * Controller which contains user token related endpoints.
 */
class Controller extends Base
{
    /**
     * @var Jwt
     */
    private $tokenBuilder;


    /**
     * Constructor
     *
     * @param Jwt $tokenBuilder
     */
    public function __construct(Jwt $tokenBuilder)
    {
        $this->tokenBuilder = $tokenBuilder;
    }


    /**
     * Retrieve an auth token for the current user.
     *
     * @api /user/token
     * @since 20.1
     */
    public function getTokenForSelf()
    {
        $this->getTokenForUser($this->getUser());
    }

    /**
     * Retrieve an auth token for a specified user.
     *
     * @api /user/token/{userId}
     * @since 20.1
     * @param User $user
     */
    public function getTokenForUser(User $user): void
    {
        // JSON response:
        // generate from JWT generator
        $this->response->content->set(
            $this->buildJsonOutput(
                [
                    'token' => $this->tokenBuilder->generate($user),
                ]
            )
        );
    }
}
