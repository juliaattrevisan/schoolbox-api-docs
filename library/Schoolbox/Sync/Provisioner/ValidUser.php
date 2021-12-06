<?php
/**
 * @copyright   Copyright 2021, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Sync\Provisioner;

/**
 * Decide if $internalUser fulfils the minimum required data
 * to go peacefully into the database
 */
class ValidUser
{

    /**
     * @var \Schoolbox\UserMapper
     */
    private \Schoolbox\UserMapper $userMapper;


    /**
     * @var array
     */
    private array $newUsernameCache;

    /**
     * @param \Schoolbox\UserMapper $userMapper
     * @param array                 $newUsernameCache
     */
    public function __construct(\Schoolbox\UserMapper $userMapper, array $newUsernameCache)
    {
        $this->userMapper = $userMapper;
        $this->newUsernameCache = $newUsernameCache;
    }


    /**
     * Performs a sanity check on this user - checks if all their required
     * fields are set.
     *
     * Throws validation exceptions if it isn't.
     *
     * @param User $internalUser
     * @throws \PDOException
     * @throws \UnderflowException
     * @throws \UnexpectedValueException if a value isn't what it should be
     */
    public function sanityCheck(\Schoolbox\User $internalUser): void
    {
        $this->userMapper->verifyValidUsername($internalUser);
        $this->userMapper->verifyUniqueUsername($internalUser);
        $this->verifyUniqueNewUserUsername($internalUser);
        $this->userMapper->verifyValidRole($internalUser);
        $this->userMapper->verifyHasCampus($internalUser);
        $this->userMapper->verifyValidFirstName($internalUser);
        $this->userMapper->verifyValidLocalEmail($internalUser);
        $this->userMapper->verifyValidWebmail($internalUser);
        $this->userMapper->verifyValidInternalMail($internalUser);
        $this->userMapper->verifyValidEportfolioEnabled($internalUser);
    }

    /**
     * Checks whether a new user's proposed username conflicts with another new user.
     * This enables such scenarios to be detected during the dry-run, rather than waiting for
     * mapper to throw error during live-run.
     *
     * @param \Schoolbox\Sync\Provisioner\User $internalUser
     * @return bool Always true, as long as it doesn't throw
     * @throws \UnexpectedValueException if multiple new users were created with same username
     */
    protected function verifyUniqueNewUserUsername(\Schoolbox\User $internalUser): bool
    {

        // Is this a new user?
        if (null === $internalUser->getId()) {
            // Have we already created a new user with this username?
            if (isset($this->newUsernameCache[$internalUser->getUsername()])) {
                throw new \UnexpectedValueException(
                    sprintf(
                        'A new user with username "%s" was already created. Other user external id: "%s"',
                        $internalUser->getUsername(),
                        $this->newUsernameCache[$internalUser->getUsername()]
                    )
                );
            }
            // New username is unique. Add to cache
            $this->newUsernameCache[$internalUser->getUsername()] = $internalUser->getExternalId();
        }
        return true;
    }
}