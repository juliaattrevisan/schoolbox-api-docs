<?php

/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Pastoral\CommandLineTool;

use External_Offline_Pastoral;
use External_Pastoral;
use Schoolbox\Application\CliTool;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\Pastoral\Attachment\Mapper as PastoralAttachmentMapper;
use Schoolbox\Pastoral\Mapper as PastoralMapper;
use Schoolbox\Pastoral\UploadQueue\Mapper as PastoralUploadQueueMapper;

/**
 * This script manually syncs all pastoral care records within Schoolbox to
 * the external pastoral care system, if one is configured.
 */
class Exporter extends CliTool\App
{
    // -h, --help: print help
    private const OPT_SHORT_HELP = 'h';
    private const OPT_LONG_HELP = 'help';

    // --unexported-only: only sync records which do not have an external ID in
    // Schoolbox yet.
    private const OPT_LONG_UNEXPORTED_ONLY = 'unexported-only';

    // --repair-only: only sync records which have an external ID in Schoolbox,
    // but do not exist in the external pastoral care system.
    private const OPT_LONG_REPAIR_ONLY = 'repair-only';

    // --from: only import records created on or after this date.
    private const OPT_LONG_FROM = 'from:';

    // --to: only import records created on or after this date.
    private const OPT_LONG_TO = 'to:';

    /**
     * Number of pastoral records to fetch per batch.  Higher batch size requires more memory, but reduces run time.
     */
    private const BATCH_SIZE = 100;

    /** @inheritdoc */
    public function getShortOpts(): array
    {
        return [
            self::OPT_SHORT_HELP,
        ];
    }

    /** @inheritdoc */
    public function getLongOpts(): array
    {
        return [
            self::OPT_LONG_HELP,
            self::OPT_LONG_UNEXPORTED_ONLY,
            self::OPT_LONG_REPAIR_ONLY,
            self::OPT_LONG_FROM,
            self::OPT_LONG_TO,
        ];
    }

