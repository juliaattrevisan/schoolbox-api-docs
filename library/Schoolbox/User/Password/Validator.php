<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\User\Password;

use Alaress\Password\Password;
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
     * @var Password
     */
    protected $passwordService;

    /**
     * @var int
     */
    protected $cost;

    /**
     * Constructor
     *
     * @param UserMapper $userMapper
     * @param Password   $passwordService
     */
    public function __construct(UserMapper $userMapper, Password $passwordService)
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
        try {
            $user = $this->userMapper->findOneByColumn('username', $username);
        } catch (MapperException $e) {
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
        $user->setPassword($this->passwordService->hash($password));
        $this->userMapper->save($user);
    }
}
