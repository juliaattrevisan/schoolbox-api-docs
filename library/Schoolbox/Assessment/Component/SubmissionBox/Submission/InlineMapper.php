<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2015, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Assessment\Component\SubmissionBox\Submission;

use Schoolbox\Assessment\Activity;
use Schoolbox\Assessment\ActivityCollection;
use Schoolbox\Collection;

/**
 * Class InlineMapper
 *
 * @package Schoolbox\Assessment\Component\SubmissionBox\Submission
 * @method Inline findById($id)
 * @method Inline findOneByColumnList($columnList, $options = [])
 * @method Inline createModel()
 * @method Inline hydrate(array $row, $alias = '', $callback = null)
 */
class InlineMapper extends AbstractMapper
{

    /**
     * @return void
     */
    protected function mapping()
    {
        parent::mapping();

        $this->hasPrototype(new Inline());

        $this->uses('body', self::TYPE_STRING);
        $this->uses('draft', self::TYPE_BOOLEAN);
    }


    /**
     * Retrieves drafts for this user and box, ordered by most recent first.
     *
     * @param int $user_id
     * @param int $box_id
     * @param int $limit
     *
     * @return Collection|Inline[]
     */
    public function getDrafts($user_id, $box_id, $limit = null) {
        $query = "
            SELECT {$this->getColumnString('ss')}
            FROM {$this->getTable()} ss
            WHERE ss.box_id = :box_id
            AND ss.owner = :user_id
            AND ss.draft IS TRUE
            ORDER BY ss.submitted DESC
        ";

        if (isset($limit)) {
            $query .= ' LIMIT :limit';
        }

        $statement = $this->database->prepare($query);
        $statement->bindValue('user_id', $user_id, \PDO::PARAM_INT);
        $statement->bindValue('box_id',  $box_id,  \PDO::PARAM_INT);

        if (isset($limit)) {
            $statement->bindValue('limit', $limit, \PDO::PARAM_INT);
        }

        $statement->execute();
        return $this->hydrateList($statement, 'ss');
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
        $box_join      = $this->getJoinMapper('box_id');
        $category_join = $box_join->getJoinMapper('category');

        $query = "
            SELECT
                {$this->getColumnString('ss')},
                {$box_join->getColumnString('sb')},
                {$category_join->getColumnString('c')}
            FROM {$this->getTable()} ss
            LEFT JOIN {$this->getJoinSql('box_id', 'ss', 'sb')}
            LEFT JOIN {$box_join->getJoinSql('category', 'sb', 'c')}
            WHERE sb.category IN {$box_join->getArrayPlaceholders($categories, 'category')}
            AND ss.owner = :user_id
            AND ss.body IS NOT NULL
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
            AND ss.body IS NOT NULL
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
     * Custom hydration method used by methods that get an activity list.
     *
     * This expects the following aliases:
     * * ss: this submission
     * * sb: submission box
     * * c:  category
     *
     * @param array $row
     *
     * @return ISubmission
     */
    protected function hydrateForActivityList($row) {
        $boxJoin = $this->getJoinMapper('box_id');
        $categoryJoin = $boxJoin->getJoinMapper('category');

        $submission = $this->hydrate($row, 'ss');
        $box = $boxJoin->hydrate($row, 'sb');
        $category = $categoryJoin->hydrate($row, 'c');

        $box->setCategory($category);
        $submission->setSubmissionBox($box);

        return $submission;
    }

} 