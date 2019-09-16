<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Sync\Provisioner;

use Alaress\Rules\Lang\SkipException;
use InvalidArgumentException;
use Monolog\Logger;
use Psr\Log\LoggerInterface;
use Schoolbox\Campus;
use Schoolbox\External\Connector\ISync;
use Schoolbox\External\Normalise;
use Schoolbox\GenericCollection;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Notif\CacheableLoggerInterface;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Permission\Access\UserAccessMapper;
use Schoolbox\Realtime\Schedule\Trigger\Test\MockPropagator;
use Schoolbox\Role;
use Schoolbox\Sync\Grammar;
use Schoolbox\Sync\Log;
use Schoolbox\Sync\Ruleset;
use UnexpectedValueException;

/**
 * The User Provisioning System.
 *
 * @package Schoolbox\Sync\Provisioner
 */
class Provisioner
{
    /**
     * A ruleset factory - this is used for building configuration and rulesets for each item.
     *
     * @var Ruleset\Factory
     */
    protected $rulesetFactory;

    /**
     * A grammar factory - this is used for building the actual hydratable ruleset object.
     *
     * TODO: we shouldn't need both this and $rulesetFactory
     *
     * @var Grammar\Factory
     */
    protected $grammarFactory;

    /**
     * A connection to an external database - retrieves users from the external DB.
     *
     * @var ISync
     */
    protected $externalUserSource;

    /**
     * A user mapper - this retrieves user's initial states, and saves them.
     *
     * @var UserMapper
     */
    protected $userMapper;

    /**
     * A user access mapper - this retrieves the permissions that an existing
     * user has on groups.
     *
     * @var UserAccessMapper
     */
    protected $userAccessMapper;

    /**
     * A logger
     *
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * The order in which syncs are to be run.
     *
     * The only current requirement is that parents run after students, so that
     * parents may be attached to all their children's campuses.
     *
     * @var array
     */
    protected $runOrder = [Role::STUDENT, Role::STAFF, Role::PARENT,];

    /**
     * A mapper the the logger
     *
     * @var Log\Mapper
     */
    private $logMapper;

    /**
     * Caches new user's usernames so we can detect multi new users
     * with the the same username during dry-run.
     *
     * Maps 'username' => 'externalId'
     *
     * @var array
     */
    protected $newUsernameCache = [];


    /**
     * Constructor
     *
     * @param Ruleset\Factory          $rulesetFactory
     * @param Grammar\Factory          $grammarFactory
     * @param ISync                    $externalUserSource
     * @param UserMapper               $userMapper
     * @param CacheableLoggerInterface $logger
     * @param Log\Mapper               $logMapper
     */
    public function __construct(
        Ruleset\Factory $rulesetFactory,
        Grammar\Factory $grammarFactory,
        ISync $externalUserSource,
        UserMapper $userMapper,
        CacheableLoggerInterface $logger,
        Log\Mapper $logMapper,
        UserAccessMapper $userAccessMapper
    ) {
        $this->rulesetFactory = $rulesetFactory;
        $this->grammarFactory = $grammarFactory;
        $this->externalUserSource = $externalUserSource;
        $userMapper->setEventPropagator(new MockPropagator());
        $this->userMapper = $userMapper;
        $this->logger = $logger;
        $this->logMapper = $logMapper;
        $userAccessMapper->setEventPropagator(new MockPropagator());
        $this->userAccessMapper = $userAccessMapper;
    }

    /**
     * Set the logger from outside this class.
     *
     * @param LoggerInterface $logger
     * @return $this
     */
    public function setLogger(LoggerInterface $logger)
    {
        $this->logger = $logger;
        return $this;
    }

