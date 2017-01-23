<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Assessment\Component\SubmissionBox\Submission\SingleFile;

use PDOStatement;
use Schoolbox\Assessment\Component\SubmissionBox\Submission\SubmissionMapper;
use Schoolbox\Collection;
use Schoolbox\Mapper as BaseMapper;
use \Schoolbox\File\Mimetype as Mimetype;
use \Schoolbox\Resource\Storage\Model as Storage;
use Schoolbox\Resource\Storage\Mapper as StorageMapper;
use \Schoolbox\Learning\Assessment\DueWork\Submission\File\Plagscan\Model as Plagscan;
use \Schoolbox\Learning\Assessment\DueWork\Submission\File\Plagscan\Mapper as PlagscanMapper;
use \Schoolbox\Learning\InlineGrader\Factory\ResponseFile as ResponseFile;

/**
 * A mapper for a single file within a student's submission to a due work.
 *
 * @method Model hydrate(array $row, $alias = '', $callback = null)
 */
class Mapper extends BaseMapper
{
    /**
     * @return void
     */
    protected function mapping()
    {
        $this->hasTable('student_submission_file');
        $this->hasModel(Model::class);

        $this->uses('id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER);
        $this->joins('student_submission_id', SubmissionMapper::class, 'id', null, null, 'submission', 'submission');
        $this->joins('hash', StorageMapper::class, 'hash', null, null, 'storage', 'storage');
        $this->uses('realname', self::TYPE_STRING, null, 'filename');
        $this->uses('editable_inline', self::TYPE_BOOLEAN);
        $this->uses('uuid', self::TYPE_STRING);
        $this->uses('created_at', self::TYPE_DATETIME, self::COLUMN_CREATED_TIMESTAMP);
        $this->uses('updated_at', self::TYPE_DATETIME, self::COLUMN_UPDATED_TIMESTAMP);
        $this->uses('deleted_at', self::TYPE_DATETIME, self::COLUMN_SOFTDELETE);
        $this->joinsReverse('student_submission_file_id', PlagscanMapper::class, 'id', null, null, 'plagscan', 'plagscan');

        $this->registerCallback(self::CALLBACK_POSTSOFTDELETE, [ $this, 'softDeletePlagScan' ]);
        $this->registerCallback(self::CALLBACK_POSTRESTORE, array($this, 'restorePlagScan'));
    }

    /**
     * Soft deletes any PlagScan for the specified File
     *
     * @param Model $file
     */
    public function softDeletePlagScan(Model $file)
    {
        $plagScanMapper = $this->getJoinMapper('plagscan');
        $plagScans = $plagScanMapper->findByColumn('student_submission_file_id', $file->getId(), ['deleted' => true]);
        $plagScanMapper->delete($plagScans);
    }


    /**
     * Restores any PlagScan for the specified File
     *
     * @param Model $file
     */
    public function restorePlagScan(Model $file)
    {
        $plagScanMapper = $this->getJoinMapper('plagscan');
        $plagScans = $plagScanMapper->findByColumn('student_submission_file_id', $file->getId(), ['deleted' => true]);
        $plagScanMapper->restoreSoftDeleted($plagScans);
    }

    /**
     * @return string
     */
    protected function getSelectString()
    {
        return "
            SELECT
                {$this->getColumnString()},
                {$this->getJoinColumnString('submission', 's')},
                {$this->getJoinColumnString('storage', 'f')},
                {$this->getJoinColumnString('storage.allowedFileExtension', 'm')},
                {$this->getJoinMapper('plagscan')->getColumnString('ps')}

            FROM {$this->getDatabase()->quoteIdentifier($this->getTable())}
            LEFT JOIN {$this->getJoinSql('submission', '', 's')}
            LEFT JOIN {$this->getJoinSql('storage', '', 'f')}
            LEFT JOIN {$this->getJoinSql('storage.allowedFileExtension', 'f', 'm')}
            LEFT JOIN {$this->getJoinSql('plagscan', '', 'ps')}
        ";
    }

    /**
     * Include storage and student's submission.
     *
     * @param PDOStatement $statement
     * @return Collection|Model[]
     */
    protected function hydrateSelect(PDOStatement $statement)
    {
        /** @var Collection|Model[] $resultSet */
        $resultSet = $this->createCollection();
        $seen = [];

        foreach ($statement as $row) {

            /* Sometimes multiple plagscan results per submission file causes dupes
             * Not sure if it continues to happen normally, but have seen it in demo data
             *
             * Also unsure how plagscan results spawn multiple times for the same file
             * as new records, instead of overwriting the old one (if exists)
             */
            if (in_array($row['ps.student_submission_file_id'], $seen)) {
                continue;
            }

            /** @var Mimetype $mimetype */
            $mimetype = $this->hydrateJoin($row, 'storage.allowedFileExtension', 'm');

            /** @var Storage $storage */
            $storage = $this->hydrateJoin($row, 'storage', 'f');
            $storage->setMimetype($mimetype);

            /** @var Plagscan $plagscan */
            $plagscan = $this->hydrateJoin($row, 'plagscan', 'ps');

            $submissionFile = $this->hydrate($row);
            $submissionFile
                ->setStorage($storage)
                ->setPlagscan($plagscan)
                ->setSubmission($this->hydrateJoin($row, 'submission', 's'));

            $resultSet->add($submissionFile);

            $seen[] = $submissionFile->getId();
        }

        return $resultSet;
    }

    /**
     * Get list of submissions that should be submitted to crocodoc
     *
     * @return Collection
     */
    public function findPendingCrocodocUpload()
    {
        /* Get a list of legal files that crocodoc accepts
         */
        $allowedExtensions = ResponseFile::getAllowedFileTypes();

        new \Alaress_Probe($allowedExtensions);
        die();

//        $query = $this->getSelectString();
//        $query .= "
//            WHERE uuid is null AND ( ext like 'docx' OR
//                                        ext like 'doc' OR
//                                        ext like 'xls' OR
//                                        ext like 'xlsx' OR
//                                        ext like 'ppt' OR
//                                        ext like 'pptx' OR
//                                        ext like 'pdf')
//                 AND YEAR(submitted) = YEAR(NOW())
//            LIMIT 0,100
//            ";
//
//        $statement = $this->database->prepare($query);
//        $statement->execute();

        return $this->hydrateSelect($statement);
    }
}
