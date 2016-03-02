<?php
/**
 * A Mapper Composite (a pseudo mapper) like the RubricMapper
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
namespace Schoolbox\Assessment\Course;

use Schoolbox\Collection;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Schoolbox\Resource\Folder\Model as FolderModel;
use Schoolbox\Resource\Folder\Ancestry;
use Schoolbox\Config\Loader;

class Mapper
{
    /**
     * A mapper instance
     *
     * @var FolderMapper
     */
    private $folderMapper = null;

    /**
     * A Folder instance that represents the root node
     * for Faculties
     *
     * @var FolderModel
     */
    private $facultyNode = null;

    /**
     * There are only twelve config models
     * But there are many copies
     *
     * @var Loader
     */
    private $configLoader = null;

    /**
     * A cache of ancestral Folder instances, to save
     * on re-querying the same Folder from the db over
     * and over again
     *
     * @var Ancestry
     */
    private $ancestry = null;

    /**
     * Constructor
     *
     * Mapper constructor.
     */
    public function __construct()
    {
        $this->setConfigLoader(new Loader());
        $this->setFolderMapper(FolderMapper::getInstance());
        $this->setAncestry(new Ancestry());

        /* TODO: Replace /homepage/29 in config/course_homepage with just int
         * Faculty Root Node can be obtained Cheaply
         * but we have to strip out the /homepage/ part
         */
        $facultyNodeId = str_replace('/homepage/', '', $this->getConfigLoader()->get('course_homepage'));

        $this->setFacultyNode($this->getFolderMapper()->findCheaplyById($facultyNodeId));
    }

    /**
     * List the courses
     *
     * @api   /course/list
     * @level 160
     */
    public function findCourseList()
    {
        $query = "
            SELECT
                c.id,
                COUNT(t.id) AS taskCount
            FROM category c
            LEFT JOIN category subc ON subc.parent = c.id AND subc.deleted_at IS NULL
            JOIN `task` t ON t.category_id = subc.id AND t.deleted_at IS NULL
            WHERE c.deleted_at IS NULL
            GROUP BY c.id
        ";

        $allFolderIds = \Database::getInstance()->query($query)->fetchAll(\PDO::FETCH_COLUMN);

        /** @var Collection|FolderModel[] $courses */
        $allCourses = $this->getFolderMapper()->findByColumn('id', $allFolderIds);

        $data = [
            'courses' => $allCourses
        ];

        foreach ($allCourses as $currentCourse) {
            // this is way more expensive than it needs to be -_-;
            $data['lineage'][$currentCourse->getId()] = $this->getAncestry()->findParentsCheaply($currentCourse, $this->getFacultyNode());

            $allUnits = $this->getFolderMapper()->findByParent($currentCourse->getId());

            foreach ($allUnits as $currentUnit) {

                // far out, this is ridiculous: I can't even prepare this statement because of other transactions getting in the way
                $taskCount = \Database::getInstance()->query("
                    SELECT
                        COUNT(t.id) AS taskCount
                    FROM category c
                    JOIN `task` t ON t.category_id = c.id AND t.deleted_at IS NULL
                    WHERE c.deleted_at IS NULL AND c.id = ?
                    GROUP BY c.id
                ", [ $currentUnit->getId() ])->fetchColumn();

                if ($taskCount > 0) {
                    $data['units'][$currentCourse->getId()][] = $currentUnit;
                }
            }
        }

        /* TODO: Clear Ancestry results after you're done, or keep it?
         */

        return $data;
    }

    /* Getters/Setters
     */

    /**
     * Get folder mapper
     *
     * @return null
     */
    private function getFolderMapper()
    {
        return $this->folderMapper;
    }

    /**
     * Set folder mapper
     *
     * @param null $folderMapper
     */
    private function setFolderMapper($folderMapper)
    {
        $this->folderMapper = $folderMapper;
    }

    /**
     * Get faculty node
     *
     * @return FolderModel
     */
    private function getFacultyNode()
    {
        return $this->facultyNode;
    }

    /**
     * Set faculty node
     *
     * @param FolderModel $facultyNode
     */
    private function setFacultyNode($facultyNode)
    {
        $this->facultyNode = $facultyNode;
    }

    /**
     * Get config loader
     *
     * @return Loader
     */
    private function getConfigLoader()
    {
        return $this->configLoader;
    }

    /**
     * Set config loader
     *
     * @param Loader $configLoader
     */
    private function setConfigLoader($configLoader)
    {
        $this->configLoader = $configLoader;
    }

    /**
     * Get ancestry
     *
     * @return Ancestry
     */
    private function getAncestry()
    {
        return $this->ancestry;
    }

    /**
     * Set ancestry
     *
     * @param Ancestry $ancestry
     */
    private function setAncestry($ancestry)
    {
        $this->ancestry = $ancestry;
    }
}