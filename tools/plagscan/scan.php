#!/usr/bin/php
<?php
/**
 * Plagscan scanner
 *
 * Proactively queries Plagscan (instead of relying on its callback) for scans
 * on documents passed to it.
 *
 * @author           Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright        Copyright 2019, Schoolbox Pty Ltd
 * @link             https://schoolbox.com.au/
 * @idea_stolen_from Matt Day <matt.day@alaress.com.au>
 */

use Schoolbox\File\Exception as FileException;
use Schoolbox\Learning\Assessment\DueWork\Response\File\Plagscan\Mapper as PlagscanMapper;
use Schoolbox\Learning\Assessment\DueWork\Response\File\Plagscan\Model as Plagscan;
use Schoolbox\Learning\Assessment\DueWork\Response\File\Plagscan\v3\ReportTransactor as PlagscanReportTransactor;
use Schoolbox\Resource\Storage\Mapper as StorageMapper;
use Schoolbox\Application\CliTool;

require_once __DIR__ . '/../../conf/core/require.php';

$app = new class extends CliTool\App
{
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
    public function getRequiredProcessUser(): ?string
    {
        return 'www-data';
    }


    /** @inheritDoc */
    public function run(): void
    {
        if ($this->getOpt(self::OPT_LONG_HELP)) {
            echo <<<HELP

Proactively queries Plagscan (instead of relying on its callback) for scans on documents.
Updates our db with status changes and other plagscan report metadata when appropriate.

Usage: scan.php


HELP;
            exit;
        }

        $log = $this->getLogger();

        $mapper = PlagscanMapper::getInstance();
        $storageMapper = StorageMapper::getInstance();

        if (!$mapper->getConnector()) {

            /* Plagscan not configured - nothing to do
             */
            $log->warning('Plagscan scan did not run: username, api key and/or access token is not configured correctly');
            exit();
        }

        $log->info('Plagscan is now choosing modes and options');

        $reRetrieveMode = $this->getOpt('re-retrieve');

        if ($reRetrieveMode) {

            /* Re-scan plagscan records
             */
            $log->info('Scanner running in re-retrieve mode');
            $predicate = $mapper->getPredicateBuilder();
            $predicate->equals('status', Plagscan::STATUS_COMPLETE);
            $predicate->greaterThan('plagscan_id', 0);
            $allDocuments = $mapper->findByPredicates($predicate);
        }
        else {

            /* Scan currently-pending plagscan records
             */
            $log->info('Scanner running in scan mode');
            $predicate = $mapper->getPredicateBuilder();
            $predicate->equals('status', Plagscan::STATUS_PROCESSING);
            $predicate->greaterThan('plagscan_id', 0);
            $allDocuments = $mapper->findByPredicates($predicate);
        }

        $log->info("Plagscan now processing {$allDocuments->count()} documents");

        $numUpdated = 0;

        /** @var Plagscan $currentDocument */
        foreach ($allDocuments as $currentDocument) {
            $prefix = "Plagscan Record {$currentDocument->getId()}";

            /* Sometimes plagscan gives you back -1 as the plagscan_id
             * In this case, just skip it, so this tool doesn't get
             * stuck trying to process the unprocessable
             *
             * I am also going to assume that $currentDocument->getPlagscanId()
             *  has to be greater than zero. I can't think of why zero would be valid.
             */
            if ($currentDocument->getPlagscanId() > 0) {
                try {
                    /* Tells plagscan to analyse the document
                     * Plagscan will do it in its own time, whenever it wants to
                     * We have to poll it to find out its actual status
                     */
                    $reportReady = $mapper->getConnector()->analyse($currentDocument->getPlagscanId());

                    if ($reportReady) {
                        $interactiveReportUrl = $mapper->getConnector()->fetchInteractiveReportUrl($currentDocument->getPlagscanId());
                        $reportContents = $mapper->getConnector()->fetchReport($currentDocument->getPlagscanId());
                        $score = $mapper->getConnector()->fetchScore($currentDocument->getPlagscanId());

                        /* Update the db with the report contents for this document
                         * only when we have all three pieces (each seems to generate
                         * independently of each other on plagscan's end, so one might
                         * be available before the others etc.)
                         */
                        if (strlen($interactiveReportUrl) && strlen($reportContents) && strlen($score)) {
                            try {
                                $plagscanReport = (new PlagscanReportTransactor($currentDocument->getPlagscanId(), $reportContents, $storageMapper))->save();

                                $currentDocument->setPlagscanId($currentDocument->getPlagscanId());
                                $currentDocument->setStatus(Plagscan::STATUS_COMPLETE);
                                $currentDocument->setScore($score);
                                $currentDocument->setStorage($plagscanReport);
                                $currentDocument->setInteractiveUrl($interactiveReportUrl);
                                $currentDocument->setUpdatedAt(\Alaress_Date_Format::now());
                                $mapper->save($currentDocument);
                                $numUpdated++;
                            }
                            catch (FileException $e) {
                                /* Die critically until the plagscan report can be saved
                                 */
                                $log->critical("{$prefix}: could not save plagscan report file to storage", ['exception'=>$e]);
                            }
                        } else {
                            $log->warning("{$prefix}: all report pieces (score, mode 6 and 7 reports) not yet ready");
                        }
                    } else {
                        $log->warning("{$prefix}: report not yet analysed");
                    }
                } catch (\InvalidArgumentException $e) {
                    /* The PlagScan API needs to be updated
                     * Immediately exit
                     */
                    $log->error("{$prefix}: PlagScan API needs to be updated. {$e->getMessage()}; exiting immediately");
                    exit(1);
                } catch (\DomainException $e) {
                    $currentDocument->setStatus(Plagscan::STATUS_UNSCANNABLE);
                    $mapper->save($currentDocument);

                    $log->error("{$prefix} unscannable - {$e->getMessage()} (domain exception)");
                } catch (\RuntimeException $e) {
                    $log->error("{$prefix} unscannable - {$e->getMessage()} (runtime exception); exiting immediately");
                    exit(1);
                }
            }
        }

        $log->notice("Plagscan scan completed. {$numUpdated} documents were scanned");

        /* Also tell me how many died
         */
        $numFailed = $allDocuments->count() - $numUpdated;

        if ($numFailed) {
            $log->notice("{$numFailed} documents failed scanning or analysis is still in progress");
        }
    }
};

$kernel = new CliTool\Kernel($app, [CliTool\Kernel::OPTION_LEVEL => Monolog\Logger::INFO]);
$kernel->runApp();
