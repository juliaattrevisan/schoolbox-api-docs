<?php
/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Profile;

use External_UserInterface;
use Schoolbox\IConfigLoader;
use Schoolbox\User;

/**
 * Class ValidEmailVisible
 *
 * Spec pattern to determine email visibility; whether $viewer can see $viewee's email address
 * While it is similar to Detail Visibility, they're not entirely the same
 *
 * @package Schoolbox\Profile
 */
class ValidEmailVisible
{
    /**
     * @var External_UserInterface
     */
    private $externalUser;

    /**
     * @var IConfigLoader
     */
    private $config;

    /**
     * Constructor
     *
     * @param External_UserInterface $externalUser
     * @param IConfigLoader          $config
     */
    public function __construct(External_UserInterface $externalUser, IConfigLoader $config)
    {
        $this->externalUser = $externalUser;
        $this->config       = $config;
    }

    /**
     * Checks whether is viewee's email is visible to viewer
     *
     * @param User $viewee
     * @param User $viewer
     * @return bool
     */
    public function isSatisfiedBy(User $viewee, User $viewer): bool
    {
        /* If the viewee hasn't got an email address, you can't see it
         * and role types don't affect the outcome
         */
        if (!$viewee->getEmail()) {
            return false;
        }

        /* Students can always see other students' email addresses, specifically
         */
        if ($viewer->getRole()->isStudent() && $viewee->getRole()->isStudent()) {
            return true;
        }

        /* Everybody else is subjected to the same rules as detail visibility
         * based on role types and other settings
         */
        return (new ValidDetailsVisible($this->externalUser, $this->config))->isSatisfiedBy($viewee, $viewer);
    }
}
