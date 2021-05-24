#!/usr/bin/php
<?php

/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

use Alaress\Database\Quoter\Mysql\Quoter;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Schoolbox\Resource\Folder\Model as FolderModel;
use Schoolbox\Application\CliTool;

require_once __DIR__ . '/../conf/core/require.php';

/**
 * Folder Cleanup
 *
 * Folders that have no discernible parent id are considered orphans and are moved into a
 * folder titled "Lost & Found" ($folderName)
 *
 * Folders that are self-referential (cyclical) are also treated as orphans and moved into
 * this folder.
 */
$app = new class extends CliTool\App
{
    // --help: print help
    private const OPT_LONG_HELP = 'help';

    /**
     * The lost and found folder's name
     */
    private const FOLDER_NAME = 'Lost & Found';


    /** @inheritdoc */
    public function getLongOpts(): array
    {
        return [
            self::OPT_LONG_HELP,
        ];
    }

    /** @inheritDoc */
    public function run(): void
    {
        if ($this->getOpt(self::OPT_LONG_HELP)) {
            echo <<<HELP

Illegal folders are moved into a folder called "Lost & Found". A folder with this exact name must exist.

Illegal folders are ones which have self-referential parent_ids (ie. a folder is its own parent) and folders
which have a nonexistent parent (orphaned folders). Folders who are their own parent are called "looping folders".

Usage: folder-cleanup.php


HELP;
            exit;
        }

        $db      = $this->getDatabaseInternal();
        $logger  = $this->getLogger();

        $folderName = $db->getQuoter()->quoteIdentifier(self::FOLDER_NAME);

        $lostQuery = $db->prepareQuery('SELECT `id` FROM `folder` WHERE `name` = :folder_name');
        $lostQuery->execute(['folder_name' => self::FOLDER_NAME]);

        if ($lostQuery->rowCount() > 0) {
            $row = $lostQuery->fetch();
            $parent_id = $row['id'];
        } else {
            $db->executeQuery('INSERT INTO `folder` (name, parent, inherit) VALUES (:folder_name, 0, 0)', [ 'folder_name' => $folderName]);
            $parent_id = $db->lastInsertId();
        }

        $moveQuery = $db->prepareQuery('UPDATE `folder` SET `parent` = :parent_id WHERE `id` = :id;');

        // Do a recursive lookup on every item and make sure the ancestry is valid

        $folderMapper = FolderMapper::getInstance();

        do {
            $errors = 0;

            $folders = $folderMapper->findAll();

            /** @var FolderModel $folder */
            foreach ($folders as $folder) {
                // Keep track of nodes we have traversed so we don't end up in a loop

                $folderCurrent = $folder;
                $found = [];

                try {
                    while ($folderCurrent->getParent()->getId() != 0) {
                        $found[$folderCurrent->getId()] = 1;

                        /** @var FolderModel $folderCurrent */
                        $folderCurrent = $folderMapper->findCheaplyById($folderCurrent->getParent()->getId());

                        if ($folderCurrent->getParent()->getId() == null) {
                            // Node has no parent set

                            $logger->info(sprintf(
                                "Orphaned Folder '%s' (%d) moved to {$folderName} (%d)",
                                $folder->getName(),
                                $folder->getId(),
                                $parent_id
                            ));

                            $moveQuery->execute([
                                'parent_id' => $parent_id,
                                'id' => $folder->getId()
                            ]);

                            $errors++;

                            break;
                        } elseif (isset($found[$folderCurrent->getId()])) {
                            // Found a looping family tree

                            $loopString = "";

                            foreach ($found as $id => $item) {
                                $loopString .= "{$id},";
                            }

                            $loopString .= $folderCurrent->getId();

                            $logger->info(sprintf(
                                "Looping Folder '%s' (%s) moved to {$folderName} (%d)",
                                $folder->getName(),
                                $loopString,
                                $parent_id
                            ));

                            $moveQuery->execute([
                                'parent_id' => $parent_id,
                                'id' => $folder->getId()
                            ]);

                            $errors++;

                            break;
                        }
                    }
                } catch (\Exception $e) {
                    $logger->info(sprintf(
                        "Folder '%s' (%d) missing Parent (%d) moved to {$folderName} (%d)",
                        $folder->getName(),
                        $folder->getId(),
                        $folder->getParent()->getId(),
                        $parent_id
                    ));

                    $moveQuery->execute([
                        'parent_id' => $parent_id,
                        'id' => $folder->getId()
                    ]);

                    $errors++;
                }
            }
        } while ($errors > 0);
    }
};

$kernel = new CliTool\Kernel($app, [CliTool\Kernel::OPTION_LEVEL => Monolog\Logger::INFO]);
$kernel->runApp();