    /**
     * Runs the UPS.
     *
     * @param bool   $update      If true, updates - otherwise, performs a dry run.
     * @param string $forRoleType If set, provisions this role type
     *                            (and only this role type) regardless of if
     *                            it's enabled or not.
     */
    public function run($update = false, $forRoleType = null)
    {
        // Give the dry run 30 minutes to execute
        if (PHP_SAPI != 'cli') {
            set_time_limit(1800);
        }

        foreach ($this->runOrder as $roleType) {
            if (isset($forRoleType) && $roleType != $forRoleType) {
                // Don't run the provisioner for this role
                continue;
            }
            try {
                $ruleset = $this->rulesetFactory->produceRuleset($roleType, $this->logger, $this->grammarFactory);
            } catch (\Exception $e) {
                $this->logger->alert("Invalid ruleset detected for '{$roleType}'; skipping.", ['exception' => $e]);
                $this->logMapper->flush($this->logger);
                continue;
            }

            if ($ruleset->getConfig()->getEnabled() || $roleType == $forRoleType) {

                $this->logger->info("Commencing $roleType user provision: " . ($update ? 'updating' : 'dry run only'));

                $created = $updated = $skipped = $errors = 0;

                try {
                    $externalUsers = $this->getAllExternalUsers($roleType);
                } catch (\Exception $e) {
                    // Raise exception message, and continue as if nothing was returned
                    $this->logger->error("Cannot provision $roleType users: {$e->getMessage()}");
                    $externalUsers = [];
                }

                foreach ($externalUsers as $externalUser) {

                    // No user id? do nothing
                    $externalUserId = $externalUser->getId();
                    if (!$externalUserId) {
                        $this->logger->error("Cannot process user: User must have an External ID");
                        ++$skipped;
                        ++$errors;
                        continue;
                    }

                    // this is the context that needs to be applied to every log message while working with a user, for filtering
                    $commonContext = [
                        'externalUserId' => $externalUserId,
                    ];

                    // Get the currently stored user
                    /** @var User $internalUser */
                    try {
                        $internalUser = $this->userMapper->findByExternalId($externalUserId, $roleType);
                        // Don't need to know about external DB access.
                        // UPS can't change or do anything with class membership
                        // as that comes from the SIS anyway.
                        // Don't want to know about any permissions other than
                        // group permissions, either: only group permissions can
                        // be changed by UPS.
                        $internalUser->setAccessList(
                            $this->userAccessMapper
                                ->findAllForUser($internalUser, false)
                                ->filterBy('service', UserAccess::SERVICE_HOMEPAGE)
                        );
                        $commonContext['internalUserId'] = $internalUser->getId();
                        $this->logger->debug("Retrieving existing user: " . $internalUser->formatName(), $commonContext);
                        $action = 'update';

                    } catch (MapperException $e) {
                        $internalUser = (new User())
                            ->setCampusCollection(new GenericCollection(Campus::class));
                        $internalUser->getRole()->setType($roleType);
                        $this->logger->debug("No existing user to retrieve: new user", $commonContext);
                        $action = 'create';
                    }


                    // Keep a copy of the original user, for comparison
                    $originalUser  = clone $internalUser;
                    $oldAccessList = $this->formatAccessLevels($originalUser);


                    /* Deliberately empty $accessList so that Role-based
                     * permissions don't get saved in addition to the Group
                     * permissions that we can set
                     */
                    $preserved = $this->userAccessMapper->createCollection();


                    /* Don't blow away any UserAccess::ACCESS_FULL settings
                     */
                    foreach ($internalUser->getAccessList()->filterBy('service', UserAccess::SERVICE_HOMEPAGE) as $current) {
                        /** @var UserAccess $current */
                        if ($current->getAccessLevel() === UserAccess::ACCESS_FULL) {
                            $preserved->add($current);

                            $this->logger->debug("{$internalUser->getFullname()} is staying at access level {$current->getAccessLevel()} for {$current->getServiceIdentifier()}", $commonContext);
                        }
                    }

                    $internalUser->setAccessList($preserved);

                    $commonContext['action'] = $action;


                    // Run this user through the hydrators
                    try {
                        $ruleset->hydrate($externalUser, $internalUser);
                    } catch (SkipException $e) {
                        $commonContext['action'] = 'skip';
                        $this->logger->warning("Skipping {$internalUser->formatName()} ({$internalUser->getExternalId()}): " . $e->getMessage(), $commonContext);
                        ++$skipped;
                        continue;

                    } catch (\Exception $e) {
                        // something untoward happened - better raise an error
                        $this->logger->error("Error provisioning {$internalUser->formatName()} ({$internalUser->getExternalId()}): " . $e->getMessage(), array_merge($commonContext, ['error' => $e]));
                        ++$skipped;
                        continue;
                    }

                    // get a diff of the original user and what we've ended up with
                    $diff = $this->userMapper->diff($originalUser, $internalUser, ['old', 'new']);

                    // get a diff of the access list as well
                    $newAccessList = $this->formatAccessLevels($internalUser, $originalUser);
                    if ($newAccessList !== $oldAccessList) {
                        $diff['accessList'] = [
                            'old' => $oldAccessList,
                            'new' => $newAccessList,
                        ];
                    }

                    $originalName = ($action == 'create') ? "New user {$internalUser->formatName()} ({$internalUser->getExternalId()})"
                        : $originalUser->formatName() . " ({$internalUser->getExternalID()})";

                    if (count($diff)) {
                        $level = Logger::NOTICE;
                        $message = $update ? "$originalName $action" : "$originalName would have been {$action}d";
                        $context = array_merge($commonContext, ['diff' => $diff]);
                    } else {
                        $level = Logger::INFO;
                        $message = ($update ? "Skipping $action of $originalName" : "$originalName would not have been {$action}d") . ': record is unchanged';
                        $context = $commonContext;
                        $this->logger->log($level, $message, $context);
                        ++$skipped;
                        continue;
                    }

                    try {
                        if (!$update) {
                            // Dry Run
                            $this->sanityCheck($internalUser);
                            $this->logger->debug("User record passes sanity check", $commonContext);
                        } else {
                            // Save includes pre-save sanity check
                            $this->userMapper->save($internalUser);
                            $this->logger->debug("User record passes sanity check", $commonContext);
                            $this->logger->debug("User record saved", $commonContext);
                        }

                        $this->logger->log($level, $message, $context);

                        if ($action == 'update') {
                            ++$updated;
                        } else {
                            ++$created;
                        }

                    } catch (UnexpectedValueException $e) {
                        $this->logger->warning(sprintf("{$internalUser->getFullName()} ({$internalUser->getExternalId()}) fails sanity check (%s): skipping", $e->getMessage()), $context);
                        ++$skipped;
                        continue;
                    } catch (\Exception $e) {
                        $this->logger->error("User record save failed: {$e->getMessage()}", array_merge($commonContext, ['error' => $e->getMessage()]));
                        ++$skipped;

                    }
                }

                // end of a strat - save the result
                $this->logger->info("SUMMARY: $created users created, $updated users updated, $skipped users skipped");
                $this->logMapper->flush($this->logger);
            }
        }
    }

