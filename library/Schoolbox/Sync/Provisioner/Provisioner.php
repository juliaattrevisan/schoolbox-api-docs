<?php

/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Sync\Provisioner;

use Alaress\Rules\Lang\SkipException;
use Alaress_Date_Format;
use InvalidArgumentException;
use Monolog\Logger;
use Psr\Log\LoggerInterface;
use Schoolbox\Campus;
use Schoolbox\CampusMapper;
use Schoolbox\External\Connector\ISync;
use Schoolbox\External\Normalise;
use Schoolbox\GenericCollection;
use Schoolbox\IConfigLoader;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Notif\CacheableLoggerInterface;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Permission\Access\UserAccessMapper;
use Schoolbox\Realtime\Schedule\Trigger\Test\MockPropagator;
use Schoolbox\Resource\Folder;
use Schoolbox\Role;
use Schoolbox\RoleMapper;
use Schoolbox\Sync\Grammar;
use Schoolbox\Sync\Log;
use Schoolbox\Sync\Ruleset;
use Schoolbox\YearLevel\Mapper;
use Throwable;
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
    protected Ruleset\Factory $rulesetFactory;

    /**
     * A grammar factory - this is used for building the actual hydratable ruleset object.
     *
     * TODO: we shouldn't need both this and $rulesetFactory
     */
    protected Grammar\Factory $grammarFactory;

    /**
     * A connection to an external database - retrieves users from the external DB.
     */
    protected ISync $externalUserSource;

    /**
     * A user mapper - this retrieves user's initial states, and saves them.
     */
    protected UserMapper $userMapper;

    /**
     * A user access mapper - this retrieves the permissions that an existing
     * user has on groups.
     */
    protected UserAccessMapper $userAccessMapper;

    /**
     * A logger
     */
    protected LoggerInterface $logger;

    /**
     * The order in which syncs are to be run.
     *
     * The only current requirement is that parents run after students, so that
     * parents may be attached to all their children's campuses.
     *
     * @var array<string>
     */
    protected array $runOrder = [Role::STUDENT, Role::STAFF, Role::PARENT,];

    /**
     * A mapper for the logger
     */
    private Log\Mapper $logMapper;

    /**
     * Caches new user's usernames so we can detect multi new users
     * with the the same username during dry-run.
     *
     * Maps 'username' => 'externalId'
     *
     * @var array<string, string>
     */
    protected array $newUsernameCache = [];

    /**
     * A mapper for folders.
     */
    private Folder\Mapper $folderMapper;

    /**
     * Configuration
     */
    private IConfigLoader $config;

    /**
     * A log of the current run.
     */
    private ?Log\Model $currentRun = null;

    /**
     * Cache names
     * @var array
     */
    private array $idToNameMap = [];

    /**
     * 0 means group access is requested in UPS
     */
    private const ACCESS_MAP = [
        0 => 'Requesting',
        1 => 'Read',
        2 => 'Write',
        3 => 'Full'
    ];

    /**
     * Constructor
     *
     * @param Ruleset\Factory          $rulesetFactory
     * @param Grammar\Factory          $grammarFactory
     * @param ISync                    $externalUserSource
     * @param UserMapper               $userMapper
     * @param CacheableLoggerInterface $logger
     * @param Log\Mapper               $logMapper
     * @param UserAccessMapper         $userAccessMapper
     * @param Folder\Mapper            $folderMapper
     * @param IConfigLoader            $config
     */
    public function __construct(
        Ruleset\Factory $rulesetFactory,
        Grammar\Factory $grammarFactory,
        ISync $externalUserSource,
        UserMapper $userMapper,
        CacheableLoggerInterface $logger,
        Log\Mapper $logMapper,
        UserAccessMapper $userAccessMapper,
        Folder\Mapper $folderMapper,
        IConfigLoader $config
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
        $this->folderMapper = $folderMapper;
        $this->config = $config;
    }

    /**
     * Set the logger from outside this class.
     *
     * @param LoggerInterface $logger
     * @return $this
     */
    public function setLogger(LoggerInterface $logger): self
    {
        $this->logger = $logger;
        return $this;
    }

    /**
     * Runs the UPS.
     *
     * @param bool        $update      If true, updates - otherwise, performs a dry run.
     * @param string|null $forRoleType If set, provisions this role type
     *                                 (and only this role type) regardless of if
     *                                 it's enabled or not.
     * @return void
     * @throws MapperException
     */
    public function run(bool $update = false, ?string $forRoleType = null): void
    {
        $this->prepareForLog();
        foreach ($this->runOrder as $roleType) {
            if (isset($forRoleType) && $roleType !== $forRoleType) {
                // Don't run the provisioner for this role
                continue;
            }

            $this->currentRun = $this->logMapper->createModel()
                                                ->setDry(!$update)
                                                ->setRoleType($roleType)
                                                ->setUsersCreated(0)
                                                ->setUsersUpdated(0)
                                                ->setUsersSkipped(0);

            try {
                $ruleset = $this->rulesetFactory->produceRuleset($roleType, $this->logger, $this->grammarFactory);
            } catch (\Exception $e) {
                $this->logger->alert("Invalid ruleset detected for '{$roleType}'; skipping.", ['exception' => $e]);
                $this->logMapper->flush($this->logger, $this->currentRun);
                continue;
            }

            if ($ruleset->getConfig()->getEnabled() || $roleType === $forRoleType) {
                $this->logger->info("Commencing $roleType user provision: " . ($update ? 'updating' : 'dry run only'));

                try {
                    $this->provisionUsers($roleType, $ruleset, $update);
                } catch (\Exception $e) {
                    $this->logger->error("Cannot provision $roleType users: {$e->getMessage()}");
                }

                // end of a strat - save the result
                $this->logger->info(
                    sprintf(
                        "SUMMARY: %d users created, %d users updated, %d users skipped",
                        $this->currentRun->getUsersCreated(),
                        $this->currentRun->getUsersUpdated(),
                        $this->currentRun->getUsersSkipped(),
                    )
                );
                $this->logMapper->flush($this->logger, $this->currentRun);
            }
        }
    }

    /**
     * @param string          $roleType
     * @param Ruleset\Ruleset $ruleset
     * @param bool            $update
     * @throws MapperException
     */
    private function provisionUsers(string $roleType, Ruleset\Ruleset $ruleset, bool $update): void
    {
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
                $this->currentRun->incrementUsersSkipped();
                continue;
            }

            // this is the context that needs to be applied to every log message while working with a user, for filtering
            $commonContext = [
                'externalUserId' => $externalUserId,
            ];

            // Get the currently stored user
            $accessList = $this->userAccessMapper->createCollection();
            try {
                $internalUser = $this->userMapper->findByExternalId($externalUserId, $roleType);
                // Don't need to know about external DB access.
                // UPS can't change or do anything with class membership
                // as that comes from the SIS anyway.
                // Don't want to know about any permissions other than
                // group permissions, either: only group permissions can
                // be changed by UPS.
                $accessList = $this->userAccessMapper->findByColumnList(
                    [
                        'uid'     => $internalUser->getId(),
                        'service' => UserAccess::SERVICE_HOMEPAGE,
                    ]
                );
                // set original access list - this will be referred to update/keep groups in UserMapper postSave
                $internalUser->setAccessList($accessList);
                $commonContext['internalUserId'] = $internalUser->getId();
                $this->logger->debug("Retrieving existing user: " . $internalUser->formatName(), $commonContext);
                $action = 'update';
            } catch (MapperException $e) {
                $internalUser = (new User())
                    ->setCampusCollection(new GenericCollection(Campus::class));
                $internalUser->setAccessList($this->userAccessMapper->createCollection());
                $internalUser->getRole()->setType($roleType);
                $this->logger->debug("No existing user to retrieve: new user", $commonContext);
                $action = 'create';
            }


            // Keep a copy of the original user, for comparison
            $originalUser = clone $internalUser;

            $commonContext['action'] = $action;


            // Run this user through the hydrators
            try {
                $ruleset->hydrate($externalUser, $internalUser);
            } catch (SkipException $e) {
                $commonContext['action'] = 'skip';
                $this->logger->warning("Skipping {$internalUser->formatName()} ({$internalUser->getExternalId()}): " . $e->getMessage(), $commonContext);
                $this->currentRun->incrementUsersSkipped();
                continue;
            } catch (\Exception $e) {
                // something untoward happened - better raise an error
                $this->logger->error("Error provisioning {$internalUser->formatName()} ({$internalUser->getExternalId()}): " . $e->getMessage(), array_merge($commonContext, ['error' => $e]));
                $this->currentRun->incrementUsersSkipped();
                continue;
            }

            // get a diff of the original user and what we've ended up with
            $diff = $this->userMapper->diff($originalUser, $internalUser, ['old', 'new']);


            /* Modify accessList only when ups_groups_enabled is actually enabled
             * Otherwise leave it unchanged from whatever it was
             */
            if ($this->config->get('ups_groups_enabled')) {
                /* Figure out what is going on in the userAccess mutations
                 *
                 * UPS Rulesets can have N adds/removes in any order so you need to compute
                 * the mutations in order to find out where you ended up. These evaluations
                 * also create log->debug entries so you can see what happened
                 */
                $existingGroups = (new ExistingUserAccess($this->folderMapper, $this->logger))->generate($accessList);
                // the following is for PHPStan :(
                /** @var User $internalUser */
                $finalGroups = (new FinalUserAccess($this->folderMapper, $this->logger))->generate($accessList, $internalUser->getUserAccessMutations());

                /* Compute the actual difference - if the set of (folderId, accessLevel)
                 * tuples are the same, then there's no difference and we don't need to
                 * tell anyone about changes
                 *
                 * Otherwise show them the old list and the new list and let them see
                 * how they're different
                 *
                 * Note: In the past the XOR serialisation was done on log messages which
                 * sounds like a kind of weird way to do it but I guess it's not as insane
                 * as it might first appear
                 */
                $diffGroups = (new UserAccessXOR())->generate($existingGroups, $finalGroups);

                if (\count($diffGroups)) {
                    $this->logger->debug(
                        sprintf(
                            'Groups for %s underwent a meaningful mutation',
                            $internalUser->getFullName()
                        )
                    );

                    /* Translate the existing group tuples into something nice for humans
                     * to read - there's a lot of overhead doing these folder lookups
                     * which can definitely be optimised further if we want to keep it
                     */
                    $oldLog = [];

                    foreach ($existingGroups as $folderId => $accessLevel) {
                        /* Maybe this folder doesn't actually exist at runtime
                          */
                        try {
                            $folder     = $this->folderMapper->findCheaplyById($folderId);
                            $folderName = $folder->getName();
                        } catch (\Schoolbox\Mapper\Exception $e) {
                            $folderName = 'unknown folder';
                        }

                        $oldLog[] = sprintf(
                            '%s access to %s (/homepage/%s)',
                            self::ACCESS_MAP[$accessLevel],
                            $folderName,
                            $folderId
                        );
                    }

                    /* Translate the final group tuples into something nice for humans
                     * to read - there's a lot of overhead doing these folder lookups
                     * which can definitely be optimised further if we want to keep it
                     */
                    $newLog = [];

                    foreach ($finalGroups as $folderId => $accessLevel) {
                        /* Maybe this folder doesn't actually exist at runtime
                         */
                        try {
                            $folder     = $this->folderMapper->findCheaplyById($folderId);
                            $folderName = $folder->getName();
                        } catch (\Schoolbox\Mapper\Exception $e) {
                            $folderName = 'unknown folder';
                        }

                        $newLog[] = sprintf(
                            '%s access to %s (/homepage/%s)',
                            self::ACCESS_MAP[$accessLevel],
                            $folderName,
                            $folderId
                        );
                    }

                    /* Do you actually need to see what they had,
                     * or is the new set of UserAccess enough?
                     */
                    $diff['accessList'] = [
                        'old' => $oldLog,
                        'new' => $newLog,
                    ];
                } else {
                    $this->logger->debug(
                        sprintf(
                            'Groups for %s remained unchanged',
                            $internalUser->getFullName()
                        )
                    );
                }
            }


            $originalName = ($action === 'create') ? "New user {$internalUser->formatName()} ({$internalUser->getExternalId()})"
                : $originalUser->formatName() . " ({$internalUser->getExternalID()})";

            if (count($diff)) {
                $level   = Logger::NOTICE;
                $message = $update ? "$originalName $action" : "$originalName would have been {$action}d";
                $context = array_merge($commonContext, ['diff' => $this->formatForLog($diff)]);
            } else {
                // The user hasn't been changed: still, they've been
                // seen by UPS so their updated at time should update.
                // This is so they are not considered stale by the stale
                // users report.
                $level   = Logger::INFO;
                $message = ($update ? "Skipping $action of $originalName" : "$originalName would not have been {$action}d") . ': record is unchanged';
                $context = $commonContext;
                $this->logger->log($level, $message, $context);
                if ($update && ($action === 'update')) {
                    $this->logger->debug("Marking $originalName as having been updated", $commonContext);
                    $internalUser->setUpdatedAt(Alaress_Date_Format::now());
                    try {
                        $this->userMapper->save($internalUser);
                    } catch (MapperException | UnexpectedValueException $e) {
                        $this->logger->warning(
                            sprintf(
                                "Failed to mark %s as having been updated (%s): ",
                                $originalName,
                                $e->getMessage()
                            ),
                            $context
                        );
                    }
                }
                $this->currentRun->incrementUsersSkipped();
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

                if ($action === 'update') {
                    $this->currentRun->incrementUsersUpdated();
                } else {
                    $this->currentRun->incrementUsersCreated();
                }
            } catch (UnexpectedValueException $e) {
                $this->logger->warning(sprintf("{$internalUser->getFullName()} ({$internalUser->getExternalId()}) fails sanity check (%s): skipping", $e->getMessage()), $context);
                $this->currentRun->incrementUsersSkipped();
                continue;
            } catch (\Exception $e) {
                $this->logger->error("User record save failed: {$e->getMessage()}", array_merge($commonContext, ['error' => $e->getMessage()]));
                $this->currentRun->incrementUsersSkipped();
            }
        }
    }

    /**
     * Callback for when UPS processing is interrupted for some reason.
     *
     * @param int|Throwable $reason One of the signal constants (SIGTERM, etc.)
     *                              or a Throwable
     */
    public function onInterrupt($reason = null)
    {
        if ($reason instanceof Throwable) {
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

        // Can only flush the log if there's a run to flush
        if (isset($this->currentRun)) {
            $this->logMapper->flush($this->logger, $this->currentRun);
        }
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
     * @throws \PDOException
     * @throws \UnderflowException
     */
    protected function sanityCheck(User $internalUser): void
    {
        (new ValidUser($this->userMapper, $this->newUsernameCache))->sanityCheck($internalUser);
    }


    /**
     * Cache lookups for log
     */
    private function prepareForLog(): void
    {
        /** @var Mapper $yearLevelMapper */
        $yearLevelMapper           = $this->userMapper->getJoinMapper('year');
        $yearLevels                = $yearLevelMapper->findAll();
        $this->idToNameMap['year'] = array_combine($yearLevels->getPropertyValues('id'), $yearLevels->getPropertyValues('name'));

        /** @var CampusMapper $campusMapper */
        $campusMapper                          = $this->userMapper->getJoinMapper('userCampus');
        $campuses                              = $campusMapper->findAll();
        $this->idToNameMap['campusCollection'] = array_combine($campuses->getPropertyValues('id'), $campuses->getPropertyValues('name'));

        /** @var RoleMapper $roleMapper */
        $roleMapper                = $this->userMapper->getJoinMapper('role');
        $roles                     = $roleMapper->findAll();
        $this->idToNameMap['role'] = array_combine($roles->getPropertyValues('id'), $roles->getPropertyValues('name'));

        /** @var \HouseMapper $schoolHouseMapper */
        $schoolHouseMapper                = $this->userMapper->getJoinMapper('schoolHouse');
        $houses                           = $schoolHouseMapper->findAll();
        $this->idToNameMap['schoolHouse'] = array_combine($houses->getPropertyValues('id'), $houses->getPropertyValues('house'));

        /** @var \HouseResidentialMapper $residentialHouseMapper */
        $residentialHouseMapper                = $this->userMapper->getJoinMapper('residentialHouse');
        $houses                                = $residentialHouseMapper->findAll();
        $this->idToNameMap['residentialHouse'] = array_combine($houses->getPropertyValues('id'), $houses->getPropertyValues('house'));
    }

    /**
     * Generates diff for UPS log
     * @param array $diff
     * @return array
     */
    private function formatForLog(array $diff): array
    {
        foreach (array_keys($this->idToNameMap) as $col) {
            if (key_exists($col, $diff) && is_array($diff[$col])) {
                foreach ($diff[$col] as $key => $ids) {
                    $formatted = [];
                    if (null === $ids) {
                        continue;
                    }
                    // convert single id to array... for campus ids is array, for house ids is single value
                    if (!is_array($ids)) {
                        $ids = [$ids];
                    }
                    foreach ($ids as $id) {
                        if (!isset($this->idToNameMap[$col][$id])) {
                            continue;
                        }
                        $formatted[] = $this->idToNameMap[$col][$id];
                    }
                    $diff[$col][$key] = implode(',', $formatted);
                }
            }
        }
        return $diff;
    }
}
