<?php
/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Learning\Assessment\DueWork\Response\File\Plagscan;

use Schoolbox\Mapper;
use Schoolbox\Resource\Storage\GarbageCollector\HardLink;
use Schoolbox\Resource\Storage\GarbageCollector\IGarbageCollector;

class GarbageCollector extends HardLink implements IGarbageCollector
{
    protected $hashColumn = 'storage_hash';

    private $database;

    public function __construct(Mapper $mapper)
    {
        parent::__construct($mapper);

        $this->database = \Database::getInstance();
    }

    public function remove(string $hash)
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
            $records = $this->mapper->findByColumn($this->hashColumn, $hash, ['deleted' => true]);

            if ($records->count() > 0) {
                foreach ($records as $record) {
                    $this->mapper->hardDelete($record);
                }
            }
        }
    }

    /**
     * Check if there's a record that needs to be purged in plagscan_document via student_submission_file.id
     * @param $hash
     *
     * @return bool
     */
    private function hasStudentSubmissionRecord($hash)
    {
        $query = 'SELECT COUNT(*) FROM ' . $this->mapper->getTable() . ' WHERE student_submission_file_id
            IN (SELECT id FROM student_submission_file WHERE hash = :hash)';

        $statement = $this->database->prepare($query);
        $statement->execute(['hash' => $hash]);

        return $statement->fetchColumn();
    }
}
