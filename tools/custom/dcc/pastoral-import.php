#!/usr/bin/php
<?php
/**
 * @author      Steve Chan <steve.chan@schoolbox.com.au>
 * @copyright   Copyright 2020, Schoolbox Pty Ltd
 * @link        http://www.schoolbox.com.au/
 */
declare(strict_types=1);

use Schoolbox\Application\CliTool;
use Schoolbox\Mapper;
use Schoolbox\Pastoral;
use Schoolbox\Role;
use Schoolbox\UserMapper;

require_once __DIR__ . '/../../../conf/core/require.php';

/**
 * CLI Tool
 */
$app = new class extends CliTool\App
{
    // --help: print help
    private const OPT_LONG_HELP = 'help';


    /**
     * All imported pastoral records will have this type
     */
    private const PASTORAL_TYPE = 'Learning Support';


    /** @inheritdoc */
    public function getLongOpts(): array
    {
        return [
            self::OPT_LONG_HELP,
        ];
    }


    /**
     * Run the cli tool
     */
    public function run(): void
    {
        if ($this->getOpt(self::OPT_LONG_HELP)) {
            echo <<<HELP
DCC Import pastoral records with attachments

Usage: pastoral-import.php

HELP;
            exit;
        }

        $this->processData(
            UserMapper::getInstance(),
            Pastoral\Mapper::getInstance(),
            Pastoral\Type\Mapper::getInstance()
        );
    }


    /**
     * Process all the data
     * @param UserMapper      $userMapper
    *  @param Pastoral\Mapper $pastoralMapper
     * @param Pastoral\Type\Mapper $pastoralTypeMapper
     */
    private function processData(
        UserMapper $userMapper,
        Pastoral\Mapper $pastoralMapper,
        Pastoral\Type\Mapper $pastoralTypeMapper
    ) {
        $externalDatabase = Schoolbox\Database\Factory::getInstanceExternal();

        if (!$externalDatabase) {
            $this->getLogger()->critical('Null external database for DCC Pastoral Importer; exiting');

            return;
        }

        $statement = $externalDatabase->prepareQuery("
            SELECT DISTINCT
                vd.ID, vd.Description, vd.DocumentCreatedDate, vd.DocumentType, vd.Document
            FROM dbo.media.vDocuments vd
            INNER JOIN dbo.vStudents vs ON vd.ID = vs.ID
            WHERE ClassificationCode = 'LearningSupport' and vs.StudentCampus = 'P'
        ");

        $statement->execute();

        $summary = [
            'success' => 0,
            'failure' => 0
        ];

        /* Find out if someone set the Pastoral Fallback User ID
         * If it's not set, that'll be a problem
         */
        $fallbackOwnerId = (int)$this->getConfig()->get('pastoral_fallback_owner');

        try {
            $fallbackOwner = $userMapper->findById($fallbackOwnerId);
        } catch (Mapper\Exception $e) {
            $this->getLogger()->critical(sprintf(
                'Pastoral Fallback User ID %s does not exist in schoolbox',
                $fallbackOwnerId
            ));

            exit();
        }

        /* Find the Pastoral Type "Learning Support" - it has to be called that exactly
         * If that isn't set, that'll be a problem
         */
        try {
            $pastoralType = $pastoralTypeMapper->findOneByColumn('name', self::PASTORAL_TYPE);
        } catch (Mapper\Exception $e) {
            $this->getLogger()->critical(sprintf(
                'Pastoral Type "%s" does not exist in schoolbox',
                self::PASTORAL_TYPE
            ));

            exit();
        }


        /* Otherwise continue assigning details per pastoral record
         */
        while ($row = $statement->fetch(\PDO::FETCH_ASSOC)) {

            /* If the student of the record doesn't exist, skip them with a warning
             */
            try {
                $student = $userMapper->findByExternalId($row['ID'], Role::STUDENT);
            } catch (Mapper\Exception $e) {
                $this->getLogger()->warning(sprintf(
                    'Student %s was not found in schoolbox; skipping their pastoral record "%s"',
                    $row['ID'],
                    $row['Description']
                ));

                $summary['failure']++;

                continue;
            }

            /* If the assigned user(s) couldn't be found, use a fallback user
             * FIXME The owner of the pastoral records is currently not defined
             *  The school needs to tell me where to find their pastoral record owners
             *  Their query doesn't include it
             */
            $owner = $fallbackOwner;

            try {
                $owner = $userMapper->findByExternalId($row['Owner'], Role::STAFF);
            } catch (Mapper\Exception $e) {
                $this->getLogger()->warning(sprintf(
                    'Owner external id %s was not found in schoolbox; attempting to assign fallback user id "%s"',
                    $row['Owner'],
                    $this->getConfig()->get('pastoral_fallback_owner')
                ));
            }

            /* Manufacture a pastoral record
             * Assign values to pastoral record
             */
            $record = $pastoralMapper->createModel()
                ->setStudent($student)
                ->setHeading($row['Description'])
                ->setOwner($owner)
                ->setType($pastoralType)
            ;

            /* Accept raw data into storage->accept()
             */

            /* Assign the storage file to the pastoral record
             */

            /* Save it
             */

            /* Log it
             */
        }
    }
};

 $options = [CliTool\Kernel::OPTION_LEVEL => Monolog\Logger::DEBUG, CliTool\Kernel::OPTION_ERRORS => E_ALL,];

/** @noinspection PhpUnhandledExceptionInspection */
(new Schoolbox\Application\CliTool\Kernel($app, $options))->runApp();
