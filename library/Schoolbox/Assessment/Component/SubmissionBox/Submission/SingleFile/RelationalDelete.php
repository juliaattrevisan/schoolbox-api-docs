<?php

/**
 * @copyright Copyright 2022, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Assessment\Component\SubmissionBox\Submission\SingleFile;

use Psr\Log\LoggerInterface;
use Schoolbox\GenericCollection;
use Schoolbox\Learning\Assessment\DueWork\Response\File\Plagscan;
use Schoolbox\Learning\InlineGrader\Kami;
use Schoolbox\Learning\Turnitin;
use Schoolbox\Mapper;
use Schoolbox\Resource\Storage\RelationalDelete\Deletable;
use Schoolbox\Assessment\Component\SubmissionBox\Submission;

class RelationalDelete extends Deletable
{
    private Turnitin\File\Mapper $turnitinReportMapper;

    private Plagscan\Mapper $plagscanDocumentMapper;

    private Kami\Mapper $kamiMapper;

    public function __construct(
        Submission\SingleFile\Mapper $mapper,
        LoggerInterface $logger,
        Turnitin\File\Mapper $turnitinReportMapper,
        Plagscan\Mapper $plagscanDocumentMapper,
        Kami\Mapper $kamiMapper,
    ) {
        parent::__construct($mapper, $logger);
        $this->turnitinReportMapper   = $turnitinReportMapper;
        $this->plagscanDocumentMapper = $plagscanDocumentMapper;
        $this->kamiMapper             = $kamiMapper;
    }

    /**
     * Will need to first hard-delete any `turnitin_report` or `plagscan_document` records that reference the `student_submission_file`
     *
     * @inheritDoc
     */
    public function remove(string $hash, bool $performDryRun): void
    {
        /** @var GenericCollection|Model[] $studentSubmissionFiles */
        $studentSubmissionFiles   = $this->mapper->findByColumn($this->hashColumn, $hash, ['deleted' => true])->indexBy('id');
        $studentSubmissionFileIds = $studentSubmissionFiles->getPropertyValues('id');

        if ($studentSubmissionFiles->count() > 0) {
            // Fetch the related TII/Plagscan/Kami models
            $turnitinReports   = $this->turnitinReportMapper->findByColumn('student_submission_file_id', $studentSubmissionFileIds, ['deleted' => true]);
            $plagscanDocuments = $this->plagscanDocumentMapper->findByColumn('student_submission_file_id', $studentSubmissionFileIds, ['deleted' => true]);
            $kamiDocs          = $this->kamiMapper->findByColumn('student_submission_file_id', $studentSubmissionFileIds, ['deleted' => true]);

            if ($performDryRun) {
                if ($turnitinReports->count()) {
                    $this->logger->notice("Will delete [{$turnitinReports->count()}] record(s) in: {$this->turnitinReportMapper->getTable()}", ['char' => '-']);
                }
                if ($plagscanDocuments->count()) {
                    $this->logger->notice("Will delete [{$plagscanDocuments->count()}] record(s) in: {$this->plagscanDocumentMapper->getTable()}", ['char' => '-']);
                }
                if ($kamiDocs->count()) {
                    $this->logger->notice("Will delete [{$kamiDocs->count()}] record(s) in: {$this->kamiMapper->getTable()}", ['char' => '-']);
                }
                $this->logger->notice("Will delete [{$studentSubmissionFiles->count()}] record(s) in: {$this->mapper->getTable()}", ['char' => '-']);
            } else {
                // Delete the related TII/Plagscan/Kami models,
                // and then delete the student submission files themselves
                try {
                    if ($turnitinReports->count()) {
                        $this->turnitinReportMapper->hardDelete($turnitinReports);
                    }
                    if ($plagscanDocuments->count()) {
                        $this->plagscanDocumentMapper->hardDelete($plagscanDocuments);
                    }
                    if ($kamiDocs->count()) {
                        $this->kamiMapper->hardDelete($kamiDocs);
                    }
                    $this->mapper->hardDelete($studentSubmissionFiles);
                } catch (\Exception $exception) {
                    throw new Mapper\Exception($exception->getMessage());
                }
                if ($turnitinReports->count()) {
                    $this->logger->notice("[{$turnitinReports->count()}] record(s) deleted in: {$this->turnitinReportMapper->getTable()}", ['char' => '-']);
                }
                if ($plagscanDocuments->count()) {
                    $this->logger->notice("[{$plagscanDocuments->count()}] record(s) deleted in: {$this->plagscanDocumentMapper->getTable()}", ['char' => '-']);
                }
                if ($kamiDocs->count()) {
                    $this->logger->notice("[{$kamiDocs->count()}] record(s) deleted in: {$this->kamiMapper->getTable()}", ['char' => '-']);
                }
                $this->logger->notice("[{$studentSubmissionFiles->count()}] record(s) deleted in: {$this->mapper->getTable()}", ['char' => '-']);
            }
        }
    }
}
