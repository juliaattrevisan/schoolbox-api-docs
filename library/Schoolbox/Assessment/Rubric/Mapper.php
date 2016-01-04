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
use Schoolbox\Assessment\Rubric\Competency\Level;
use Schoolbox\Assessment\Rubric\Criteria\Criterion;
use Schoolbox\Assessment\Rubric\Criteria\Group;

/**
 * Umm. Not *exactly* a mapper... at the moment it's more like a handler...
 *
 * Class Mapper
 *
 * @package Schoolbox\Assessment\Rubric
 */
class Mapper
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
     * @param int    $folderId
     * @param array  $levelNames
     * @return Rubric
     */
    public function findByFolderId($folderId, array $levelNames = [])
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


        // TODO DI and mapperise
        $stmt = \Database::getInstance()->query("
            SELECT rc.id, rc.grouping, rc.name, COUNT(ru.submission_box_id) usage_count
            FROM rubric_criterion rc
            LEFT JOIN rubric_usage ru ON rc.id = ru.criterion_id
            WHERE rc.parent_type = 'category'
            AND rc.parent_id = ?
            AND rc.deleted_at IS NULL
            GROUP BY rc.id
        ", [ $folderId ]);

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
     * @param int  $boxId
     * @return Rubric
     */
    public function findBySubmissionBoxId($boxId)
    {
        $criteriaStatement = $this->database->query("
            SELECT COUNT(box_id) usages, COALESCE(selected, 0) selected, crit_id, `grouping`, `name`
            FROM (
              SELECT submission_box_id box_id, submission_box_id = :boxId selected, c.id crit_id, `sequence`, `grouping`, `name`
              FROM rubric_usage u
              RIGHT JOIN rubric_criterion c ON u.criterion_id = c.id
              WHERE parent_type = 'category'
              AND parent_id = (SELECT category FROM submission_box WHERE id = :boxId)
              AND deleted_at IS NULL
              ORDER BY selected DESC
            ) t
            GROUP BY crit_id
            ORDER BY selected DESC, `sequence` DESC, `grouping` ASC, `name` ASC;
            /* ORDER BY u.sequence ASC */
        ", [ 'boxId' => $boxId ]);


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


        $descriptorStatement = $this->database->query("
            SELECT id, criterion_id, description, value
            FROM rubric_descriptor
            WHERE valid IS TRUE
            AND submission_box_id = ?
        ", [ $boxId ]);

        $levelList = [];

        while ($r = $descriptorStatement->fetch(PDO::FETCH_ASSOC)) {
            // only populate if a description is given

            $descriptors[$r['criterion_id']][$r['id']] = [
                'text'   => $r['description'],
                'points' => $r['value'] || $r['description']? $r['value'] : ''
            ];

            $levelList[$r['value']] = [ 'name' => '' ];
        }

        foreach ($descriptors as $criterionId => $descriptorData) {
            $descriptors[$criterionId] = array_values($descriptorData);
        }

        // setup the data structure to match what the factory expects
        $postdata = [ $boxId => [
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
     * @param SubmissionBox
     * @return int
     */
    public function save($rubric, $submissionBox)
    {

    }




}