    /**
     * Callback for when UPS processing is interrupted for some reason.
     *
     * @param int $reason One of the signal constants (SIGTERM, SIGINT, etc.)
     *                    or an Exception
     */
    public function onInterrupt($reason = null)
    {
        if ($reason instanceof \Throwable) {
            $message = sprintf("Execution interrupted by exception:\n%s", $reason->getTraceAsString());
        } else {
            switch ($reason) {
                case SIGTERM:
                    $message = 'Execution interrupted by external signal - flushing log';
                    break;
                case SIGINT:
                case SIGQUIT:
                    $message = 'Execution interrupted by user - flushing log';
                    break;
                default:
                    $message = sprintf('Execution interrupted by some other signal (%d) - flushing log', $reason);
                    break;
            }
        }
        $this->logger->alert($message);
        $this->logMapper->flush($this->logger);
    }

    /**
     * Gets all users out of $this->externalUserSource for this roletype.
     *
     * @param string $roleType Role type of users to fetch
     *
     * @return Normalise\User\Base[]
     * @throws InvalidArgumentException if $roleType isn't staff, student or parent
     */
    protected function getAllExternalUsers($roleType)
    {
        switch ($roleType) {
            case Role::STUDENT:
                return $this->externalUserSource->findAllStudents();
            case Role::STAFF:
                return $this->externalUserSource->findAllStaff();
            case Role::PARENT:
                return $this->externalUserSource->findAllParents();
        }
        throw new InvalidArgumentException("$roleType is not one of: student, staff, parent");
    }

    /**
     * Performs a sanity check on this user - checks if all their required
     * fields are set.
     *
     * Throws validation exceptions if it isn't.
     *
     * @param User $internalUser
     * @throws UnexpectedValueException if a value isn't what it should be
     */
    protected function sanityCheck(User $internalUser)
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
     * @throws UnexpectedValueException if multiple new users were created with same username
     */
    protected function verifyUniqueNewUserUsername(User $internalUser)
    {

        // Is this a new user?
        if (null === $internalUser->getId()) {
            // Have we already created a new user with this username?
            if (isset($this->newUsernameCache[$internalUser->getUsername()])) {
                throw new UnexpectedValueException(
                    sprintf('A new user with username "%s" was already created. Other user external id: "%s"',
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


    /**
     * Format access levels in a user-readable manner
     *
     * @param User      $user
     * @param User|null $previous
     * @return array
     */
    private function formatAccessLevels(User $user, ?User $previous = null): array
    {
        // Final access levels:
        // Original - removed + added
        $result = [];
        if ($previous) {
            foreach ($previous->getAccessList() as $access) {
                if ($access->getService() === UserAccess::SERVICE_HOMEPAGE) {
                    $result[$access->getServiceIdentifier()] = $access->getAccessLevel();
                }
            }
        }


        // Remove any groups in the remove list
        foreach ($user->getUserAccessRemove() as $accessRemove) {
            $existingAccess = $result[$accessRemove] ?? UserAccess::ACCESS_DENY;
            if ($existingAccess < UserAccess::ACCESS_FULL) {
                unset($result[$accessRemove]);
            }
        }


        // Then add any groups in the add list
        foreach ($user->getAccessList() as $access) {
            if ($access->getService() === UserAccess::SERVICE_HOMEPAGE
                && $access->getAccessLevel() < UserAccess::ACCESS_FULL
            ) {
                $result[$access->getServiceIdentifier()] = $access->getAccessLevel();
            }
        }


        // Format it in a readable format
        ksort($result);
        $formattedResult = [];
        foreach ($result as $groupId => $accessLevel) {
            $accessLevelString = null;
            switch ($accessLevel) {
                case UserAccess::ACCESS_WRITE:
                    $accessLevelString = 'write';
                    break;

                case UserAccess::ACCESS_READ:
                    $accessLevelString = 'read';
                    break;

                case UserAccess::ACCESS_FULL:
                case UserAccess::ACCESS_DENY:
                default:
                    // Don't render
                    break;
            }
            if (isset($accessLevelString)) {
                $formattedResult[] = sprintf('%d: %s', $groupId, $accessLevelString);
            }
        }
        return $formattedResult;
    }
}
