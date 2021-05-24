<?php

/**
 * ValidChangeRequest
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\User\Password\Change;

use Alaress\Password\Service;

class ValidChangeRequest
{

    /**
     * Minimum number of characters in a password
     */
    private const MIN_PASSWORD_LENGTH = 8;


    /**
     * @var Service
     */
    private $passwordService;


    /**
     * ValidChangeRequest constructor.
     *
     * @param Service $passwordService
     */
    public function __construct(Service $passwordService)
    {
        $this->passwordService = $passwordService;
    }


    /**
     * These are the criteria for changing a password
     *
     * - the new password has to match the confirmation password
     * - new password has to be at least MIN_PASSWORD_LENGTH chars long
     * - the existing password has to match the current_password form var
     *
     * @param string $currentPassword
     * @param string $userPassword
     * @param string $newPassword
     * @param string $confirmPassword

     * @return bool
     */
    public function isSatisfiedBy(
        string $currentPassword,
        string $userPassword,
        string $newPassword,
        string $confirmPassword
    ): bool {
        return
            $newPassword === $confirmPassword &&
            mb_strlen($newPassword, 'UTF-8') >= self::MIN_PASSWORD_LENGTH &&
            $this->passwordService->verify($currentPassword, $userPassword);
    }
}
