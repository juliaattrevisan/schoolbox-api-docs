<?php
/**
 * @copyright   Copyright 2021, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Sync\Provisioner;

use Psr\Log\LoggerInterface;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Permission\Access\UserAccessMapper;


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
     * @var \Schoolbox\Resource\Folder\Mapper
     */
    private $folderMapper;


    /**
     * @var LoggerInterface|null
     */
    private $logger;


    /**
     * FinalUserAccess constructor.
     *
     * @param \Schoolbox\Resource\Folder\Mapper $folderMapper
     * @param LoggerInterface|null              $logger
     */
    public function __construct(\Schoolbox\Resource\Folder\Mapper $folderMapper, ?LoggerInterface $logger)
    {
        $this->folderMapper = $folderMapper;
        $this->logger = $logger;
    }


    /**
     * Using the mutation timeline, derive UserAccess instances
     * deciding whether to retain Full Access settings and/or create
     * new instances
     *
     * @param User $user
     * @return array
     * @throws \InvalidArgumentException
     * @throws \Schoolbox\Mapper\DefinitionException
     * @throws \Schoolbox\Mapper\Exception
     * @throws \UnexpectedValueException
     */
    public function generate(User $user): array
    {
        $this->log('Computing groups mutations');

        /* Decide the net effect, in accordance to how the mutation timeline evolved
         */
        $initialGroups = [];
        $mutationGroups = [];

        /* The ruleset records mutations with [] as initial conditions, but that meant
         * what you started with would never show up in logs; initial conditions are
         * the list of userAccess instances you originally had and the mutations act
         * upon this
         */
        foreach ($user->getAccessList() as $userAccess) {
            $folderId = $userAccess->getServiceIdentifier();

            $initialGroups[$folderId] = [
                'instance' => [$folderId],
                'level' => $userAccess->getAccessLevel(),
                'status' => 'initial'
            ];
        }

        /* Mutate initial conditions chronologically
         */
        foreach (array_merge($initialGroups, $user->getUserAccessMutations()) as $mutation) {
            foreach($mutation['instance'] as $folderId) {

                /* Maybe this folder doesn't actually exist at runtime
                 */
                try {
                    $folder = $this->folderMapper->findCheaplyById($folderId);
                    $folderName = $folder->getName();
                } catch (\Schoolbox\Mapper\Exception $e) {
                    $folderName = 'unknown folder';
                }

                /* Record the mutations
                 */
                if ($mutation['status'] === 'initial') {
                    $this->log(sprintf(
                        "Mutation: %s %s (%s) with accesslevel %s\n",
                        $mutation['status'],
                        $folderId,
                        $folderName,
                        $mutation['level']
                    ));

                    $mutationGroups[$folderId] = $mutation['level'];
                } elseif ($mutation['status'] === 'add') {
                    $this->log(sprintf(
                        "Mutation: %s %s (%s) with accesslevel %s\n",
                        $mutation['status'],
                        $folderId,
                        $folderName,
                        $mutation['level']
                    ));

                    $mutationGroups[$folderId] = $mutation['level'];
                } elseif ($mutation['status'] === 'remove') {
                    $this->log(sprintf(
                        "Mutation: %s %s (%s)\n",
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
        $keep = $user->getAccessList()
            ->filterBy('service', UserAccess::SERVICE_HOMEPAGE)
            ->filterBy('accessLevel', UserAccess::ACCESS_FULL)
            ->indexBy('serviceIdentifier');

        /* For every item listed in the mutation timeline, process a UserAccess
         * These are now the userAccesses the person should have
         */
        $this->log('Computing groups final list');

        $finalGroups = [];

        foreach ($mutationGroups as $serviceIdentifier => $accessLevel) {

            /* If this person had ACCESS_FULL before, they still have it now
             * Don't mutate them
             */
            if (isset($keep[$serviceIdentifier])) {
                $this->log(sprintf(
                    "Final Groups: %s %s (retained)\n",
                    $serviceIdentifier,
                    $keep[$serviceIdentifier]->getAccessLevel()
                ));

                /* Remember it for the log ui
                 */
                $finalGroups[$serviceIdentifier] = $keep[$serviceIdentifier]->getAccessLevel();

                continue;
            }

            $this->log(sprintf(
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