<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2015, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Sync\Provisioner;

use Alaress\Rules\Lang\SkipException;
use InvalidArgumentException;
use Monolog\Logger;
use Psr\Log\LoggerInterface;
use Schoolbox\Collection;
use Schoolbox\External\Connector\SyncInterface;
use Schoolbox\External\Normalise;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Notif\CacheableLoggerInterface;
use Schoolbox\Role;
use Schoolbox\Sync\Grammar;
use Schoolbox\Sync\Log;
use Schoolbox\Sync\Ruleset;
use Schoolbox\UserMapper;

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
     * @var SyncInterface
     */
    protected $externalUserSource;

    /**
     * A user mapper - this retrieves user's initial states, and saves them.
     *
     * @var UserMapper
     */
    protected $userMapper;

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
     * Constructor
     *
     * @param Ruleset\Factory $rulesetFactory
     * @param Grammar\Factory $grammarFactory
     * @param SyncInterface   $externalUserSource
     * @param UserMapper      $userMapper
     * @param CacheableLoggerInterface $logger
     * @param Log\Mapper      $logMapper
     */
    public function __construct(
        Ruleset\Factory $rulesetFactory,
        Grammar\Factory $grammarFactory,
        SyncInterface $externalUserSource,
        UserMapper $userMapper,
        CacheableLoggerInterface $logger,
        Log\Mapper $logMapper
    ) {
        $this->rulesetFactory     = $rulesetFactory;
        $this->grammarFactory     = $grammarFactory;
        $this->externalUserSource = $externalUserSource;
        $this->userMapper         = $userMapper;
        $this->logger             = $logger;
        $this->logMapper          = $logMapper;
    }

    /**
     * Set the logger from outside this class.
     *
     * @param  LoggerInterface $logger
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
        foreach ($this->runOrder as $roleType) {
            if (isset($forRoleType) && $roleType != $forRoleType) {
                // Don't run the provisioner for this role
                continue;
            }
            $ruleset = $this->rulesetFactory->produceRuleset($roleType, $this->logger, $this->grammarFactory);
            if ($ruleset->getConfig()->getEnabled() || $roleType == $forRoleType) {

                $this->logger->warning("Commencing $roleType user provision: " . ($update ? 'updating' : 'dry run only'));

                $created = $updated = $skipped = 0;

                foreach ($this->getAllExternalUsers($roleType) as $externalUser) {
                    // No user id? do nothing
                    $externalUserId = $externalUser->getId();
                    if (!$externalUserId) {
                        $this->logger->notice("Skipping user, no externalUserId found");
                        ++$skipped;
                        continue;
                    }

                    // this is the context that needs to be applied to every log message while working with a user, for filtering
                    $commonContext = [
                        'externalUserId' => $externalUserId
                    ];

                    // Get the currently stored user
                    try {
                        $internalUser = $this->userMapper->findByExternalId($externalUserId, $roleType);
                        $commonContext['internalUserId'] = $internalUser->getId();
                        $this->logger->debug("Retrieving existing user: " . $internalUser->formatName(), $commonContext);
                        $action = 'update';

                    } catch (MapperException $e) {
                        $internalUser = $this->userMapper->createModel();
                        $this->logger->debug("No existing user to retrieve: new user", $commonContext);
                        $action = 'create';

                    }

                    $originalUser = clone $internalUser;

                    // Run this user through the hydrators
                    try {
                        $ruleset->hydrate($externalUser, $internalUser);
//                        new \Alaress_Probe(
//                            $this->userMapper->dehydrate($originalUser),
//                            $this->userMapper->dehydrate($internalUser),
//                            $this->userMapper->diff($originalUser, $internalUser)
//                        );
//                        echo '<hr style="clear:both"/>';
                    } catch (SkipException $e) {
                        // This ruleset is to be skipped - continue
//                        new \Alaress_Probe(
//                            $this->userMapper->dehydrate($originalUser),
//                            'skip',
//                            []
//                        );
//                        echo '<hr style="clear:both"/>';
                        $this->logger->notice("Skipping user: ".$e->getMessage(), array_merge($commonContext, [ 'error' => $e ]));
                        ++$skipped;
                        continue;
                    } catch (\Exception $e) {
                        // something untoward happened - better raise an error
                        $this->logger->error("Error provisioning user: ".$e->getMessage(), array_merge($commonContext, [ 'error' => $e ]));
                        ++$skipped;
                        continue;
                    }

                    // get a diff of the original user and what we've ended up with
                    $diff = $this->userMapper->diff($originalUser, $internalUser);

                    $originalName = ($action == 'create') ? 'New user' : $originalUser->formatName();

                    if (count($diff)) {
                        $level   = Logger::NOTICE;
                        $message = $update ? "Preparing to $action $originalName" : "$originalName would have been {$action}d";
                        $context = array_merge($commonContext, [ 'diff' => $diff ]);

                        if ($action == 'update') {
                            ++$updated;
                        } else {
                            ++$created;
                        }

                    } else {
                        $level   = Logger::DEBUG;
                        $message = ($update ? "Skipping $action of $originalName" : "$originalName would not have been {$action}d") . ': data is unchanged';
                        $context = $commonContext;

                        ++$skipped;
                    }

                    $this->logger->log($level, $message, $context);

                    // And save
                    if ($update) {
                        // TODO add some sort of sanity check to $internalUser
                        $this->userMapper->save($internalUser);
                        $this->logger->debug("User record saved", $commonContext);
                    }
                }

                // end of a strat - save the result
                $this->logger->warning("SUMMARY: $created users created, $updated users updated, $skipped users skipped");
                $this->logMapper->flush($this->logger);
            }
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
}
