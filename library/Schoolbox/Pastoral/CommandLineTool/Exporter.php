<?php
/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Pastoral\CommandLineTool;

use External_Pastoral;
use Schoolbox\Application\CliTool;
use Schoolbox\Pastoral\Attachment\Mapper as PastoralAttachmentMapper;
use Schoolbox\Pastoral\Mapper as PastoralMapper;
use Schoolbox\Pastoral\UploadQueue\Mapper as PastoralUploadQueueMapper;

/**
 * This script manually syncs all pastoral care records within Schoolbox to
 * the external pastoral care system, if one is configured.
 */
class Exporter extends CliTool\App
{
    // --help: print help
    private const OPT_LONG_HELP = 'help';


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

Syncs all pastoral care records within Schoolbox to the external pastoral care system, if one is configured.

Usage: pastoralExport.php


HELP;
            exit;
        }

        $log = $this->getLogger();

        // Only process if pastoral docman writeback is actually enabled in config
        if (!$this->getConfig()->get("external_pastoral")) {
            $log->warning('Pastoral external is disabled, exiting...');
            exit;
        }

        $pastoralMapper            = PastoralMapper::getInstance();
        $pastoralAttachmentMapper  = PastoralAttachmentMapper::getInstance();
        $pastoralUploadQueueMapper = PastoralUploadQueueMapper::getInstance();
        $externalPastoral          = new External_Pastoral();
        $externalPastoralUpload    = $this->getConfig()->get("external_pastoral_upload");
        if ($externalPastoralUpload) {
            $log->info('Pastoral external attachment upload is enabled');
        } else {
            $log->info('Pastoral external attachment upload is disabled: not uploading attachments');
        }

        // Retrieve all pastoral records and re-save
        $log->info("Beginning export of pastoral records...");
        $pastoralRecords = $pastoralMapper->findAll();

        foreach ($pastoralRecords as $pastoralRecord) {
            $log->debug("Syncing pastoral record (Pastoral ID #{$pastoralRecord->getId()})...");
            $externalPastoral->syncPastoral($pastoralRecord);
            $log->debug("Pastoral record synced (External ID #{$pastoralRecord->getExternalId()})...");

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

        // And done
        $log->info(sprintf("%s pastoral records exported", count($pastoralRecords)));
    }
}
