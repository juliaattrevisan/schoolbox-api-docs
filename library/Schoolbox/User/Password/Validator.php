<?php

/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\User\Password;

use Alaress\Password\Service;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\User;
use Schoolbox\UserMapper;

/**
 * Password validator
 *
 * @package Schoolbox\User\Password
 */
class Validator
{
    /**
     * @var UserMapper
     */
    protected $userMapper;

    /**
     * @var Service
     */
    protected $passwordService;

    /**
     * @var int
     */
    protected $cost;

    /**
     * Constructor

     *
*@param UserMapper    $userMapper
     * @param Service $passwordService
     */
    public function __construct(UserMapper $userMapper, Service $passwordService)
    {
        $this->userMapper      = $userMapper;
        $this->passwordService = $passwordService;
    }

    /**
     * Checks whether the username matches the password.
     *
     * This has the side effect of performing rehashing of passwords - I don't
     * really want to introduce side effects here, but am doing so as
     * password verification and generation is intentionally computationally
     * expensive.
     *
     * @param  string $username
     * @param  string $password
     * @return bool
     */
    public function checkPassword($username, $password)
    {
        $user = $this->userMapper->findByColumnList(['username' => $username], ['deleted' => false])->first();
        if (!$user instanceof User) {
            return false;
        }

        // If user is disabled, they will never get in.
        // I still want to rehash their password though.
        $enabled = $user->getEnabled();
        $hash    = $user->getPassword();

        // Matches new-style password
        if ($this->passwordService->verify($password, $hash)) {
            // Maybe requires rehash
            if ($this->passwordService->needsRehash($hash)) {
                $this->saveUserPassword($user, $password);
            }
            return $enabled;
        }

        // Matches old-style password
        if ($this->userMapper->hashPassword($password) == $hash) {
            // Definitely requires rehash
            $this->saveUserPassword($user, $password);
            return $enabled;
        }

        // Doesn't match either
        return false;
    }

    /**
     * Saves a user password.
     *
     * @param User   $user
     * @param string $password
     */
    protected function saveUserPassword(User $user, $password)
    {
        $user
            ->setRetrievablePassword($password)
            ->setPassword($this->passwordService->hash($password));
        $this->userMapper->save($user);
    }
}
