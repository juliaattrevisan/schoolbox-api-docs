<?php

/**
 * @copyright   Copyright 2021, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Sync\Provisioner;

use Psr\Log\LoggerInterface;
use Schoolbox\Collection;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Resource\Folder;

/**
 * Class FinalUserAccess
 *
 * Get a list of UserAccess instances that a user is now involved with, after
 * all the UPS Group mutations have occurred
 *
 * @package Schoolbox\Sync\Provisioner
 */
class FinalUserAccess
{

    /**
     * Representations of the mutations that occurred, in chronological order
     */
    private const STATUS_INITIAL = 'initial';
    private const STATUS_ADD = 'add';
    private const STATUS_REMOVE = 'remove';


    /**
     * @var Folder\Mapper
     */
    private $folderMapper;


    /**
     * @var LoggerInterface|null
     */
    private $logger;


    /**
     * FinalUserAccess constructor.
     *
     * @param Folder\Mapper        $folderMapper
     * @param LoggerInterface|null $logger
     */
    public function __construct(Folder\Mapper $folderMapper, ?LoggerInterface $logger)
    {
        $this->folderMapper = $folderMapper;
        $this->logger = $logger;
    }


    /**
     * Using the mutation timeline, derive UserAccess instances
     * deciding whether to retain Full Access settings and/or create
     * new instances
     *
     * @param Collection $accessList
     * @param array      $accessMutations
     * @return array
     */
    public function generate(Collection $accessList, array $accessMutations): array
    {
        $this->log('Computing groups mutations');

        /* Decide the net effect, in accordance to how the mutation timeline evolved
         */
        $initialGroups = [];
        $mutationGroups = [];
        $finalGroups = [];

        /* The ruleset records mutations with [] as initial conditions, but that meant
         * what you started with would never show up in logs; initial conditions are
         * the list of userAccess instances you originally had and the mutations act
         * upon this
         */
        foreach ($accessList as $userAccess) {
            $folderId = $userAccess->getServiceIdentifier();

            $initialGroups[$folderId] = [
                'instance' => [$folderId],
                'level' => $userAccess->getAccessLevel(),
                'status' => 'initial'
            ];
        }

        /* Mutate initial conditions chronologically
         */
        foreach (array_merge($initialGroups, $accessMutations) as $mutation) {
            foreach ($mutation['instance'] as $folderId) {
                /* Maybe this folder doesn't actually exist at runtime
                 */
                try {
                    $folder = $this->folderMapper->findCheaplyById($folderId);
                    $folderName = $folder->getName();
                } catch (\Schoolbox\Mapper\Exception $e) {
                    continue;
                }

                /* Record the mutations
                 */
                if (self::STATUS_INITIAL === $mutation['status']) {
                    $this->log(sprintf(
                        "Mutation: %s %s (%s) with accesslevel %s\n",
                        $mutation['status'],
                        $folderId,
                        $folderName,
                        $mutation['level']
                    ));

                    $mutationGroups[$folderId] = $mutation['level'];
                } elseif (self::STATUS_ADD === $mutation['status']) {
                    $this->log(sprintf(
                        "Mutation: %s %s (%s) with accesslevel %s\n",
                        $mutation['status'],
                        $folderId,
                        $folderName,
                        $mutation['level']
                    ));

                    $mutationGroups[$folderId] = $mutation['level'];
                } elseif (self::STATUS_REMOVE === $mutation['status']) {
                    /* Did we retain this because of FULL_ACCESS preservation?
                     * If so, log the intended mutation anyway, but note that we're
                     * disregarding the intended mutation
                     *
                     * Otherwise remove it, as intended
                     */
                    $status = $mutation['status'];

                    /* Find the existing UserAccess instances and see if any have a ACCESS_FULL access level
                     * We want to keep that instance unchanged, because people are going to lose their Group Admin
                     * access and abilities, essentially locking them out of their own Groups en masse
                     */
                    if (isset($initialGroups[$folderId]) && ($initialGroups[$folderId]['level'] === UserAccess::ACCESS_FULL)) {
                        $status = 'retain';
                    } else {
                        unset($mutationGroups[$folderId]);
                    }

                    $this->log(sprintf(
                        "Mutation: %s %s (%s)\n",
                        $status,
                        $folderId,
                        $folderName,
                    ));
                }
            }
        }

        /* For every item listed in the mutation timeline, process a UserAccess
         * These are now the userAccesses the person should have
         */
        $this->log('Computing groups final list');

        foreach ($mutationGroups as $serviceIdentifier => $accessLevel) {
            /* If this person had ACCESS_FULL before, they still have it now
             * Don't mutate them
             */
            $status = '';
            $actualAccessLevel = $accessLevel;

            /* If we retain it, update the intent
             */
            if (isset($initialGroups[$serviceIdentifier]) && ($initialGroups[$serviceIdentifier]['level'] === UserAccess::ACCESS_FULL)) {
                $status = '(retain)';
                $actualAccessLevel = UserAccess::ACCESS_FULL;
            }

            $this->log(sprintf(
                "Final Groups: %s %s %s\n",
                $serviceIdentifier,
                $actualAccessLevel,
                $status
            ));

            /* Remember it for the log ui
             */
            $finalGroups[$serviceIdentifier] = $actualAccessLevel;
        }

        /* This is the list of groups to which this person has access
         */
        return $finalGroups;
    }


    /**
     * Optionally log a message
     *
     * @param string $message
     */
    private function log(string $message): void
    {
        if ($this->logger) {
            $this->logger->debug($message);
        }
    }
}
