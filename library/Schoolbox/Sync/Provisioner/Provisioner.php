<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2015, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Sync\Provisioner;

use Psr\Log\LoggerInterface;
use Schoolbox\External\Connector\SyncInterface;
use Schoolbox\Role;
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
     * A ruleset factory - this is used for building rulesets for each item.
     *
     * @var Ruleset\Factory
     */
    protected $rulesetFactory;

    /**
     * A connection to an external database - retrieves users from the external DB.
     *
     * @var SyncInterface
     */
    protected $externalUserMapper;

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
     * @param SyncInterface   $externalUserSource
     * @param UserMapper      $userMapper
     * @param LoggerInterface $logger
     */
    public function __construct(
        Ruleset\Factory $rulesetFactory,
        SyncInterface $externalUserSource,
        UserMapper $userMapper,
        LoggerInterface $logger
    ) {
        $this->rulesetFactory     = $rulesetFactory;
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
            $ruleset = $this->rulesetFactory->produceRuleset($roleType);
            if ($ruleset->getConfig()->getEnabled()) {
                // run something
                // do something with each roletype's users
            }
        }
    }
}
