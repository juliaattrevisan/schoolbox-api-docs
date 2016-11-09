<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2015, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Assessment\Component\SubmissionBox\Submission;

use PDOStatement;
use Schoolbox\Assessment\Activity;
use Schoolbox\Assessment\ActivityCollection;
use Schoolbox\Assessment\ActivityFactory;
use Schoolbox\Assessment\Component\SubmissionBoxMapper;
use Schoolbox\Assessment\Component\SubmissionBox\ReturnedWork;
use Schoolbox\Assessment\Component\SubmissionBox\ReturnedWorkMapper;
use Schoolbox\Collection;
use Schoolbox\Mapper;
use Schoolbox\UserMapper;

/**
 * A mapper for a student's submission to a due work.
 *
 * The name FileMapper is a misnomer, as student submissions may now contain multiple files.
 *
 * @package Schoolbox\Assessment\Component\SubmissionBox\Submission
 * @method File findById($id)
 * @method File hydrate(array $row, $alias = '', $callback = null)
 */
class FileMapper extends Mapper
{
    /**
     * @return void
     */
    protected function mapping()
    {
        $this->hasTable('student_submission');
        $this->hasModel(File::class);

        $this->uses('id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER);
        $this->joins('owner', UserMapper::class);
        $this->uses('marked', self::TYPE_BOOLEAN);
        $this->uses('late', self::TYPE_BOOLEAN);
        $this->joins('box_id', SubmissionBoxMapper::class, 'id', null, null, 'submissionBox');
        $this->uses('draft', self::TYPE_BOOLEAN);
        $this->uses('submitted', self::TYPE_DATETIME, self::COLUMN_CREATED_TIMESTAMP, 'createdAt');
        $this->uses('updated_at', self::TYPE_DATETIME, self::COLUMN_UPDATED_TIMESTAMP);
        $this->uses('deleted_at', self::TYPE_DATETIME, self::COLUMN_SOFTDELETE);


        $this->hasJoin(
            'file',
            [
                'mapper'    => SingleFile\Mapper::class,
                'this_key'  => 'id',
                'other_key' => 'student_submission_id',
            ]
        );

        $this->registerCallback(self::CALLBACK_POSTSAVE, [ $this, 'createDefaultReturn' ]);
    }

    /**
     * @return string
     */
    protected function getSelectString()
    {
        // much join.
        return "
            SELECT
                {$this->getColumnString()},
                {$this->getJoinColumnString('box_id', 'b')},
                {$this->getJoinColumnString('owner', 'u')}
            FROM {$this->getDatabase()->quoteIdentifier($this->getTable())}
            LEFT JOIN {$this->getJoinSql('box_id', '', 'b')}
            LEFT JOIN {$this->getJoinSql('owner', '', 'u')}
        ";
    }

    /**
     * Include all files that are part of this submission.
     *
     * @param PDOStatement $statement
     * @return Collection
     */
    protected function hydrateSelect(PDOStatement $statement)
    {
        /** @var Collection|File[] $resultSet */
        $resultSet = $this->createCollection();
        $ids       = [];

        // Default hydration
        foreach ($statement as $row) {
            $result = $this->hydrate($row);
            $ids[]  = $result->getId();
            $resultSet->add(
                $result
                    ->setSubmissionBox($this->hydrateJoin($row, 'box_id', 'b'))
                    ->setOwner($this->hydrateJoin($row, 'owner', 'u'))
            );
        }

        // Fetch and hydrate files
        $files = $this->getJoinMapper('file')
            ->findByColumn('student_submission_id', $ids)
            ->groupByCallback(function($f) {
                /** @var SingleFile\Model $f */
                return $f->getSubmission()->getId();
            });
        foreach ($resultSet as $result) {
            if (isset($files[$result->getId()])) {
                $result->setFiles($files[$result->getId()]);
            }
        }
        return $resultSet;
    }

    /**
     * Post-save callback.
     *
     * Attaches a default return to this submission for this student, if there
     * is no return for them already.
     *
     * @param File $object
     * @param File $previous
     */
    public function createDefaultReturn(File $object, $previous = null)
    {
        if (!($object->getDraft())) {
            $boxId = $object->getSubmissionBox()->getId();
            /** @var ReturnedWorkMapper $returnMapper */
            $returnMapper = $this->getJoinMapper('box_id.return');
            $returnCount  = $returnMapper->countByColumnList([
                'box_id' => $boxId,
                'owner'  => $object->getOwner()->getId()
            ]);
            if ($returnCount === 0) {
                try {
                    // Get default state
                    $submissionStateMapper = new \DueWork_ErrorMapper();
                    $defaultState          = $submissionStateMapper->findDefaultForNewSubmissions();
                } catch (Mapper\Exception $e) {
                    // No default state: do nothing
                    return;
                }

                // eurgh... manual hydration
                $box = $this->getJoinMapper('box_id')->findById($object->getSubmissionBox()->getId());

                /** @var ReturnedWork $defaultReturn */
                $defaultReturn = $returnMapper->createModel();
                $defaultReturn
                    ->setIsConfirmationOfSubmission(true)
                    ->setSubmissionBox($object->getSubmissionBox())
                    ->setOwner($object->getOwner())
                    // No real marker
                    ->setMarker($box->getOwner())
                    ->setMark($defaultState->getDisplayAs())
                    ->setReturned($object->getCreatedAt())
                    ->setNormalizedMark($defaultState->getNormaliseAs())
                ;
                $returnMapper->save($defaultReturn);
            }
        }
    }

    /**
     * Gets the list of activities related to student submissions.
     *
     * An activity is returned for each submission that this user has made into
     * a submission box in the given categories.
     *
     * @param int $userId
     * @param int $limit
     * @param array $categories
     *
     * @return ActivityCollection|Activity[]
     */
    public function getActivities($userId, $limit, array $categories)
    {
        $boxJoin      = $this->getJoinMapper('box_id');
        $categoryJoin = $boxJoin->getJoinMapper('category');

        $query = "
            SELECT
                {$this->getColumnString('ss')},
                {$boxJoin->getColumnString('sb')},
                {$categoryJoin->getColumnString('c')},
            FROM {$this->getTable()} ss
            LEFT JOIN {$this->getJoinSql('box_id', 'ss', 'sb')}
            LEFT JOIN {$boxJoin->getJoinSql('category', 'sb', 'c')}
            WHERE sb.category IN {$boxJoin->getArrayPlaceholders($categories, 'category')}
            AND sb.deleted IS FALSE
            AND ss.owner = :user_id
            AND ss.draft IS FALSE
            ORDER BY ss.submitted DESC
            LIMIT :limit
        ";

        $params = $this->getArrayParameters($categories, 'category');
        $params['limit']   = $limit;
        $params['user_id'] = $userId;

        return $this->hydrateActivities($query, $params);
    }


    /**
     * @param int $userId
     * @param int $boxId
     * @return ActivityCollection
     */
    public function getHistory($userId, $boxId)
    {
        $boxJoin      = $this->getJoinMapper('box_id');
        $categoryJoin = $boxJoin->getJoinMapper('category');

        $query = "
            SELECT
                {$this->getColumnString('ss')},
                {$boxJoin->getColumnString('sb')},
                {$categoryJoin->getColumnString('c')}
            FROM {$this->getTable()} ss
            LEFT JOIN {$this->getJoinSql('box_id', 'ss', 'sb')}
            LEFT JOIN {$boxJoin->getJoinSql('category', 'sb', 'c')}
            WHERE sb.id = :box_id
            AND ss.owner = :user_id
            AND ss.draft IS FALSE
            ORDER BY ss.submitted DESC
        ";

        $params = array(
            'user_id' => $userId,
            'box_id'  => $boxId,
        );

        return $this->hydrateActivities($query, $params);
    }


    /**
     * @param string $query
     * @param array  $params
     * @return ActivityCollection
     */
    protected function hydrateActivities($query, $params)
    {
        $factory       = new ActivityFactory();
        $collection    = new ActivityCollection();

        $statement = $this->database->prepare($query);

        foreach ($params as $key => $value) {
            $statement->bindParam($key, intval($value), \PDO::PARAM_INT);
        }
        $statement->execute();
        foreach ($statement as $row) {
            $collection->add(
                $factory->buildFromStudentSubmission(
                    $this->hydrateForActivityList($row)
                )
            );
        }

        return $collection;
    }


    /**
     * Custom hydration method used by methods that get an activity list.
     *
     * This expects the following aliases:
     * * ss:  this submission
     * * sb:  submission box
     * * c:   category
     * * h:   storage
     * * afe: allowed file extension
     *
     * @param array $row
     *
     * @return File
     */
    protected function hydrateForActivityList($row) {
        $boxJoin = $this->getJoinMapper('box_id');
        $categoryJoin = $boxJoin->getJoinMapper('category');

        $submission = $this->hydrate($row, 'ss');
        $box        = $boxJoin->hydrate($row, 'sb');
        $category   = $categoryJoin->hydrate($row, 'c');

        $box->setCategory($category);
        $submission->setSubmissionBox($box);

        return $submission;
    }

    /**
     * Retrieves draft submissions for this user and box, ordered by most recent first.
     *
     * @param int $user_id
     * @param int $box_id
     * @param int $limit
     *
     * @return Collection|File[]
     */
    public function getDrafts($user_id, $box_id, $limit = null)
    {
        return $this->findByColumnList([
            'box_id' => $box_id,
            'owner'  => $user_id,
            'draft'  => true
        ], [
            'limit'  => $limit,
            'order'  => [ 'submitted' => 'desc' ],
        ]);

    }

    /**
     * Get list of submissions that should be submitted to crocodoc
     *
     * @return Collection|File[]
     */
    public function getCrocodoc() {

        $query = $this->getSelectString();
        $query .= "
            WHERE uuid is null AND ( ext like 'docx' OR
                                        ext like 'doc' OR
                                        ext like 'xls' OR
                                        ext like 'xlsx' OR
                                        ext like 'ppt' OR
                                        ext like 'pptx' OR
                                        ext like 'pdf')
                 AND YEAR(submitted) = YEAR(NOW())
            LIMIT 0,100
            ";

        $statement = $this->database->prepare($query);
        $statement->execute();
        return $this->hydrateSelect($statement);
    }


    /**
     * Retrieves the number of submissions that have been made on each box in a
     * list.
     *
     * @param array $box_ids
     * @param int   $user_id
     *
     * @return array The submission box ID as the key, the count as the value.
     */
    public function findCountsForSubmissionBoxes($box_ids, $user_id) {
        // Retrieve count of submission IDs from a list of IDs for a user
        if (empty($box_ids)) {
            return array();
        }
        $query = "
            SELECT ss.box_id, COUNT(DISTINCT submitted) as submission_count
            FROM {$this->getTable()} ss
            WHERE ss.owner = :user_id
            AND ss.box_id IN {$this->getArrayPlaceholders($box_ids, 'sb')}
            AND ss.draft IS FALSE
            GROUP BY ss.box_id 
        ";
        $statement = $this->database->prepare($query);
        $params = $this->getArrayParameters($box_ids, 'sb');
        $params['user_id'] = $user_id;
        $statement->execute($params);

        $return = array();
        foreach ($statement as $row) {
            $return[$row['box_id']] = $row['submission_count'];
        }
        return $return;
    }

}
