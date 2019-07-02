<?php
/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Learning\Assessment\DueWork\Response\File\Plagscan;

use Psr\Log\LoggerInterface;
use Schoolbox\Mapper;
use Schoolbox\Resource\Storage\RelationalDelete\Deletable;
use Schoolbox\Resource\Storage\RelationalDelete\IRelationalDelete;

class RelationalDelete extends Deletable implements IRelationalDelete
{
    /**
     * @var string
     */
    protected $hashColumn = 'storage_hash';

    /**
     * @var \Database
     */
    private $database;

    /**
     * RelationalDelete constructor.
     *
     * @param Mapper          $mapper
     */
    public function __construct(Mapper $mapper, LoggerInterface $logger)
    {
        parent::__construct($mapper, $logger);

        $this->database = \Database::getInstance();
    }

    /**
     * @inheritDoc
     */
    public function remove(string $hash, bool $performDryRun): void
    {
        /**
         * Some items do exists in student_submission_file only but not in plagscan_document
         */
        if ($this->hasStudentSubmissionRecord($hash)) {
            $query = 'DELETE FROM ' . $this->mapper->getTable() . ' WHERE student_submission_file_id
            IN (SELECT id FROM student_submission_file WHERE hash = :hash)';

            $statement = $this->database->prepare($query);
            $statement->execute(['hash' => $hash]);
        } else {
            parent::remove($hash, $performDryRun);
        }
    }

    /**
     * Check if there's a record that needs to be purged in plagscan_document via student_submission_file.id
     *
     * @param $hash
     *
     * @return int
     */
    private function hasStudentSubmissionRecord($hash): int
    {
        $query = 'SELECT COUNT(*) FROM ' . $this->mapper->getTable() . ' WHERE student_submission_file_id
            IN (SELECT id FROM student_submission_file WHERE hash = :hash)';

        $statement = $this->database->prepare($query);
        $statement->execute(['hash' => $hash]);

        return (int) $statement->fetchColumn();
    }
}
