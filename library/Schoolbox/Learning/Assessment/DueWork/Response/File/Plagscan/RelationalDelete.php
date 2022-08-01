<?php

/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Assessment\DueWork\Response\File\Plagscan;

use Alaress\IDatabase;
use Psr\Log\LoggerInterface;
use Schoolbox\Mapper;
use Schoolbox\Resource\Storage\RelationalDelete\Deletable;

class RelationalDelete extends Deletable
{
    /**
     * @var \Database
     */
    private $database;

    /**
     * RelationalDelete constructor.
     *
     * @param Mapper          $mapper
     * @param LoggerInterface $logger
     */
    public function __construct(Mapper $mapper, LoggerInterface $logger, IDatabase $database)
    {
        parent::__construct($mapper, $logger, 'storage_hash');

        $this->database = $database;
    }

    /**
     * @inheritDoc
     */
    public function remove(string $hash, bool $performDryRun): void
    {
        $records = $this->mapper->findByColumn($this->hashColumn, $hash, ['deleted' => true]);

        if ($records->count() > 0 || $this->hasStudentSubmissionRecord($hash)) {
            if ($performDryRun) {
                $this->logger->notice('Will delete [' . $records->count() . '] record(s) in: ' . $this->mapper->getTable(), ['char' => '-']);
            } else {
                $query = 'DELETE pd FROM plagscan_document pd
                    INNER JOIN student_submission_file ssf ON pd.student_submission_file_id = ssf.id
                    WHERE ssf.hash = :hash';

                $statement = $this->database->prepare($query);
                $statement->execute(['hash' => $hash]);

                if ($statement->rowCount()) {
                    $this->logger->notice('[' . $statement->rowCount() . '] record(s) deleted in: ' . $this->mapper->getTable(), ['char' => '-']);
                } else {
                    parent::remove($hash, $performDryRun);
                }
            }
        }
    }

    /**
     * Check if there's a record that needs to be purged in student_submission_file
     *
     * @param string $hash
     *
     * @return int
     * @throws \PDOException
     */
    private function hasStudentSubmissionRecord($hash): int
    {
        $query = 'SELECT COUNT(*) FROM student_submission_file WHERE hash = :hash';

        $statement = $this->database->prepare($query);
        $statement->execute(['hash' => $hash]);

        return (int) $statement->fetchColumn();
    }
}
