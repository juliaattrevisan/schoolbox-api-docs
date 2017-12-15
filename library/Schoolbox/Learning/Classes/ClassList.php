<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Learning\Classes;

use Alaress_Date_Format;
use Schoolbox\Assessment\Term\Model as Term;
use Schoolbox\Collection;
use Schoolbox\GenericCollection;
use Schoolbox\Learning\Course\DataModel\Specification as CourseSpecification;
use Schoolbox\Learning\Course\DataModel\Implementation as CourseImplementation;
use Schoolbox\Mapper\DefinitionException;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Resource\Folder\Model as Folder;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Schoolbox\User;
use Timetable_ColourMapper;

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

    /** @var FolderMapper */
    private $folderMapper;

    /** @var Timetable_ColourMapper */
    private $timetableColourMapper;

    /** @var CourseSpecification\Mapper */
    private $courseSpecMapper;

    /** @var CourseImplementation\Mapper */
    private $courseImplMapper;

    /**
     * @param User $user
     * @param FolderMapper $folderMapper
     * @param Timetable_ColourMapper $timetableColourMapper
     * @param CourseSpecification\Mapper $courseSpecMapper
     * @param CourseImplementation\Mapper $courseImplMapper
     */
    public function __construct(
        User $user,
        FolderMapper $folderMapper,
        Timetable_ColourMapper $timetableColourMapper,
        CourseSpecification\Mapper $courseSpecMapper,
        CourseImplementation\Mapper $courseImplMapper
    ) {
        $this->user = $user;
        $this->folderMapper = $folderMapper;
        $this->timetableColourMapper = $timetableColourMapper;
        $this->courseSpecMapper = $courseSpecMapper;
        $this->courseImplMapper = $courseImplMapper;
    }

    /**
     * Returns a set of of classes, along with subsidiary data, suitable for
     * rendering on the classes component or page
     *
     * @param Term $term
     * @param Alaress_Date_Format $now
     * @return array
     * @var Term                          return['term']
     * @var Collection|Folder[]           return['classList']
     * @var string[]                      return['classTypes'] Indexed by folder.id
     * @var CourseSpecification\Model[][] return['courses'] Grouped by folder.id
     * @throws DefinitionException
     * @throws MapperException
     */
    public function getClassList(Term $term, Alaress_Date_Format $now)
    {
        $classTypes    = [];
        $folderList    = $this->folderMapper->createCollection();
        $isTermCurrent = $term->contains($now);

        // Are we looking at current classes, or historical?

        if ($isTermCurrent) {

            // Iterate all subjects from the SIS and try to find folders with that class code

            foreach ($this->user->getSubjectCollection() as $subject) {
                try {
                    $folder                       = $this->folderMapper->findByCode($subject['code']);
                    $folderList                   = $folderList->mergeUnique(new GenericCollection(Folder::class, [$folder]), 'id');
                    $classTypes[$folder->getId()] = $subject['classType'];
                } catch (MapperException $e) {
                    // Not found
                }
            }
        }

        // Add in other folders with feedback during the term, this will pick up archived classes

        if ($this->user->getRole()->isStaff()) {
            $folderList = $folderList->mergeUnique($this->folderMapper->findFoldersWithFeedbacksFromTeacher($this->user, $term), 'id');
        } elseif ($this->user->getRole()->isStudent()) {
            /** @var GenericCollection|Folder[] $folderList */
            $folderList = $folderList->mergeUnique($this->folderMapper->findFoldersWithResponses($this->user, $term), 'id');
            $folderList = $folderList->mergeUnique($this->folderMapper->findFoldersWithFeedbackToStudent($this->user, $term), 'id');
        }

        $courseSpecs = $this->getCourseSpecsHavingUnitsInTerm($folderList, $term);

        $this->applyFolderColours($folderList);

        return [
            'term' => $term,
            'classList' => $folderList,
            'classTypes' => $classTypes,
            'courses' => $courseSpecs,
        ];
    }

    /**
     * Returns a sets of CourseSpecs, grouped by class folder
     *
     * Filters to only return CourseSpecs that have units imported to class folder for specified term
     *
     * @param GenericCollection|Folder[] $folderList
     * @param Term $term
     * @return Collection|CourseSpecification\Model[][] Grouped by folder.id
     */
    protected function getCourseSpecsHavingUnitsInTerm(GenericCollection $folderList, Term $term)
    {
        $courseSpecsPerFolder = new Collection();

        // Get course implementations, then group by Folder
        $courseImpls = $this->courseImplMapper->findByFolderId($folderList->getColumnValues('id'));

        /** @var CourseImplementation\Model[][] $courseImplsPerClass */
        $courseImplsPerClass = $courseImpls->groupByCallback(function (CourseImplementation\Model $courseImpl) {
            return $courseImpl->getFolder()->getId();
        });

        // If a course import has unit implementations that are within the specified term, add the course spec to the folder.

        /** @var CourseImplementation\Model[] $courseImpls */
        foreach ($courseImplsPerClass as $folderId => $courseImpls) {

            $courseSpecsPerFolder[$folderId] = $this->courseSpecMapper->createCollection();
            $termContainsUnits               = false;

            foreach ($courseImpls as $courseImpl) {
                foreach ($courseImpl->getUnits() as $unitImpl) {
                    if ($term->contains($unitImpl->getOpenDate()) || $term->contains($unitImpl->getDueDate())) {
                        $termContainsUnits = true;
                        break;
                    }
                }

                if ($termContainsUnits) {
                    $courseSpecId = $courseImpl->getSpecification()->getId();
                    $courseSpecsPerFolder[$folderId][$courseSpecId] = $courseImpl->getSpecification();
                }
            }
        }

        return $courseSpecsPerFolder;
    }

    /**
     * If a folder does not have a colour, sets its colour from the configured timetable colours
     *
     * @param GenericCollection|Folder[] $folderList
     */
    protected function applyFolderColours(GenericCollection $folderList)
    {
        // Get Timetable colours to use as random colours for null set folders
        $colourList = $this->timetableColourMapper->findAll();
        $colourIndex = 0;

        /** @var Folder $folder */
        foreach ($folderList as $folder) {
            if (!$folder->getColour()) {

                // Apply colour from list if no colour exists
                $folder->setColour($colourList[$colourIndex]->getHexColour());
                $colourIndex = ($colourIndex + 1) % $colourList->count();
            }
        }
    }
}
