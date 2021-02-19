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
     * @param \Schoolbox\Resource\Folder\Mapper $folderMapper
     * @param LoggerInterface                   $logger
     * @return array
     * @throws \InvalidArgumentException
     * @throws \Schoolbox\Mapper\DefinitionException
     */
    public function generateExistingUserAccess(\Schoolbox\Resource\Folder\Mapper $folderMapper, LoggerInterface $logger): array
    {
        /* Find out what you started with
         */
        $existingGroups = [];

        foreach ($this->getAccessList() as $userAccess) {
            $folderId = $userAccess->getServiceIdentifier();

            $existingGroups[$folderId] = $userAccess->getAccessLevel();

            /* Maybe this folder doesn't actually exist at runtime
             */
            try {
                $folder = $folderMapper->findCheaplyById($folderId);

                $logger->debug(sprintf(
                    "Existing Group: %s (%s) with accesslevel %s\n",
                    $folder->getId(),
                    $folder->getName(),
                    $userAccess->getAccessLevel()
                ));
            } catch (\Schoolbox\Mapper\Exception $e) {
                $logger->debug(sprintf(
                    "Existing Group: %s (%s) with accesslevel %s\n",
                    $folderId,
                    'unknown folder',
                    $userAccess->getAccessLevel()
                ));
            }
        }

        return $existingGroups;
    }


    /**
     * Using the mutation timeline, derive UserAccess instances
     * deciding whether to retain Full Access settings and/or create
     * new instances
     *
     * @param \Schoolbox\Resource\Folder\Mapper $folderMapper
     * @param LoggerInterface                   $logger
     * @return array
     * @throws \InvalidArgumentException
     * @throws \Schoolbox\Mapper\DefinitionException
     * @throws \Schoolbox\Mapper\Exception
     */
    public function generateFinalUserAccess(\Schoolbox\Resource\Folder\Mapper $folderMapper, LoggerInterface $logger): array
    {

        /* Decide the net effect, in accordance to how the mutation timeline evolved
         */
        $mutationGroups = [];

        foreach ($this->getUserAccessMutations() as $mutation) {
            foreach($mutation['instance'] as $folderId) {

                /* Maybe this folder doesn't actually exist at runtime
                 */
                try {
                    $folder = $folderMapper->findCheaplyById($folderId);

                    $folderName = $folder->getName();
                } catch (\Schoolbox\Mapper\Exception $e) {
                    $folderName = 'unknown folder';
                }

                /* Record the mutations
                 */
                if ($mutation['status'] === 'add') {
                    $logger->debug(sprintf(
                        "Groups: %s %s (%s) with accesslevel %s\n",
                        $mutation['status'],
                        $folderId,
                        $folderName,
                        $mutation['level']
                    ));

                    $mutationGroups[$folderId] = $mutation['level'];
                } elseif ($mutation['status'] === 'remove') {
                    $logger->debug(sprintf(
                        "Groups: %s %s (%s)\n",
                        $mutation['status'],
                        $folderId,
                        $folderName,
                    ));

                    unset($mutationGroups[$folderId]);
                }
            }
        }

        /* Find the existing UserAccess instances and see if any have a ACCESS_FULL access level
         * We want to keep that instance unchanged, because people are going to lose their Group Admin
         * access and abilities, essentially locking them out of their own Groups en masse
         */
        $keep = $this->getAccessList()
            ->filterBy('service', UserAccess::SERVICE_HOMEPAGE)
            ->filterBy('accessLevel', UserAccess::ACCESS_FULL)
            ->indexBy('serviceIdentifier');

        /* For every item listed in the mutation timeline, process a UserAccess
         * These are now the userAccesses the person should have
         */
        $finalGroups = [];

        foreach ($mutationGroups as $serviceIdentifier => $accessLevel) {

            /* If this person had ACCESS_FULL before, they still have it now
             * Don't mutate them
             */
            if (isset($keep[$serviceIdentifier])) {
                $logger->debug(sprintf(
                    "Retained full access to %s\n",
                    $serviceIdentifier
                ));

                /* Remember it for the log ui
                 */
                $finalGroups[$serviceIdentifier] = $keep[$serviceIdentifier]->getAccessLevel();

                continue;
            }

            /* Otherwise change their stuff
             */
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

            /* Remember it for the log ui
             */
            $finalGroups[$serviceIdentifier] = $accessLevel;
        }

        /* This is the list of groups to which this person has access
         */
        return $finalGroups;
    }
}
