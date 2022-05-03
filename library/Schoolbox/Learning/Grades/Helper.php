<?php

/**
 * @copyright Copyright 2022, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Grades;

use Alaress_Date_Format;
use Schoolbox\Assessment\Term\Model as Term;
use Schoolbox\Collection;
use Schoolbox\GenericCollection;
use Schoolbox\Learning\Assessment\DataModel\MapperProxy as AssessmentMapperProxy;
use Schoolbox\Learning\Assessment\DueWork\Model as DueWork;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\Assessment\Lti\Model as Lti;
use Schoolbox\Learning\Assessment\Project;
use Schoolbox\Learning\Assessment\Visibility;
use Schoolbox\Learning\Markbook\Markbook;
use Schoolbox\Learning\MarkScheme\Factory as MarkSchemeFactory;
use Schoolbox\Learning\Quiz\Model as Quiz;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Permission\Access\Access;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Schoolbox\Resource\Folder\Model as Folder;
use Schoolbox\User;

/**
 * Helper class to generate grade pages
 */
class Helper
{
    private FolderMapper $folderMapper;

    private AssessmentMapperProxy $assessmentMapperProxy;

    private Access $access;

    private MarkSchemeFactory $markSchemeFactory;

    public function __construct(
        FolderMapper $folderMapper,
        AssessmentMapperProxy $assessmentMapperProxy,
        Access $access,
        MarkSchemeFactory $markSchemeFactory
    ) {
        $this->folderMapper          = $folderMapper;
        $this->assessmentMapperProxy = $assessmentMapperProxy;
        $this->access                = $access;
        $this->markSchemeFactory     = $markSchemeFactory;
    }

    /**
     * Generates data for grades
     * @param User                $viewer
     * @param User                $viewee
     * @param Term                $term
     * @param Alaress_Date_Format $from
     * @param Alaress_Date_Format $to
     * @return array
     * @throws MapperException
     */
    public function generateGrades(User $viewer, User $viewee, Term $term, Alaress_Date_Format $from, Alaress_Date_Format $to)
    {
        // Add folders with response or feedback
        $folders = $this->folderMapper->findFoldersWithInteractionsForStudent($viewee, $term);
        /* For a given (Folder, Year Level) relationship, Grades Pages can be optionally available
         * This is specified in adminv2/lists/year-levels
         *
         * It's kind of inefficient because we just hydrated a bunch of interactions + folders
         * which might potentially be discarded entirely
         */
        $displayFolderIds = $this->folderMapper->filterIdsForGradePage($folders->getPropertyValues('id'), $term);
        $folders          = $folders->filter(
            static function (Folder $folder) use ($displayFolderIds) {
                return in_array($folder->getId(), $displayFolderIds);
            }
        )->indexBy('id');

        // Get all assessments for this user in the specified term, grouped by folder
        $assessmentsPerFolder = $this->assessmentMapperProxy
            ->findAllByStudentBetweenDates($viewee, false, $from, $to, [], $displayFolderIds)
            ->filter(function (IAssessable $a) use ($viewee) {
                return $a->isPublished($viewee) && $a->isParticipant($viewee);
            })
            ->groupByCallback(function (IAssessable $assessment) {
                return $assessment->getBaseContext()->getFolder()->getId();
            });

        // Load Teachers
        foreach ($folders as $folder) {
            $folder->loadTeachers();
        }

        /** @var GenericCollection|Markbook[] Indexed by folder.id */
        $markbooks = new GenericCollection(Markbook::class);
        // Generate markbooks for these assessments
        foreach ($assessmentsPerFolder as $folderId => $assessments) {
            $assessment = $assessments->first();
            if ($assessment instanceof IAssessable) {
                $overallMarkScheme = $this->markSchemeFactory->spawnForFolder(
                    $folders->offsetGet($folderId),
                    $this->access->isInstructor($viewer, $assessment->getBaseContext()->getFolder())
                );
                $markbooks->setOffset($folderId, new Markbook($assessments, $overallMarkScheme, $viewer));
            }
        }

        // remove folders without assessments
        $folders = $folders->filter(
            static function (Folder $folder) use ($assessmentsPerFolder) {
                return in_array($folder->getId(), $assessmentsPerFolder->keys());
            }
        )->indexBy('id');
        $folders->sortNaturally('name');

        return [$folders, $markbooks, $assessmentsPerFolder];
    }

    /**
     * Filter assessments with given work types
     * @param User       $viewer
     * @param User       $viewee
     * @param Collection $assessments
     * @param Collection $workTypes
     * @return Collection
     * @throws MapperException
     */
    public function filterAssessments(User $viewer, User $viewee, Collection $assessments, Collection $workTypes)
    {
        $visibility                  = new Visibility($this->access);
        $workTypeAssessmentsByFolder = new Collection();
        foreach ($assessments as $folderId => $folderAssessments) {
            $workTypeAssessments = new Collection();
            foreach ($folderAssessments as $folderAssessment) {
                if (
                    ($folderAssessment instanceof Project || $folderAssessment instanceof DueWork || $folderAssessment instanceof Quiz || $folderAssessment instanceof Lti) &&
                    $folderAssessment->getWorkType() &&
                    \in_array($folderAssessment->getWorkType()->getId(), $workTypes->getPropertyValues('id'), true) &&
                    $visibility->isVisibleToUser($folderAssessment, $viewee, true, null, !$this->access->isInstructor($viewer, $folderAssessment->getBaseContext()->getFolder()))
                ) {
                    $workTypeAssessments->add($folderAssessment);
                }
            }
            if ($workTypeAssessments->count() > 0) {
                $workTypeAssessmentsByFolder->offsetSet($folderId, $workTypeAssessments);
            }
        }
        return $workTypeAssessmentsByFolder;
    }
}
