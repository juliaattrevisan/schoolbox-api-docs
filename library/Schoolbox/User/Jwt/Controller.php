<?php
/**
 *
 * @author      James Leckie <jleckie@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 */

declare(strict_types=1);

namespace Schoolbox\User\Jwt;

use Alaress\Web\Controller\Base;
use External_User;
use Schoolbox\API\Token\Jwt;
use Schoolbox\Permission\Access\UserAccessMapper;
use Schoolbox\User;
use User\Logger;

class Controller extends Base
{
    /**
     * @var Jwt
     */
    private $jwtBuilder;


    /**
     * Constructor
     *
     * @param Jwt $jwtBuilder
     */
    public function __construct(Jwt $jwtBuilder)
    {
        $this->jwtBuilder = $jwtBuilder;
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
                    'token' => $this->jwtBuilder->generate($user)
                ]
            )
        );
    }
}
