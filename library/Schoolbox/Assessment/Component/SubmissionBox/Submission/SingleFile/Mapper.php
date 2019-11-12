<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Assessment\Component\SubmissionBox\Submission\SingleFile;

use Alaress\Database\Quoter\IQuoter;
use PDOStatement;
use Schoolbox\Assessment\Component\SubmissionBox\Submission\SubmissionMapper;
use Schoolbox\Collection;
use Schoolbox\Mapper as BaseMapper;
use \Schoolbox\File\Mimetype;
use \Schoolbox\Resource\Storage\Model as Storage;
use Schoolbox\Resource\Storage\Mapper as StorageMapper;
use \Schoolbox\Learning\Assessment\DueWork\Response\File\Plagscan;
use Schoolbox\Learning\InlineGrader\Kami;

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
        $this->uses('created_at', self::TYPE_DATETIME, self::COLUMN_CREATED_TIMESTAMP);
        $this->uses('updated_at', self::TYPE_DATETIME, self::COLUMN_UPDATED_TIMESTAMP);
        $this->uses('deleted_at', self::TYPE_DATETIME, self::COLUMN_SOFTDELETE);
        $this->joinsReverseSingle('student_submission_file_id', Plagscan\Mapper::class, 'plagscan', 'plagscan');
        $this->joinsReverseSingle('student_submission_file_id', Kami\Mapper::class, 'kami', 'kami');

        $this->registerCallback(self::CALLBACK_POSTSOFTDELETE, [ $this, 'softDeletePlagScan' ]);
        $this->registerCallback(self::CALLBACK_POSTSOFTDELETE, [ $this, 'softDeleteKami' ]);
    }


    /**
     * Soft deletes any PlagScan for the specified File
     *
     * @param Model $file
     * @throws BaseMapper\DefinitionException
     */
    public function softDeletePlagScan(Model $file)
    {
        /** @var Plagscan\Mapper $plagScanMapper */
        $plagScanMapper = $this->getJoinMapper('plagscan');
        $plagScans = $plagScanMapper->findByColumn('student_submission_file_id', $file->getId(), ['deleted' => true]);
        $plagScanMapper->delete($plagScans);
    }


    /**
     * Soft-deletes any kami docs for this file
     *
     * @param Model $file
     * @throws BaseMapper\Exception
     * @throws BaseMapper\DefinitionException
     * @return bool
     */
    public function softDeleteKami(Model $file): bool
    {
        /** @var Kami\Mapper $kamiMapper */
        $kamiMapper = $this->getJoinMapper('kami');
        $kamiFiles = $kamiMapper->findByColumn('student_submission_file_id', $file->getId());
        $kamiMapper->delete($kamiFiles);
        return true;
    }


    /**
     * @return string
     * @throws BaseMapper\DefinitionException
     */
    protected function getSelectString()
    {
        /** @var IQuoter $db */
        $db = $this->getDatabase();
        return "
            SELECT
                {$this->getColumnString()},
                {$this->getJoinColumnString('submission', 's')},
                {$this->getJoinColumnString('storage', 'f')},
                {$this->getJoinColumnString('storage.allowedFileExtension', 'm')},
                {$this->getJoinMapper('plagscan')->getColumnString('ps')},
                {$this->getJoinMapper('kami')->getColumnString('k')}
            FROM {$db->quoteIdentifier($this->getTable())}
            INNER JOIN {$this->getJoinSql('submission', '', 's', true)}
            LEFT JOIN {$this->getJoinSql('storage', '', 'f')}
            LEFT JOIN {$this->getJoinSql('storage.allowedFileExtension', 'f', 'm')}
            LEFT JOIN {$this->getJoinSql('plagscan', '', 'ps')}
            LEFT JOIN {$this->getJoinSql('kami', '', 'k')}
        ";
    }


    /**
     * Include storage and student's submission.
     *
     * @param PDOStatement $statement
     * @return Model[]|Collection
     * @throws BaseMapper\DefinitionException
     */
    protected function hydrateSelect(PDOStatement $statement)
    {
        /** @var Collection|Model[] $resultSet */
        $resultSet = $this->createCollection();
        $seen = [];

        /**
         * @var Kami\Mapper      $kamiMapper
         * @var SubmissionMapper $submissionMapper
         */
        $kamiMapper       = $this->getJoinMapper('kami');
        $submissionMapper = $this->getJoinMapper('submission');

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

            /** @var Plagscan\Model $plagscan */
            $plagscan = $this->hydrateJoin($row, 'plagscan', 'ps');

            $submissionFile = $this->hydrate($row);
            $submissionFile
                ->setStorage($storage)
                ->setPlagscan($plagscan)
                ->setKami($kamiMapper->hydrate($row, 'k'))
                ->setSubmission($submissionMapper->hydrate($row, 's'));

            $resultSet->add($submissionFile);

            $seen[] = $submissionFile->getId();
        }

        return $resultSet;
    }
}
