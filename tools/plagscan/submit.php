#!/usr/bin/php
<?php

/**
 * This script will take plagscan documents in an queued state and attempt to transmit them to
 * plagscan so they can be processed. Use this if you have lots of files in the queued state.
 *
 * @author           James Leckie <jleckie@alaress.com.au>
 * @copyright        Copyright 2019, Schoolbox Pty Ltd
 */

use Schoolbox\Assessment\Component\SubmissionBox\Submission\SingleFile\Mapper as StudentSubmissionFileMapper;
use Schoolbox\Assessment\Component\SubmissionBox\Submission\SingleFile\Model as StudentSubmissionFile;
use Schoolbox\Learning\Assessment\DueWork\Response\File\Plagscan\Mapper as PlagscanMapper;
use Schoolbox\Learning\Assessment\DueWork\Response\File\Plagscan\Model as Plagscan;
use Schoolbox\Application\CliTool;

require_once __DIR__ . '/../../conf/core/require.php';

$app = new class extends CliTool\App {

    // --help: print help
    private const OPT_LONG_HELP = 'help';


    /** @inheritDoc */
    public function getLongOpts(): array
    {
        return [
            self::OPT_LONG_HELP,
            're-retrieve'
        ];
    }

    /** @inheritDoc */
    public function run(): void
    {
        if ($this->getOpt(self::OPT_LONG_HELP)) {
            echo <<<HELP

Re-uploads failed documents into plagscan so it can try processing again.

Usage: submit.php


HELP;
            exit;
        }

        $log = $this->getLogger();

        $mapper = PlagscanMapper::getInstance();

        if (!$mapper->getConnector()) {
            /* Plagscan not configured - nothing to do
             */
            $log->warning('Plagscan scan did not run: username, api key and/or access token is not configured correctly');
            exit();
        }

        $allDocuments = $mapper->findByColumn('status', Plagscan::STATUS_QUEUED, [
            'order' => [
                'student_submission_file_id' => 'ASC',
            ],
        ]);

        $log->info("Plagscan now re-transmitting {$allDocuments->count()} documents");

        $numUpdated = 0;

        /** @var Plagscan $currentDocument */
        foreach ($allDocuments as $currentDocument) {
            $prefix = "Plagscan Record {$currentDocument->getId()}";

            try {
                $plagscanId = null;

                /** @var StudentSubmissionFile $studentSubmissionFile */
                $studentSubmissionFile = StudentSubmissionFileMapper::getInstance()->findById($currentDocument->getStudentSubmissionFileId());

                /* This returns a plagscan id upon transmission
                 */
                $log->info("{$prefix}: transmitting student_submission_file_id {$currentDocument->getStudentSubmissionFileId()}");

                /* Figure out what the filename will be
                 */
                $filename = $mapper->plagscanFilename($studentSubmissionFile);

                if ($filename !== null) {
                    /* Privatise all previous incarnations of this (assessment, student) tuple to
                     * prevent documents from being scanned against older versions of themselves
                     */
                    $previousPlagscans = $mapper->findPreviousIncarnationsOf($studentSubmissionFile->getId());

                    /** @var Plagscan $currentPlagscan */
                    foreach ($previousPlagscans as $currentPlagscan) {
                        $log->info("{$prefix}: privatising {$currentPlagscan->getPlagscanId()} - superseded by student_submission_file_id {$currentDocument->getStudentSubmissionFileId()}");
                        $mapper->privatise($currentPlagscan);
                    }

                    /* Transmit the new file
                     */
                    try {
                        $plagscanId = $mapper->getConnector()->transmit($studentSubmissionFile, $filename);

                        /* Update the db with this new plagscan id */
                        if ($plagscanId) {
                            $log->info("{$prefix}: transmission successful, document set to processing state: " . Plagscan::STATUS_PROCESSING);

                            $currentDocument->setPlagscanId($plagscanId);
                            $currentDocument->setStatus(Plagscan::STATUS_PROCESSING);
                            $currentDocument->setUpdatedAt(\Alaress_Date_Format::now());
                            $mapper->save($currentDocument);

                            $numUpdated++;
                        } else {
                            $log->warning("{$prefix}: transmission did not return a plagscanId for submission file {$currentDocument->getStudentSubmissionFileId()}");
                        }
                    } catch (\RuntimeException | \InvalidArgumentException $e) {
                        /* Transient errors can get logged; transmit() retry is allowed later
                         *
                         * These are fixable things like plagscan being unavailable temporarily,
                         * or their api signature(s) have changed, etc. without having to ban the
                         * plagscan document entirely
                         *
                         * DomainExceptions are not going to so readily change;
                         * such as filesize limits, filetypes, files not existing, other unrecoverables
                         */
                        $log->error($e->getMessage());
                    }
                } else {
                    $log->warning("{$prefix}: unknown join criteria for file/assessment/user");
                }
            } catch (Schoolbox\Mapper\Exception $e) {
                $log->warning("{$prefix}: unable to find student_submission_file_id {$currentDocument->getStudentSubmissionFileId()}");

                /* Couldn't find the file or any of its pieces
                 * So we continue instead, ignoring this plagscan doc
                 */
                continue;
            } catch (\InvalidArgumentException $e) {
                /* The PlagScan API needs to be updated
                 * Immediately exit
                 */
                $log->error("{$prefix}: PlagScan API needs to be updated. {$e->getMessage()}; exiting immediately");
                exit(1);
            } catch (\DomainException $e) {
                /* Could not transmit this file to plagscan for some reason so remove it from the queue
                 */
                $currentDocument->setStatus(Plagscan::STATUS_UNSCANNABLE);
                $mapper->save($currentDocument);
                $log->error("{$prefix}: could not transmit student_submission_file_id {$currentDocument->getStudentSubmissionFileId()}. {$e->getMessage()}");
            } catch (\RuntimeException $e) {
                /* Could not transmit this file to plagscan for some reason
                 * RuntimeException = Plagscan is unusable = immediate exit
                 */
                $log->error("{$prefix}: could not transmit student_submission_file_id {$currentDocument->getStudentSubmissionFileId()}. {$e->getMessage()} (runtime exception); exiting immediately");
                exit(1);
            }
        }

        $log->info("Plagscan transmission completed. {$numUpdated} document(s) were sent to plagscan and are pending processing");

        /* Also tell me how many died
         */
        $numFailed = $allDocuments->count() - $numUpdated;

        if ($numFailed) {
            $log->info("{$numFailed} document(s) failed transmitting");
        }
    }
};

$kernel = new CliTool\Kernel($app, [CliTool\Kernel::OPTION_LEVEL => Monolog\Logger::INFO]);
$kernel->runApp();
