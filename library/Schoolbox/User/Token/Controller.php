<?php
/**
 *
 * @author      James Leckie <jleckie@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 */

declare(strict_types=1);

namespace Schoolbox\User\Token;

use Alaress\Web\Controller\Base;
use Schoolbox\API\Token\Jwt;
use Schoolbox\User;

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
     * Retrieve a JWT for the current user.
     *
     * @api /user/jwt
     */
    public function getJwtForSelf()
    {
        $this->getJwtForUser($this->getUser());
    }

    /**
     * Retrieve a JWT for a specified user.
     *
     * @api /user/jwt/{userId}
     * @param User $user
     */
    public function getJwtForUser(User $user): void
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
