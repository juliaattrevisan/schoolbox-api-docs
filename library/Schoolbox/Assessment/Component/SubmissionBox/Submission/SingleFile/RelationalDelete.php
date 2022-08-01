<?php

/**
 * @copyright Copyright 2022, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Assessment\Component\SubmissionBox\Submission\SingleFile;

use Psr\Log\LoggerInterface;
use Schoolbox\GenericCollection;
use Schoolbox\Learning\Turnitin;
use Schoolbox\Mapper;
use Schoolbox\Resource\Storage\RelationalDelete\Deletable;

class RelationalDelete extends Deletable
{
    private Turnitin\File\Mapper $turnitinReportMapper;

    public function __construct(Mapper $mapper, LoggerInterface $logger, Turnitin\File\Mapper $turnitinReportMapper, string $hashColumn = 'hash')
    {
        parent::__construct($mapper, $logger, $hashColumn);
        $this->turnitinReportMapper = $turnitinReportMapper;
    }

    /**
     * Will need to first hard-delete any `turnitin_report` records that reference the `student_submission_file`
     *
     * @inheritDoc
     */
    public function remove(string $hash, bool $performDryRun): void
    {
        /** @var GenericCollection|Model[] $studentSubmissionFiles */
        $studentSubmissionFiles = $this->mapper->findByColumn($this->hashColumn, $hash, ['deleted' => true])->indexBy('id');

        $turnitinReports = $this->turnitinReportMapper->findByColumn('student_submission_file_id', $studentSubmissionFiles->getPropertyValues('id'), ['deleted' => true]);
        foreach ($turnitinReports as $turnitinReport) {
            $studentSubmissionFiles[$turnitinReport->getStudentSubmissionFileId()]->setTurnItIn($turnitinReport);
        }

        if ($studentSubmissionFiles->count() > 0) {
            if ($performDryRun) {
                if ($turnitinReports->count()) {
                    $this->logger->notice("Will delete [{$turnitinReports->count()}] record(s) in: {$this->turnitinReportMapper->getTable()}", ['char' => '-']);
                }
                $this->logger->notice("Will delete [{$studentSubmissionFiles->count()}] record(s) in: {$this->mapper->getTable()}", ['char' => '-']);
            } else {
                foreach ($studentSubmissionFiles as $studentSubmissionFile) {
                    try {
                        if ($studentSubmissionFile->getTurnItIn()) {
                            $this->turnitinReportMapper->hardDelete($studentSubmissionFile->getTurnItIn());
                        }
                        $this->mapper->hardDelete($studentSubmissionFile);
                    } catch (\Exception $exception) {
                        throw new Mapper\Exception($exception->getMessage());
                    }
                }
                $this->logger->notice("[{$turnitinReports->count()}] record(s) deleted in: {$this->turnitinReportMapper->getTable()}", ['char' => '-']);
                $this->logger->notice("[{$studentSubmissionFiles->count()}] record(s) deleted in: {$this->mapper->getTable()}", ['char' => '-']);
            }
        }
    }
}
