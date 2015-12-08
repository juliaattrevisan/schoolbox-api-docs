<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2015, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Sync\Provisioner;

use Alaress\Rules\Lang\SkipException;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use Schoolbox\External\Connector\SyncInterface;
use Schoolbox\External\Normalise;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Role;
use Schoolbox\Sync\Grammar;
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
     * Constructor
     *
     * @param Ruleset\Factory $rulesetFactory
     * @param Grammar\Factory $grammarFactory
     * @param SyncInterface   $externalUserSource
     * @param UserMapper      $userMapper
     * @param LoggerInterface $logger
     */
    public function __construct(
        Ruleset\Factory $rulesetFactory,
        Grammar\Factory $grammarFactory,
        SyncInterface $externalUserSource,
        UserMapper $userMapper,
        LoggerInterface $logger
    ) {
        $this->rulesetFactory     = $rulesetFactory;
        $this->grammarFactory     = $grammarFactory;
        $this->externalUserSource = $externalUserSource;
        $this->userMapper         = $userMapper;
        $this->logger             = $logger;
    }

    /**
     * Runs the UPS.
     *
     * @param bool $update If true, updates - otherwise, performs a dry run.
     */
    public function run($update = false)
    {
        foreach ($this->runOrder as $roleType) {
            $ruleset = $this->rulesetFactory->produceRuleset($roleType, $this->grammarFactory);
            if ($ruleset->getConfig()->getEnabled()) {
                foreach ($this->getAllExternalUsers($roleType) as $externalUser) {
                    // No user id? do nothing
                    $externalUserId = $externalUser->getId();
                    if (!$externalUserId) {
                        continue;
                    }

                    // Get the currently stored user
                    try {
                        $internalUser = $this->userMapper->findByExternalId($externalUserId, $roleType);
                    } catch (MapperException $e) {
                        $internalUser = $this->userMapper->createModel();
                    }
//                    $originalUser = clone $internalUser;

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
                        continue;
                    } catch (\Exception $e) {
                        // something untoward happened - better raise an error
                        continue;
                    }

                    // And save
                    if ($update) {
                        // TODO add some sort of sanity check to $internalUser
                        $this->userMapper->save($internalUser);
                    }
                }
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
