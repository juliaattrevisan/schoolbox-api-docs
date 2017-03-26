<?php
/**
 * Synergetic implementation of user connector
 *
 * @author    Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright Copyright 2017, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\External\Connector\Synergetic\Base;

use Alaress\Database\Connector\IConnector;
use Schoolbox\External\Connector\UserInterface;
use Schoolbox\IConfigLoader;

class User implements UserInterface
{
    /**
     * @var IConnector
     */
    protected $databaseExternal;

    /**
     * @var IConfigLoader
     */
    protected $config;

    /**
     * @inheritdoc
     */
    public function __construct($di)
    {
        $this->config = $di->get('config');
        $this->databaseExternal = $di->get('databaseExternal');
    }

    /**
     * @inheritdoc
     */
    function getPortrait($externalId) {

    }

    /**
     * @inheritdoc
     */
    function getGuardians($externalIds, $hydrateStudents = false) {

    }

    /**
     * @inheritdoc
     */
    function getGuardianDirectory($filter = []) {

    }

    /**
     * @inheritdoc
     */
    function getStudents($externalIds, $hydrateGuardians = false) {

    }

    /**
     * @inheritdoc
     */
    function getStudentsAccessible($externalID) {

    }

    /**
     * @inheritdoc
     */
    function getStaff($externalId) {

    }

    /**
     * @inheritdoc
     */
    function getStaffDirectory($filter = []) {

    }

    /**
     * @inheritdoc
     */
    function getTutors($externalIds) {

    }

    /**
     * @inheritdoc
     */
    function getEmails($externalIds) {

    }

    /**
     * @inheritdoc
     */
    function getCampusInvolvement($externalId) {

    }
}