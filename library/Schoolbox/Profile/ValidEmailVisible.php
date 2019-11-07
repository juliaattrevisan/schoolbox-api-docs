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
     * Checks whether is viewee's detail is visible to viewer
     * @param User $viewee
     * @param User $viewer
     * @return bool
     */
    public function isSatisfiedBy(User $viewee, User $viewer): bool
    {
        if (!$viewer->getEmail()) {
            return false;
        }

        if ($viewer->getRole()->isStudent() && $viewee->getRole()->isStudent()) {
            return true;
        }

        return (new ValidDetailsVisible($this->externalUser, $this->config))->isSatisfiedBy($viewee, $viewer);
    }
}
