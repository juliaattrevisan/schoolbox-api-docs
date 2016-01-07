<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2015, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Assessment\Rubric;

use Database;
use PDO;
use Schoolbox\Assessment\Component\SubmissionBox;
use Schoolbox\Assessment\Component\SubmissionBox\Rubric\Result;
use Schoolbox\Resource\Folder;
use Schoolbox\Assessment\Rubric\Competency\Level;
use Schoolbox\Assessment\Rubric\Criteria\Criterion;
use Schoolbox\Assessment\Rubric\Criteria\Group;
use Schoolbox\Collection;

/**
 * Umm. Not *exactly* a mapper... at the moment it's more like a handler...
 *
 * Class Mapper
 *
 * @package Schoolbox\Assessment\Rubric
 */
class RubricMapper
{

    /**
     * Stores the singleton instance of the mapper
     *
     * @var array[Mapper]
     */
    static protected $instance;


    /**
     * Retreives the singleton instance of this class.
     *
     * @return static
     */
    public static function getInstance()
    {
        $class_name = get_called_class();
        if (!isset(self::$instance[$class_name])) {
            self::$instance[$class_name] = new static();
        }
        return self::$instance[$class_name];
    }



    /** @var Database */
    protected $database;

    /** @var Factory */
    protected $factory;


    /** */
    public function __construct()
    {
        $this->database = Database::getInstance();
        $this->factory  = new Factory();
    }


