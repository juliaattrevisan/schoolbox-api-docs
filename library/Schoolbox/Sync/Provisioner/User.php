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


    /**
     * Find the current set of userAccess instances
     *
     * @param \Schoolbox\Resource\Folder\Mapper $fm
     * @param LoggerInterface                   $logger
     * @return array|null
     * @throws \InvalidArgumentException
     * @throws \Schoolbox\Mapper\DefinitionException
     * @throws \Schoolbox\Mapper\Exception
     * @throws \Schoolbox\Mapper\Exception\Deleted
     * @throws \Schoolbox\Mapper\Exception\NotFound
     */
    public function generateExistingUserAccess(\Schoolbox\Resource\Folder\Mapper $fm, LoggerInterface $logger): ?array
    {
        /* Find out what you started with
         */
        $existingGroups = [];

        foreach ($this->getAccessList() as $userAccess) {
            $folderId = $userAccess->getServiceIdentifier();
            $folder = $fm->findCheaplyById($folderId);

            $existingGroups[$folderId] = $userAccess->getAccessLevel();

            $logger->debug(sprintf(
                "Existing Group: %s (%s) with accesslevel %s\n",
                $folder->getId(),
                $folder->getName(),
                $userAccess->getAccessLevel()
            ));
        }

        return $existingGroups;
    }


    /**
     * Using the mutation timeline, derive UserAccess instances
     * deciding whether to retain Full Access settings and/or create
     * new instances
     *
     * @param \Schoolbox\Resource\Folder\Mapper $fm
     * @param LoggerInterface                   $logger
     * @return array|null
     * @throws \InvalidArgumentException
     * @throws \Schoolbox\Mapper\DefinitionException
     * @throws \Schoolbox\Mapper\Exception
     * @throws \Schoolbox\Mapper\Exception\Deleted
     * @throws \Schoolbox\Mapper\Exception\NotFound
     */
    public function generateFinalUserAccess(\Schoolbox\Resource\Folder\Mapper $fm, LoggerInterface $logger): ?array
    {

        /* Decide the net effect, in accordance to how the mutation timeline evolved
         */
        $finalGroups = [];

        foreach ($this->getUserAccessMutations() as $mutation) {
            foreach($mutation['instance'] as $folderId) {
                $folder = $fm->findCheaplyById($folderId);

                if ($mutation['status'] === 'add') {
                    $logger->debug(sprintf(
                        "Groups: %s %s (%s) with accesslevel %s\n",
                        $mutation['status'],
                        $folder->getId(),
                        $folder->getName(),
                        $mutation['level']
                    ));

                    $finalGroups[$folderId] = $mutation['level'];
                } elseif ($mutation['status'] === 'remove') {

                    $logger->debug(sprintf(
                        "Groups: %s %s (%s)\n",
                        $mutation['status'],
                        $folder->getId(),
                        $folder->getName()
                    ));

                    unset($finalGroups[$folderId]);
                }
            }
        }

        /* For every item listed in the mutation timeline, process a UserAccess
         * These are now the userAccesses the person should have
         */
        foreach ($finalGroups as $serviceIdentifier => $accessLevel) {
            $userAccess = UserAccessMapper::getInstance()->createModel()
                ->setUser($this)
                ->setService(UserAccess::SERVICE_HOMEPAGE)
                ->setServiceIdentifier($serviceIdentifier)
                ->setAccessLevel($accessLevel);

            /* Shank it in to this user instance
             */
            $this->accessList->add($userAccess);

            $logger->debug(sprintf(
                "Final Groups: %s %s \n",
                $serviceIdentifier,
                $accessLevel
            ));
        }

        /* This is the list of groups to which this person has access
         */
        return $finalGroups;
    }

//
//    /**
//     * Set $identifiers to access level $level in $accessList collection
//     *
//     * @param array $identifiers
//     * @param int $level
//     */
//    private function setAccessLevel($identifiers, $level)
//    {
//        /* Modify accessList only when ups_groups_enabled is actually enabled
//         * Otherwise leave it unchanged from whatever it was
//         */
//        if ($this->getConfigLoader()->get('ups_groups_enabled')) {
//            $found = [];
//
//            /* Update existing userAccess instance
//             *
//             * In accordance with SB-11230:
//             * - don't change Full Access settings
//             * - downgrade Write to Read
//             */
//            foreach ($this->getAccessList()->filterBy('service', UserAccess::SERVICE_HOMEPAGE) as $current) {
//                if (in_array($current->getServiceIdentifier(), $identifiers)) {
//                    if ($current->getAccesslevel() < UserAccess::ACCESS_FULL) {
//                        $current->setAccessLevel($level);
//                    }
//
//                    $found[] = $current->getServiceIdentifier();
//                }
//            }
//
//            /* If the userAccess instance didn't exist, create it
//             */
//            $diff = array_diff($identifiers, $found);
//
//            if (count($diff)) {
//                foreach ($diff as $current) {
//                    $userAccess = new UserAccess();
//                    $userAccess->setUser($this);
//                    $userAccess->setService(UserAccess::SERVICE_HOMEPAGE);
//                    $userAccess->setServiceIdentifier($current);
//                    $userAccess->setAccessLevel($level);
//
//                    $this->accessList->add($userAccess);
//                }
//            }
//        }
//    }
}
