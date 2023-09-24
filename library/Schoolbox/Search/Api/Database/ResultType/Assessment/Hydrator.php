<?php

/**
 * @copyright Copyright 2023, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Search\Api\Database\ResultType\Assessment;

use Schoolbox\Permission\Access\Access;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Learning\Assessment\AssessmentType;
use Schoolbox\Learning\Assessment\DataModel\Mapper as AssessmentMapper;
use Schoolbox\Learning\Assessment\DataModel\Model as AssessmentModel;
use Schoolbox\Resource\Folder\Model as FolderModel;
use Schoolbox\Search\Api\Cursor\RawResult;
use Schoolbox\Search\Api\Hydrator\BaseHydrator;
use Schoolbox\Search\Api\Hydrator\FullResult;
use Schoolbox\User;

/**
 * Hydrator for link results.
 */
class Hydrator extends BaseHydrator
{
    /**
     * Base type for this type of result.
     */
    public const BASE_TYPE = 'assessment';

    /**
     * Constructor
     *
     * @param AssessmentMapper $assessmentMapper Used to fetch assessments for hydration.
     * @param Access           $access           Used to determine if the current user may view this page.
     */
    public function __construct(
        private AssessmentMapper $assessmentMapper,
        private Access $access,
    ) {
    }

    /**
     * @inheritdoc
     */
    protected function getBaseType(): string
    {
        return self::BASE_TYPE;
    }

    /**
     * @inheritdoc
     */
    public function hydrate(string $keyword, array $matches, User $viewer): array
    {
        // Find all links that match $matches.
        $acceptedMatches = array_filter($matches, [$this, 'accepts']);

        $ids = array_map(fn (RawResult $match) => $match->id, $acceptedMatches);
        if (empty($ids)) {
            return [];
        }

        // Find the matching links.
        $assessments = $this->assessmentMapper->findByColumn('id', $ids);

        // And map to a full result.
        $fullResults = array_map(
            function (RawResult $match) use ($assessments, $viewer) {
                $assessment = $assessments->getBy('id', $match->id);

                if ($assessment instanceof AssessmentModel) {
                    // Note $match->type will be something like 'assessment.dueWork'.
                    $matchTypeTokens = explode('.', $match->type);
                    $assessmentType = $matchTypeTokens[1];

                    if ($assessmentType === 'lti') {
                        $folder = $assessment->getLtiAssessment()->getAssessmentModel()->getAssessmentContext()->getFolder();
                    } else {
                        $folder = (
                            ($assessment->getContextUnitImplementation() !== null)
                            ? $assessment->getContextUnitImplementation()->getFolder()
                            : $assessment->getContextUnitSpecification()?->getFolder()
                        );
                    }

                    if ($folder === null) {
                        throw new \LogicException('No folder found for assessment ' . $assessment->getId());
                    }

                    if (!$this->assessmentIsViewableByUser($assessment, $assessmentType, $folder, $viewer)) {
                        return null;
                    }

                    // If the user does not have access to the assessment's folder, do not show the search result.
                    $assessmentAccess = $this->access->getFolderAccess($viewer, $folder->getId());
                    if ($assessmentAccess < UserAccess::ACCESS_READ) {
                        return null;
                    }

                    // Checklist
                    //  * dueWork - results appear, nested in folders - DONE.
                    //  * lessonPlan - results appear, nested in folders - DONE.
                    //  * lti - results appear, nested in folders - DONE.
                    //  * project - results appear, nested in folders - DONE.
                    //  * quiz - results appear, nested in folders - DONE.
                    //  * task - results appear, nested in folders - DONE.

                    return new FullResult(
                        $match,
                        $match->id,
                        $assessment->getTitle(),
                        $this->buildBaseUrl()
                             ->withPath("/learning/assessments/{$assessment->getId()}")
                             ->__toString(),
                        $match->type,
                        $match->relevance,
                        $assessment->getUpdatedAt(),
                        $folder,
                        null,
                        null,
                        AssessmentType::getIconClassSuffixForType($assessmentType),
                        strip_tags($assessment->getDescription()),
                        match ($assessmentType) { // Get creatorId depending on type.
                            AssessmentType::TYPE_DUE_WORK => $assessment->getDueWork()->getOwner(),
                            AssessmentType::TYPE_QUIZ => $assessment->getQuiz()->getUser(),
                            AssessmentType::TYPE_TASK => $assessment->getTask()->getOwner(),
                            AssessmentType::TYPE_PROJECT => $assessment->getProject()->getOwner(),
                            AssessmentType::TYPE_LTI_ASSESSMENT => null,
                            AssessmentType::TYPE_LESSON_PLAN => $assessment->getLessonPlan()->getCreatedBy()
                        },
                        [$folder],
                        [],
                    );
                }

                return null;
            },
            $matches
        );

        // Filter out anything missing.
        return array_filter($fullResults, fn (?FullResult $x) => isset($x));
    }

    /**
     * @param AssessmentModel $assessment
     * @param string $assessmentType
     * @param FolderModel $assessmentFolder
     * @param User $user
     * @return bool
     */
    private function assessmentIsViewableByUser(AssessmentModel $assessment, string $assessmentType, FolderModel $assessmentFolder, User $user): bool
    {
        // 1st attempt at filtering assessments to those students should see.
        // TODO: Find out if there is a better way.

        $userRole = $user->getRole();

        if ($userRole->isStudent()) {
            // Students should only see assessments that have reached their open date (AC for SA-16014).
            if ($assessment->getOpenDate() === null || $assessment->getOpenDate()->isFuture()) {
                return false;
            }

            $impl = $assessment->getContextUnitImplementation();

            // TODO: Figure out how to tell whether a student is involved in a folder/class/group from here.
            // May be useful:
            //$impl->getFolder()->isGroup();
            //$impl->getFolder()->isClass();
            //IsInvolvedInClass()

            $folderId = $assessmentFolder->getId();

            // Should only see assessments in their own classes/groups (AC for SA-16014).
            $folderIsInSubjectCollection = false;
            foreach ($user->getSubjectCollection() as $subject) {
                if ($folderId === $subject['id']) {
                    $folderIsInSubjectCollection = true;
                }
            }
//            $folderIsInGroupCollection = false;
//            foreach ($user->getGroupCollection() as $group) {
//                if ($folderId === $group['id']) {
//                    $folderIsInGroupCollection = true;
//                }
//            }
//            if (!$folderIsInSubjectCollection && !$folderIsInGroupCollection) {
//                return false;
//            }

            // Students should only see assessments that they have not been excluded from (AC for SA-16014).
            // TODO: Find whether user is a participant somehow depending on $assessmentType?
            //       Then if user is not a participant, return false?
            // $userParticipant = $assessment->getParticipantIfExtant();
            // if ($userParticipant === null) {
            //     // User is not participant in assessment.
            //     return false;
            // }
        } elseif ($userRole->isStaff()) {
             // Staff should see assessments in their own classes (AC for SA-16014).
             // Staff should see assessments in units that they have permission to access (AC for SA-16014).
             return true;
         }

        return true;
    }
}