    /** @inheritDoc */
    public function run(): void
    {
        if ($this->getOpt(self::OPT_LONG_HELP) || $this->getOpt(self::OPT_SHORT_HELP)) {
            $this->printHelp();
            exit;
        }

        $log = $this->getLogger();

        if ($this->perform()) {
            $this->getLogger()->notice('Running in perform mode (-p): changes will be written to database');
        } else {
            $this->getLogger()->notice('Running in dry-run mode: use -p to write changes to database');
        }

        // Only process if pastoral docman writeback is actually enabled in config
        if (!$this->getConfig()->get("external_pastoral")) {
            $log->warning('Pastoral external is disabled, exiting...');
            exit;
        }

        $pastoralMapper            = PastoralMapper::getInstance();
        $pastoralAttachmentMapper  = PastoralAttachmentMapper::getInstance();
        $pastoralUploadQueueMapper = PastoralUploadQueueMapper::getInstance();
        $externalPastoral          = new External_Pastoral();

        $log->info("Using external database connector '" . $externalPastoral->getDatabaseClassName() . "'");

        if ($externalPastoral->getDatabaseClassName() === External_Offline_Pastoral::class) {
            $log->info('The current external db does not support pastoral exports; nothing will persist.');
        }

        $externalPastoralUpload    = $this->getConfig()->get(ConfigMapper::NAME_PASTORAL_EXTERNAL_UPLOAD);
        if ($externalPastoralUpload) {
            $log->info('Pastoral external attachment upload is enabled');
        } else {
            $log->info('Pastoral external attachment upload is disabled: not uploading attachments');
        }

        // Retrieve all pastoral records and re-save
        $log->info('Finding pastoral records to export...');
        $pb = $pastoralMapper->getPredicateBuilder();
        if ($this->getOpt(self::OPT_LONG_UNEXPORTED_ONLY)) {
            $log->info('* filtered to unexported records only');
            $pb = $pb->any(
                $pastoralMapper->getPredicateBuilder()
                    ->isNull('external_id')
                    ->equals('external_id', '')
            );
        }
        $fromDate = $this->buildDate($this->getOpt(self::OPT_LONG_FROM));
        if ($fromDate) {
            $this->getLogger()->info(
                sprintf('* filtered to records created on or after: "%s"', $fromDate->toRfc3339String()),
            );
            $pb = $pb->greaterThanOrEqualTo('created_at', $fromDate->getDatabase());
        }
        $toDate = $this->buildDate($this->getOpt(self::OPT_LONG_TO));
        if ($toDate) {
            $this->getLogger()->info(
                sprintf('* filtered to records created on or before: "%s"', $toDate->toRfc3339String()),
            );
            $pb = $pb->lessThanOrEqualTo('created_at', $toDate->getDatabase());
        }
        $pastoralRecordIds  = $pastoralMapper->findIdsByPredicates($pb, ['deleted' => false]);
        $nPastoralRecordIds = count($pastoralRecordIds);
        $log->info("Beginning export of $nPastoralRecordIds pastoral records...");

        $builder = $pastoralMapper->getPredicateBuilder();

        $failedPastoralIds = [];
        $nRecordsExported = 0;

        foreach (array_chunk($pastoralRecordIds, self::BATCH_SIZE) as $batchIndex => $batchOfPastoralRecordIds) {
            foreach ($pastoralMapper->findByPredicates($builder->in('id', $batchOfPastoralRecordIds)) as $pastoralRecord) {
                $log->debug("Syncing pastoral record (Pastoral ID #{$pastoralRecord->getId()})...");
                if ($this->perform()) {
                    try {
                        $syncSucceeded = $externalPastoral->syncPastoral($pastoralRecord, $this->getConfig()->get('external_pastoral_title_include'));
                    } catch (\Exception $e) {
                        $log->debug("Could not sync pastoral record (Pastoral ID #{$pastoralRecord->getId()}).  Error: {$e->getMessage()}");
                        $failedPastoralIds[] = $pastoralRecord->getId();
                        continue;
                    }

                    if (!$syncSucceeded) {
                        $log->debug("Pastoral record with external ID {$pastoralRecord->getExternalId()} could not be synced.");
                        $failedPastoralIds[] = $pastoralRecord->getId();
                        continue;
                    }
                    $log->debug("Pastoral record synced with external ID {$pastoralRecord->getExternalId()} was synced.");
                }

                $nRecordsExported++;

                // Re-process all attachments (only if attachment upload is on)
                if (!$externalPastoralUpload) {
                    continue;
                }

                // Note that only public attachments get synced
                $attachments = $pastoralAttachmentMapper->findByColumnList([
                    'pastoral_id' => $pastoralRecord->getId(),
                    'private'     => false,
                ]);
                if (count($attachments)) {
                    $log->debug(sprintf("Syncing %d pastoral record attachments", count($attachments)));
                    foreach ($attachments as $attachment) {
                        $log->debug(
                            sprintf('Syncing pastoral record attachment "%s"', $attachment->getStorage()->getBasename())
                        );

                        if ($this->perform()) {
                            // Only re-sync if it's not already in the Q
                            $queueCount = $pastoralUploadQueueMapper->countByColumnList([
                                'pastoral_id' => $pastoralRecord->getId(),
                                'hash'        => $attachment->getStorage()->getHash(),
                            ]);
                            if ($queueCount === 0) {
                                $pastoralAttachmentMapper->syncToPastoralUploadQueue($attachment, null);
                            }
                        }
                    }
                }
            }

            $nRecordsProcessed = min(($batchIndex + 1) * self::BATCH_SIZE, $nPastoralRecordIds);

            if ($nPastoralRecordIds > self::BATCH_SIZE) {
                $log->info("Processed $nRecordsProcessed records of $nPastoralRecordIds.  " . round(100 * $nRecordsProcessed / $nPastoralRecordIds) . '% complete.');
            }
        }

        $log->info("$nRecordsExported pastoral records exported successfully.");

        if (count($failedPastoralIds) > 0) {
            $log->info("PastoralIds that failed to export: " . implode(', ', $failedPastoralIds));
        }
    }

    /**
     * Print help text
     *
     * @return void
     */
    private function printHelp(): void
    {
        $file       = $this->getToolFilename();
        $green      = self::COLOUR_GREEN;
        $white      = self::COLOUR_WHITE;
        $yellow     = self::COLOUR_YELLOW;
        echo <<<HELP
NAME:
    Pastoral record exporter

SYNOPSIS:
    $green{$file} {$yellow}[OPTION]...$white

DESCRIPTION
    Exports all pastoral care records within Schoolbox to the external pastoral care system, if one is configured.

OPTIONS
    $yellow-h, --help$white
        Print this help and exit

    $yellow-p, --perform$white
        Actually export records: otherwise, only output records which would be exported.

    $yellow--unexported-only$white
        If provided, only attempt to export records which have been not yet been exported to the external pastoral care system.

        Mutually exclusive with $yellow--repair-only$white: if neither is provided, all records will be exported.

    $yellow--repair-only$white
        If provided, only attempt to export records which have been recorded as exported to the external pastoral care system, but don't exist within it:
        this attempts to repair exports which might have worked only partially.

        Mutually exclusive with $yellow--unsynced-only$white.

    $yellow--from=FROM_DATE$white
        If provided, only export records created from {$yellow}FROM_DATE{$white} onward.

        {$yellow}FROM_DATE{$white} will be parsed according to the following rules:
        * as a database date (YYYY-MM-DD HH:MM:SS)
        * as a Unix timestamp
        * via `strtotime`

        This script will fail if {$yellow}FROM_DATE{$white} is not parseable.

    $yellow--to=TO_DATE$white
        If provided, only export records created on {$yellow}TO_DATE{$white} and before.

        {$yellow}TO_DATE{$white} will be parsed using the same rules as {$yellow}FROM_DATE{$white}, and will cause this script to fail for the same reasons.


HELP;
    }
}
