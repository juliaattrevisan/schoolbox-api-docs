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
use Schoolbox\Permission\Access\UserAccessMapper;
use Schoolbox\User;
use User\Logger;

class Controller extends Base
{
    /**
     * @var UserAccessMapper
     */
    protected $userAccessMapper;

    /**
     * @var External_User
     */
    private $externalHandler;

    /** @var Logger\Mapper */
    private $assumeUserLogMapper;

    /**
     * @param UserAccessMapper $userAccessMapper
     * @param External_User    $externalHandler
     * @param Logger\Mapper    $assumeUserLogMapper
     */
    public function __construct(UserAccessMapper $userAccessMapper, External_User $externalHandler, Logger\Mapper $assumeUserLogMapper)
    {
        $this->userAccessMapper    = $userAccessMapper;
        $this->externalHandler     = $externalHandler;
        $this->assumeUserLogMapper = $assumeUserLogMapper;
    }


    /**
     * Function to transfer to another user (impersonate), stores your previous session for later return
     *
     * @api /user/jwt
     * @param User $user
     * @throws \PDOException
     * @throws \Schoolbox\Mapper\Exception
     * @throws \UnexpectedValueException
     */
    public function getJwtForSelf()
    {
        $this->getJwtForUser($this->getUser());
    }

    public function getJwtForUser(User $user): void
    {
        // JSON response:
        // generate from JWT generator
    }
}
