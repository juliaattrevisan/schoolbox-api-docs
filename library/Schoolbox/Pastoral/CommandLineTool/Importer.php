<?php

/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Pastoral\CommandLineTool;

use External_Pastoral;
use Schoolbox\Application\CliTool;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\UserMapper;

/**
 * CLI Pastoral Importer
 *
 * Used when there are lots of records to import from the sis, because we have
 * an 1800sec timeout enforced by nginx that we can't override.
 */
class Importer extends CliTool\App
{
    /** @inheritDoc */
    public function getLongOpts(): array
    {
        return [
            'help',
            'user:',
            'default-owner:',
            'parent-access',
            'add-records',
            'add-attachments',
            'delete-records',
        ];
    }


    /** @inheritDoc */
    public function run(): void
    {
        /* Help
         */
        if ($this->getOpt('help')) {
            echo <<<HELP

Imports Pastoral records from external source. Primarily used if there are many records.

Usage: pastoralImport.php [--parent-access] [--add-records] [--add-attachments] [--user=1] [--default-owner=1]
    --parent-access:    give parents access to pastoral records
    --add-records:      import pastoral records
    --add-attachments:  import documents associated with pastoral records
    --delete-records:   if record exists, delete it and recreate. If not specified only non-existing records will be imported
    --user:             the user id of someone who should be associated with this import (typically a superuser)
    --default-owner:    the user id of someone who should be assigned as owner of pastoral records


HELP;

            exit(0);
        }

        /* command-line options
         */
        $importParentAccess = $this->getOpt('parent-access');
        $importRecords      = $this->getOpt('add-records');
        $importAttachments  = $this->getOpt('add-attachments');
        $deleteRecords      = $this->getOpt('delete-records');
        $defaultOwnerId     = $this->getOptAsInt('default-owner:');
        /** @var ?int $userId The user conducting the import (used in logging) */
        $userId = $this->getOptAsInt('user:');

        $log = $this->getLogger();
        $di  = $this->getDi();

        if (!$userId) {
            $log->critical('Invalid user id. Specify a user to whom the pastoral import is associated for logging purposes (typically a superuser)');
            exit(0);
        }

        $userMapper = new UserMapper();

        try {
            $user = $userMapper->findById($userId);
        } catch (MapperException $e) {
            $log->critical('Invalid user id. User not found.');
            exit(0);
        }

        if (!$defaultOwnerId) {
            $log->critical('Invalid default owner id. Specify a user to whom pastoral records are assigned.');
            exit(0);
        }

        try {
            $defaultOwner = $userMapper->findById($defaultOwnerId);
        } catch (MapperException $e) {
            $log->critical('Invalid default owner id. User not found');
            exit(0);
        }

        // Only process if pastoral docman writeback is actually enabled in config
        if (!$di->get('config')->get("external_pastoral")) {
            $log->critical('Pastoral external is disabled, exiting...');
            exit(0);
        }

        $log->info("Beginning import of pastoral records...");

        $pastoralExternal = new External_Pastoral();
        $pastoralExternal->importPastoral($user, $defaultOwner, $importParentAccess, $importRecords, $importAttachments, $deleteRecords);
    }
}
