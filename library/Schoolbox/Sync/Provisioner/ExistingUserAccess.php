<?php

/**
 * @copyright   Copyright 2021, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Sync\Provisioner;

use Psr\Log\LoggerInterface;
use Schoolbox\Collection;

/**
 * Class ExistingUserAccess
 *
 * Get a list of UserAccess instances for a given user
 * This is used for diff
 *
 * @package Schoolbox\Sync\Provisioner
 */
class ExistingUserAccess
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
     * ExistingUserAccess constructor.
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
     * Find the current set of userAccess instances
     *
     * @param Collection $existingUserAccessList
     * @return array
     */
    public function generate(Collection $existingUserAccessList): array
    {

        /* Find out what you started with
         */
        $existingGroups = [];

        foreach ($existingUserAccessList as $userAccess) {
            $folderId = $userAccess->getServiceIdentifier();

            /* Maybe this folder doesn't actually exist at runtime
             */
            try {
                $folder = $this->folderMapper->findCheaplyById($folderId);
                $existingGroups[$folderId] = $userAccess->getAccessLevel();
                $folderName = $folder->getName();
            } catch (\Schoolbox\Mapper\Exception $e) {
                continue;
            }

            $this->log(sprintf(
                "Existing Group: %s (%s) with accesslevel %s\n",
                $folderId,
                $folderName,
                $userAccess->getAccessLevel()
            ));
        }

        return $existingGroups;
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
