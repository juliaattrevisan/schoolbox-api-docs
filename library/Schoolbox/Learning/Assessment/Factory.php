<?php
/**
 * @author    Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
namespace Schoolbox\Learning\Assessment;

use Alaress\IDatabase;
use Schoolbox\Collection;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Schoolbox\User;

/**
 * Optimised DB queries to build objects for specific purposes
 */
class Factory
{
    /**
     * @var IDatabase
     */
    private $database;

    public function __construct()
    {
        $this->database = \Database::getInstance();
    }

    /**
     * Get Assessments for specified User that have participants that have submitted work
     * that requires marking.
     *
     * Assessments may live directly on folders, or in UnitImplementations that reside on a folder.
     *
     * If an assessment belongs to a unit, we need that units name, and its course's name.
     *
     * @param User $user
     * @return Collection|IAssessable[]
     */
    public function getAssessmentsToMark(User $user)
    {
        $folderIds = $subjectIds = $groupIds = [];

        if($user->getSubjectCollection()) {
            $subjects = $user->getSubjectCollection();
            foreach ($subjects as $subject) {
                $subjectIds[] = intval($subject['id']);
            }
            $groups = FolderMapper::getInstance()->findForUser($user, false);
            foreach ($groups as $group) {
                $groupIds[] = $group->getId();
            }
            $folderIds = array_merge($subjectIds, $groupIds);
        } else {
            $subjectsAndGroups = FolderMapper::getInstance()->findForUser($user, true);
            foreach($subjectsAndGroups as $folder){
                $folderIds[] = $folder->getId();
            }
        }

        // Get Ids of Course Units that reside on these folders
        $query = "
            SELECT id
            FROM course_unit_implementation
            WHERE category_id IN (".implode(',', $folderIds).")
        ";
        $statement = $this->database->prepare($query);
        $statement->execute();
        $unitIds = [];
        while($row = $statement->fetch(\PDO::FETCH_ASSOC)){
            $unitIds[] = $row['id'];


        $query = "
            SELECT assessment_id 
            FROM assessment_folder
            WHERE folder_id IN (".implode(',',$folderIds).")
                UNION
            SELECT assessment_id 
            FROM assessment_course_unit_implementation
            WHERE course_unit_implementation_id IN (".implode(',', $unitIds).")
        ";

        $statement = $this->database->prepare($query);
        $statement->execute();

        $result = [];
        while($row = $statement->fetch(\PDO::FETCH_ASSOC)) {
            $result[] = $row;
        }
        return $result;
    }
}