    /**
     * Given a folder ID, return the base rubric, including criteria inherited from parents
     *
     * @param int   $folderId
     * @param bool  $forceTaskMode - if you need task-level criteria showing
     * @param array $levelNames
     * @return Rubric
     */
    public function findByFolderId($folderId, $forceTaskMode = false, array $levelNames = [])
    {

        /** @var Group[] $groupCache */
        $groupCache = [];

        $rubric = new Rubric();

        if (!count($levelNames)) {
            // default to 2 blank levels
            $levelNames = [ '', '' ];
        }

        // inject the levels
        foreach ($levelNames as $i => $level) {
            $rubric->putLevel(new Level($i, $level));
        }

        $args = [ 'folderId' => $folderId ];
        $in   = [ 'folderId' ];

        if ($forceTaskMode) {
            $key      = 'parentFolderId';
            $parentId = Folder\Mapper::getInstance()->findCheaplyById($folderId)->getParent()->getId();

            $args[$key] = $parentId;
            $in[] = $key;
        }

        $in = implode(', :', $in);


        // TODO DI and mapperise
        $stmt = \Database::getInstance()->query("
            SELECT rc.id, rc.grouping, rc.name, COUNT(ru.submission_box_id) usage_count
            FROM rubric_criterion rc
            LEFT JOIN rubric_usage ru ON rc.id = ru.criterion_id
            WHERE rc.parent_type = 'category'
            AND rc.parent_id IN (:$in)
            AND rc.deleted_at IS NULL
            GROUP BY rc.id
        ", $args);

        $groupId = 21;

        while ($r = $stmt->fetch(\PDO::FETCH_ASSOC)) {

            $groupName = $r['grouping'];

            if (!array_key_exists($groupName, $groupCache)) {
                $groupCache[$groupName] = new Group($groupId++, $groupName);
            }

            $crit = new Criterion($r['id'], $r['name']);

            $crit->setReadOnly($r['usage_count'] > 0);

            // cascade the read-onlyness up to the Group
            if ($crit->isReadOnly()) {
                $groupCache[$groupName]->setReadOnly(true);
            }

            $rubric->putCriterion($groupCache[$groupName], $crit);
        }

        return $rubric;
    }


    /**
     * Given a box ID, return the rubric assigned to it, if any
     *
     * @param SubmissionBox $box
     * @param bool          $forceTaskMode - if the box doesn't yet have a task, but you still need the task-level criteria showing
     * @return Rubric
     */
    public function findBySubmissionBox($box, $forceTaskMode = false)
    {
        $args = [
            'boxId' => $box->getId(),
            'folderId' => $box->getCategory()->getId()
        ];

        $in = [ 'folderId' ];

        // if the box has a task, include parent-folder criteria
        if ($box->getTask()->getId() || $forceTaskMode) {

            $key      = 'parentFolderId';
            $parentId = $box->getCategory()->getParent()->getId();

            if (!$parentId) {
                // insufficient hydration
                $parentId = Folder\Mapper::getInstance()->findCheaplyById($box->getCategory()->getId())->getParent()->getId();
            }

            $args[$key] = $parentId;
            $in[] = $key;
        }

        $in = implode(', :', $in);

        $criteriaStatement = $this->database->query("
            SELECT COUNT(box_id) usages, COALESCE(selected, 0) selected, crit_id, `grouping`, `name`
            FROM (
              SELECT submission_box_id box_id, submission_box_id = :boxId selected, c.id crit_id, `sequence`, `grouping`, `name`
              FROM rubric_usage u
              RIGHT JOIN rubric_criterion c ON u.criterion_id = c.id
              WHERE parent_type = 'category'
              AND parent_id IN (:$in)
              AND deleted_at IS NULL
              ORDER BY selected DESC
            ) t
            GROUP BY crit_id
            ORDER BY selected DESC, `sequence` ASC, `grouping` ASC, `name` ASC;
        ", $args);


        // why 20? because it's not 0.
        $groupId = 20;

        $groups = $criteria = $descriptors = [];

        $groupIdMap = [];

        while ($r = $criteriaStatement->fetch(PDO::FETCH_ASSOC)) {

            $groupName = $r['grouping'];

            if (empty($groupIdMap[$groupName])) {
                ++$groupId;

                $groupIdMap[$groupName] = $groupId;
                $groups[$groupId] = [ 'name' => $groupName ];
            }

            // check for usage
            $usedElsewhere = ($r['usages'] - $r['selected']) > 0;

            $criteria[$r['crit_id']] = [
                'groupId'  => $groupIdMap[$groupName],
                'on'       => $r['selected'] > 0,
                'name'     => $r['name'],
                'readonly' => $usedElsewhere
            ];

        }

        // umm... I have *never* had to do this before... how does the while() loop break if not EOF??
        $criteriaStatement->closeCursor();

        /** @var Collection|SubmissionBox\Rubric\Descriptor[] $descriptorCollection */
        $descriptorCollection = SubmissionBox\Rubric\DescriptorMapper::getInstance()->findByColumnList([
            'submission_box_id' => $box->getId(),
            'valid'             => true
        ]);

        $levelList = [];

        foreach ($descriptorCollection as $d) {
            // only populate if a description is given

            $descriptors[$d->getCriterion()->getId()][$d->getId()] = [
                'text'   => $d->getDescription(),
                'points' => $d->getValue() || $d->getDescription() ? $d->getValue() : ''
            ];

            // emulate empty levels
            $levelList[$d->getValue()] = [ 'name' => '' ];
        }

        // mokay. so if at this point you've got no descriptors, check to see if there should be a resultSet we should use
        if (count($descriptors) == 0) {
            // misnomer much
            $resultSetId = $box->getRubricResultSet();

            if ($resultSetId > 0) {
                // got a resultSet - we're going to fake that into a descriptor grid

                /** @var Collection|Result[] $results */
                $results = SubmissionBox\Rubric\ResultMapper::getInstance()->findByColumn('resultset_id', $resultSetId, [ 'order' => [ 'value' => 'desc' ] ]);

                foreach ($results as $result) {
                    foreach ($criteria as $id => $crit) {
                        $descriptors[$id][] = [
                            'text' => $result->getLabel(),
                            'points' => $result->getValue()
                        ];
                        // for legacy support, we leave the name blank
                        $levelList[$result->getValue()] = [ 'name' => '' ];
                    }
                }

            }
        }

        // ensure there are ALWAYS at least 2 levels
        while (count($levelList) < 2) {
            $levelList[] = [ 'name' => '' ];
        }

        foreach ($descriptors as $criterionId => $descriptorData) {
            $descriptors[$criterionId] = array_values($descriptorData);
        }

        // setup the data structure to match what the factory expects
        $postdata = [$box->getId() => [
            'level'      => array_values($levelList),
            'group'      => $groups,
            'criterion'  => $criteria,
            'descriptor' => $descriptors,
        ]];

        // use the factory to produce the rubric object
        return $this->factory->buildFromPostData($postdata, true);
    }


    /**
     * TODO decouple rubrics from submissions, but that requires some epic schema changes we don't have time for in 15.6
     *
     * @param Rubric $rubric
     * @param SubmissionBox $box
     * @return int
     */
    public function save($rubric, $box)
    {
        // TODO find some way to wrap this in a transaction
        var_dump($rubric->getORMCriteria());
        var_dump($rubric->getORMLevels());
        var_dump($rubric->getORMDescriptors());
        exit;
    }

}
