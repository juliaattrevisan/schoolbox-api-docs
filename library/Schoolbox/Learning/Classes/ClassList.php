<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Learning\Classes;

use Alaress\Color\Color;
use Alaress\Color\ColorFactory;
use Alaress_Date_Format;
use Schoolbox\Assessment\Term;
use Schoolbox\Collection;
use Schoolbox\GenericCollection;
use Schoolbox\Mapper\DefinitionException;
use Schoolbox\Resource\Folder;
use Schoolbox\User;
use Timetable_ColourMapper;
use UnexpectedValueException;
use Schoolbox\Permission\Access\Access;
use Schoolbox\Permission\Access\UserAccess;

/**
 * Generates a list of classes, along with the courses imported to those classes, suitable
 * for rendering on either:
 *
 * - Classes Dashboard Component, or
 * - Classes Page
 */
class ClassList
{

    /** @var User */
    private $user;

    /** @var Folder\Mapper */
    private $folderMapper;

    /** @var Timetable_ColourMapper */
    private $timetableColourMapper;

    /** @var Folder\ClassListFactory */
    private $classListFactory;


    /**
     * @param User                    $user
     * @param Folder\Mapper           $folderMapper
     * @param Timetable_ColourMapper  $timetableColourMapper
     * @param Folder\ClassListFactory $classListFactory
     */
    public function __construct(
        User $user,
        Folder\Mapper $folderMapper,
        Timetable_ColourMapper $timetableColourMapper,
        Folder\ClassListFactory $classListFactory
    ) {
        $this->user                  = $user;
        $this->folderMapper          = $folderMapper;
        $this->timetableColourMapper = $timetableColourMapper;
        $this->classListFactory      = $classListFactory;
    }


    /**
     * Returns a set of of classes, along with subsidiary data, suitable for
     * rendering on the classes component or page
     *
     * @param Term\Model          $term
     * @param Alaress_Date_Format $now
     * @return array
     * @var Term                          return['term']
     * @var Collection|Folder\Model[]     return['classList']
     * @var string[]                      return['classTypes'] Indexed by folder.id
     * @var string[][]                    return['courses'] Grouped by folder.id, courseSpec.id
     * @throws DefinitionException
     * @throws UnexpectedValueException
     */
    public function getClassList(Term\Model $term, Alaress_Date_Format $now)
    {
        $classTypes    = [];
        $folderList    = $this->folderMapper->createCollection();
        $isTermCurrent = $term->contains($now);

        // Are we looking at current classes, or historical?

        if ($isTermCurrent) {

            // Iterate all subjects from the SIS and try to find folders with that class code

            foreach ($this->user->getSubjectCollection() as $subject) {
                $folder                       = $this->folderMapper->findForCampusAndClass($subject['campusCode'] ?? '', $subject['code']);
                if (!$folder) {
                    continue;
                }
                $folderList                   = $folderList->mergeUnique(new GenericCollection(Folder\Model::class, [$folder]), 'id');
                $classTypes[$folder->getId()] = $subject['classType'] ?? '';
            }
        }

        // Add in other folders with feedback during the term, this will pick up archived classes
        if (!$isTermCurrent && $this->user->getRole()->isStaff()) {
            $folderList = $folderList->mergeUnique($this->folderMapper->findFoldersWithFeedbacksFromTeacher($this->user, $term), 'id');
        } elseif ($this->user->getRole()->isStudent()) {
            /** @var GenericCollection|Folder\Model[] $folderList */
            $folderList = $folderList->mergeUnique($this->folderMapper->findFoldersWithResponses($this->user, $term), 'id');
            $folderList = $folderList->mergeUnique($this->folderMapper->findFoldersWithFeedbackToStudent($this->user, $term), 'id');
        }

        $courseSpecs = $this->getCourseSpecsHavingUnitsInTerm($folderList, $term);

        $this->applyFolderColours($folderList);

        $homepageUrls = [];
        $access       = Access::getInstance();

        // Only set those homepage URLs that user have access permission
        foreach ($folderList as $folder) {
            if ($access->getFolderAccess($this->user, $folder->getId()) >= UserAccess::ACCESS_READ) {
                $homepageUrls[$folder->getId()] = "/homepage/{$folder->getId()}";
            } else {
                $homepageUrls[$folder->getId()] = false;
            }
        }
        $folderList->sortByCallback(static function (Folder\Model $a, Folder\Model $b): int {
            return \strnatcasecmp($a->getCodeString(), $b->getCodeString());
        }, true);

        return [
            'term'         => $term,
            'classList'    => $folderList,
            'classTypes'   => $classTypes,
            'courses'      => $courseSpecs,
            'homepageUrls' => $homepageUrls,
        ];
    }


    /**
     * Returns a sets of CourseSpecs titles, indexed by class folder.id, courseSpec.id
     *
     * Filters to only return CourseSpecs that have assessments imported to class folder that
     * are due in the specified term
     *
     * @param GenericCollection|Folder\Model[] $folderList
     * @param Term\Model                       $term
     * @return string[][] CourseSpec titles, indexed by folder.id, courseSpec.id
     */
    private function getCourseSpecsHavingUnitsInTerm(GenericCollection $folderList, Term\Model $term): array
    {
        return $this->classListFactory->findFoldersWithDueImportedAssessments(
            $folderList->getPropertyValues('id'),
            $term->getStart(),
            $term->getActualFinish()
        );
    }


    /**
     * If a folder does not have a colour, sets its colour from the configured timetable colours
     *
     * @param GenericCollection|Folder\Model[] $folderList
     */
    protected function applyFolderColours(GenericCollection $folderList): void
    {
        // Get Timetable colours to use as random colours for null set folders
        $colourList  = $this->timetableColourMapper->findAll();
        $colourIndex = 0;

        $colourFactory = new ColorFactory();
        $colourCache   = new GenericCollection(Color::class);
        foreach ($folderList as $folder) {
            if (!$folder->getColour()) {
                $hexColour = $colourList[$colourIndex]->getHexColour();

                // Build colour object from hex string and cache the colour object
                if (!$colourCache->offsetExists($hexColour)) {
                    $colourCache->setOffset($hexColour, $colourFactory->build($hexColour));
                }

                $folder->setColour($colourCache->getOffset($hexColour));
                $colourIndex = ($colourIndex + 1) % $colourList->count();
            }
        }
    }
}
