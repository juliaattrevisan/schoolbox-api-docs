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
    private Plagscan\Mapper      $plagscanDocumentMapper;
    private Kami\Mapper          $kamiMapper;

    public function __construct(
        Submission\SingleFile\Mapper $mapper,
        LoggerInterface $logger,
        Turnitin\File\Mapper $turnitinReportMapper,
        Plagscan\Mapper $plagscanDocumentMapper,
        Kami\Mapper $kamiMapper,
        string $hashColumn = 'hash'
    ) {
        parent::__construct($mapper, $logger, $hashColumn);
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
        $studentSubmissionFiles = $this->mapper->findByColumn($this->hashColumn, $hash, ['deleted' => true])->indexBy('id');

        $studentSubmissionFileIds = $studentSubmissionFiles->getPropertyValues('id');

        // Fetch TurnitinReports models and add to StudentSubmissionFile models
        $turnitinReports = $this->turnitinReportMapper->findByColumn('student_submission_file_id', $studentSubmissionFileIds, ['deleted' => true]);
        foreach ($turnitinReports as $turnitinReport) {
            $studentSubmissionFiles[$turnitinReport->getStudentSubmissionFileId()]->setTurnItIn($turnitinReport);
        }

        // Fetch Plagscan models and add to StudentSubmissionFile models
        $plagscanDocuments = $this->plagscanDocumentMapper->findByColumn('student_submission_file_id', $studentSubmissionFileIds, ['deleted' => true]);
        foreach ($plagscanDocuments as $plagscanDocument) {
            $studentSubmissionFiles[$plagscanDocument->getStudentSubmissionFileId()]->setPlagscan($plagscanDocument);
        }

        // Fetch Kami models and add to StudentSubmissionFile models
        $kamiDocs = $this->kamiMapper->findByColumn('student_submission_file_id', $studentSubmissionFileIds, ['deleted' => true]);
        foreach ($kamiDocs as $kamiDoc) {
            $studentSubmissionFiles[$kamiDoc->getStudentSubmissionFile()->getId()]->setKami($kamiDoc);
        }

        if ($studentSubmissionFiles->count() > 0) {
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
                foreach ($studentSubmissionFiles as $studentSubmissionFile) {
                    try {
                        $this->deleteJoinedModels($studentSubmissionFile);
                        $this->mapper->hardDelete($studentSubmissionFile);
                    } catch (\Exception $exception) {
                        throw new Mapper\Exception($exception->getMessage());
                    }
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

    /**
     * Hard deletes any TurnItIn,Plagscan or Kami joined models
     *
     * @throws Mapper\Exception
     */
    private function deleteJoinedModels(Model $studentSubmissionFile): void
    {
        if ($studentSubmissionFile->getTurnItIn()) {
            $this->turnitinReportMapper->hardDelete($studentSubmissionFile->getTurnItIn());
        }
        if ($studentSubmissionFile->getPlagscan()) {
            $this->plagscanDocumentMapper->hardDelete($studentSubmissionFile->getPlagscan());
        }
        if ($studentSubmissionFile->getKami()) {
            $this->kamiMapper->hardDelete($studentSubmissionFile->getKami());
        }
    }
}
