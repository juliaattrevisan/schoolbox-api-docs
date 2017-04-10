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
use Schoolbox\Mapper\DefinitionException;
use Schoolbox\Resource\Folder\Model as Folder;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Schoolbox\User;
use Schoolbox\Mapper\Exception as MapperException;
use Timetable_ColourMapper;
use Schoolbox\Learning\Course\DataModel\Specification\Model as CourseSpecification;
use Schoolbox\Learning\Course\DataModel\Specification\Mapper as CourseSpecificationMapper;
use Schoolbox\Learning\Course\DataModel\Implementation\Model as CourseImplementation;
use Schoolbox\Learning\Course\DataModel\Implementation\Mapper as CourseImplementationMapper;

/**
 * Generates a list of classes, along with the courses imported to those classes, suitable
 * for rendering on either:
 * * Classes Dashboard Component, or
 * * Classes Page
 */
class ClassList
{

    /** @var User */
    private $user;

    /** @var FolderMapper */
    private $folderMapper;

    /** @var Timetable_ColourMapper */
    private $timetableColourMapper;

    /** @var CourseSpecificationMapper */
    private $courseSpecMapper;

    /** @var CourseImplementationMapper */
    private $courseImplMapper;


    /**
     * @param User                       $user
     * @param FolderMapper               $folderMapper
     * @param Timetable_ColourMapper     $timetableColourMapper
     * @param CourseSpecificationMapper  $courseSpecMapper
     * @param CourseImplementationMapper $courseImplMapper
     */
    public function __construct(
        User $user,
        FolderMapper $folderMapper,
        Timetable_ColourMapper $timetableColourMapper,
        CourseSpecificationMapper $courseSpecMapper,
        CourseImplementationMapper $courseImplMapper
    ) {
        $this->user                  = $user;
        $this->folderMapper          = $folderMapper;
        $this->timetableColourMapper = $timetableColourMapper;
        $this->courseSpecMapper      = $courseSpecMapper;
        $this->courseImplMapper      = $courseImplMapper;
    }


    /**
     * Returns a set of of classes, along with subsidiary data, suitable for
     * rendering on the classes component or page
     *
     * @param Term                $term
     * @param Alaress_Date_Format $now
     * @return array
     * @var Term                    return['term']
     * @var Collection|Folder[]     return['classList']
     * @var string[]                return['classTypes'] Indexed by folder.id
     * @var CourseSpecification[][] return['courses'] Grouped by folder.id
     * @throws DefinitionException
     * @throws MapperException
     */
    public function getClassList(Term $term, Alaress_Date_Format $now)
    {
        $classTypes    = [];
        $folderList    = $this->folderMapper->createCollection();
        $isTermCurrent = $term->contains($now);

        // Are we looking at current Classes, or historical ?
        if ($isTermCurrent) {
            /* Convert external subjects to folders */
            foreach ($this->user->getSubjectCollection() as $subject) {
                try {
                    $folder = $this->folderMapper->findByCode($subject['code']);
                    $folderList = $folderList->mergeUnique(new GenericCollection(Folder::class, [$folder]),'id');
                    $classTypes[$folder->getId()] = $subject['classType'];
                } catch (MapperException $e) {
                    //not found
                }
            }
        } else {
            // Is user a teacher or student ?
            if ($this->user->getRole()->isStaff()) {
                $folderList = $this->folderMapper->findFoldersWithFeedbacksFromTeacher($this->user, $term);
            } elseif ($this->user->getRole()->isStudent()) {
                $folderList = $this->folderMapper
                    ->findFoldersWithResponses($this->user, $term)
                    ->mergeUnique($this->folderMapper->findFoldersWithFeedbackToStudent($this->user, $term), 'id');
            }
        }

        $courseSpecs = $this->getCourseSpecsHavingUnitsInTerm($folderList, $term);


        $this->applyFolderColours($folderList);

        return [
            'term'       => $term,
            'classList'  => $folderList,
            'classTypes' => $classTypes,
            'courses'    => $courseSpecs,
        ];
    }


    /**
     * Returns a sets of CourseSpecs, grouped by class folder
     *
     * Filters to only return CourseSpecs that have units imported to class folder for specified term
     *
     * @param GenericCollection|Folder[] $folderList
     * @param Term                       $term
     * @return Collection|CourseSpecification[][] Grouped by folder.id
     */
    protected function getCourseSpecsHavingUnitsInTerm(GenericCollection $folderList, Term $term)
    {
        $courseSpecsPerFolder = new Collection();

        // Get CourseImpls, then group by Folder
        $courseImpls = $this->courseImplMapper->findByFolderId($folderList->getColumnValues('id'));

        /** @var CourseImplementation[][] $courseImplsPerClass */
        $courseImplsPerClass = $courseImpls->groupByCallback(function (CourseImplementation $courseImpl) {
            return $courseImpl->getFolder()->getId();
        });

        // If a course import has unit implementations that are within the specified
        // term, add the course spec to the folder.
        /**
         * @var CourseImplementation[] $courseImpls
         */
        foreach ($courseImplsPerClass as $folderId => $courseImpls) {
            $courseSpecsPerFolder[$folderId] = $this->courseSpecMapper->createCollection();
            $hasUnitsInTerm                  = false;
            foreach ($courseImpls as $courseImpl) {
                foreach ($courseImpl->getUnits() as $unitImpl) {
                    if ($unitImpl->getOpenDate()->compareDateOnly($term->getStart()) >= 0) {
                        if ($term->getActualFinish()->isNull() || $unitImpl->getDueDate()->compareDateOnly($term->getActualFinish()) <= 0) {
                            $hasUnitsInTerm = true;
                            break;
                        }
                    }
                }
                if ($hasUnitsInTerm) {
                    $courseSpecId                                   = $courseImpl->getSpecification()->getId();
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
        /* Get Timetable colours to use as random colours for null set folders */
        $colourList  = $this->timetableColourMapper->findAll();
        $colourIndex = 0;

        foreach ($folderList as $folder) {
            if (!$folder->getColour()) {
                /* Apply colour from list if no colour exists */
                $folder->setColour($colourList[$colourIndex]->getHexColour());
                $colourIndex = ($colourIndex + 1) % $colourList->count();
            }
        }
    }
}
