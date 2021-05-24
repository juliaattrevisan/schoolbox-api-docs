<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Sync\Provisioner;

use Psr\Log\LoggerInterface;
use Schoolbox\CampusMapper;
use Schoolbox\Permission\Access\UserAccessMapper;
use Schoolbox\User as SchoolboxUser;
use Schoolbox\Permission\Access\UserAccess;

/**
 * Class User
 *
 * @package Schoolbox\Sync\Provisioner
 */
class User extends SchoolboxUser
{
    /**
     * A list of UserAccess instances to remove for this
     * particular user instance
     *
     * @var array
     */
    protected $accessRemoveList = [];

    /**
     * A list of campus instances to remove for this
     * particular user instance
     *
     * @var array
     */
    protected $campusRemoveList = [];


    /**
     * A timeline of mutations - additions and removals - of UserAccess instances
     * as they were specified by the UPS Ruleset.
     *
     * @var array
     */
    private $userAccessMutations = [];


    /**
     * Get user access read
     * A list of Groups to which this user has Read access
     *
     * @return array
     */
    public function getUserAccessRead()
    {
        return $this->accessList->filterBy('accessLevel', UserAccess::ACCESS_READ)->getColumnValues('serviceIdentifier');
    }

    /**
     * Set user access read
     *
     * @param array $userAccessRead
     */
    public function setUserAccessRead($userAccessRead)
    {
        /* Add it to the list of things to process later
         */
        $this->userAccessMutations[] = [
            'instance' => $userAccessRead,
            'level' => UserAccess::ACCESS_READ,
            'status' => 'add'
        ];
    }

    /**
     * Get user access write
     * A list of Groups to which this user has Write access
     *
     * @return array
     */
    public function getUserAccessWrite()
    {
        return $this->accessList->filterBy('accessLevel', UserAccess::ACCESS_WRITE)->getColumnValues('serviceIdentifier');
    }

    /**
     * Set user access write
     *
     * @param array $userAccessWrite
     */
    public function setUserAccessWrite($userAccessWrite)
    {
        /* Add it to the list of things to process later
         */
        $this->userAccessMutations[] = [
            'instance' => $userAccessWrite,
            'level' => UserAccess::ACCESS_WRITE,
            'status' => 'add'
        ];
    }

    /**
     * Get user access remove
     *
     * @return array
     */
    public function getUserAccessRemove()
    {
        return $this->accessRemoveList;
    }

    /**
     * Remove selected UserAccess instances
     *
     * @param array $userAccessRemove
     */
    public function setUserAccessRemove($userAccessRemove)
    {
        /* Add it to the list of things to process later
         */
        $this->userAccessMutations[] = [
            'instance' => $userAccessRemove,
            'level' => null,
            'status' => 'remove'
        ];
    }

    /**
     * Get campus collection remove
     *
     * @return array
     */
    public function getCampusCollectionRemove()
    {
        return $this->campusRemoveList;
    }


    /**
     * Get a list of UserAccess additions and removals, in the order in which they occurred
     *
     * @return array
     */
    public function getUserAccessMutations(): array
    {
        return $this->userAccessMutations;
    }


    /**
     * Remove selected CampusCollection instances.
     *
     * Any campuses in $campusCollectionRemove are removed from this user's
     * campus collection.
     *
     * TODO: But is this even used?
     *
     * @param array $campusCollectionRemove
     * @throws \UnexpectedValueException
     */
    public function setCampusCollectionRemove($campusCollectionRemove)
    {
        $newCampusCollection = CampusMapper::getInstance()->createCollection();
        foreach ($this->getCampusCollection() as $existingCampus) {
            // $campusCollectionRemove is provided as a list of IDs
            $isCampusRemoved = false;
            foreach ($campusCollectionRemove as $campusRemoveId) {
                if ($campusRemoveId == $existingCampus->getId()) {
                    $isCampusRemoved = true;
                    break;
                }
            }
            if (!$isCampusRemoved) {
                $newCampusCollection->add($existingCampus);
            }
        }
        $this->setCampusCollection($newCampusCollection);

        // And add to the list to remove, if this actually does anything anymore!
        $this->campusRemoveList = $campusCollectionRemove;
    }
}
