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
use Schoolbox\Resource\Storage;

/**
 * Class FileMapper
 *
 * @package Schoolbox\Assessment\Component\SubmissionBox\Submission
 * @method File findById($id)
 * @method File createModel()
 * @method File hydrate(array $row, $alias = '', $callback = null)
 */
class FileMapper extends AbstractMapper
{

    /**
     * @return void
     */
    protected function mapping()
    {
        parent::mapping();

        $file = new File();
        $file->setStorage($this->getStorageMapper()->createModel());

        $this->hasPrototype($file);

        $this->uses('realname', self::TYPE_STRING, null, 'filename');
        $this->uses('uuid', self::TYPE_STRING);

        $this->joins('hash', Storage\Mapper::class, 'hash', null, null, 'storage', 'storage');
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
        $storageJoin  = $this->getJoinMapper('storage');

        $query = "
            SELECT
                {$this->getColumnString('ss')},
                {$boxJoin->getColumnString('sb')},
                {$categoryJoin->getColumnString('c')},
                {$this->getJoinColumnString('storage', 'h')},
                {$storageJoin->getJoinColumnString('allowedFileExtension', 'afe')}
            FROM {$this->getTable()} ss
            LEFT JOIN {$this->getJoinSql('box_id', 'ss', 'sb')}
            LEFT JOIN {$this->getJoinSql('storage', 'ss', 'h')}
            LEFT JOIN {$storageJoin->getJoinSql('allowedFileExtension', 'h', 'afe')}
            LEFT JOIN {$boxJoin->getJoinSql('category', 'sb', 'c')}
            WHERE sb.category IN {$boxJoin->getArrayPlaceholders($categories, 'category')}
            AND ss.owner = :user_id
            AND ss.body IS NULL
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
        $storageJoin  = $this->getJoinMapper('storage');

        $query = "
            SELECT
                {$this->getColumnString('ss')},
                {$boxJoin->getColumnString('sb')},
                {$categoryJoin->getColumnString('c')},
                {$this->getJoinColumnString('storage', 'h')},
                {$storageJoin->getJoinColumnString('allowedFileExtension', 'afe')}
            FROM {$this->getTable()} ss
            LEFT JOIN {$this->getJoinSql('box_id', 'ss', 'sb')}
            LEFT JOIN {$this->getJoinSql('storage', 'ss', 'h')}
            LEFT JOIN {$storageJoin->getJoinSql('allowedFileExtension', 'h', 'afe')}
            LEFT JOIN {$boxJoin->getJoinSql('category', 'sb', 'c')}
            WHERE sb.id = :box_id
            AND ss.owner = :user_id
            AND ss.body IS NULL
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
     * * ss:  this submission
     * * sb:  submission box
     * * c:   category
     * * h:   storage
     * * afe: allowed file extension
     *
     * @param array $row
     *
     * @return ISubmission
     */
    protected function hydrateForActivityList($row) {
        $boxJoin = $this->getJoinMapper('box_id');
        $categoryJoin = $boxJoin->getJoinMapper('category');
        $storageJoin  = $this->getJoinMapper('storage');
        $mimeJoin     = $storageJoin->getJoinMapper('allowedFileExtension');

        $submission = $this->hydrate($row, 'ss');
        $box        = $boxJoin->hydrate($row, 'sb');
        $category   = $categoryJoin->hydrate($row, 'c');

        /** @var Storage\Model $storage */
        $storage    = $storageJoin->hydrate($row, 'h');
        $storage->setMimetype($mimeJoin->hydrate($row, 'afe'));

        $box->setCategory($category);
        $submission->setSubmissionBox($box);
        $submission->setStorage($storage);

        return $submission;
    }

} 