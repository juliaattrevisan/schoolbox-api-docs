<?php

namespace Schoolbox\Learning\Assessment\DueWork\Feedback;

use Schoolbox\Learning\Assessment\Feedback\IFeedbackDataSource;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\DueWork\StateMapper;
use Schoolbox\Mapper\Exception;
use Schoolbox\Mapper\Exception\Deleted;

class Rescore
{
    /**
     * @param IFeedbackDataSource $feedbackDataSource
     * @param StateMapper $stateMapper
     */
    public function __construct(private IFeedbackDataSource $feedbackDataSource, private StateMapper $stateMapper)
    {
    }


    /**
     * Don't make any database changes; used for a preview screen
     *
     * @param IAssessable $assessment
     * @return array
     * @throws Deleted
     * @throws Exception
     */
    public function preview(IAssessable $assessment): array
    {
        return $this->generate($assessment);
    }


    /**
     * Persist updates in the db and return a list of changed records;
     * used for a confirmation screen
     *
     * @param IAssessable $assessment
     * @return array
     * @throws Deleted
     * @throws Exception
     */
    public function perform(IAssessable $assessment): array
    {
        return $this->generate($assessment, true);
    }


    /**
     * Takes a fully hydrated $assessment via findByIdForReview()) and recalculates
     * normalised and human readable marks using $assessment's current settings for
     * Mark Type
     *
     * This is used to change the feedbacks' mark type after they've been marked.
     *
     * @param IAssessable $assessment
     * @param bool $perform
     * @return array
     * @throws Exception
     * @throws Deleted
     */
    private function generate(IAssessable $assessment, bool $perform = false): array
    {
        $changes = [];

        /* Submission States with a normalised grade might get recomputed: instead, use the stateMapper
         * to try and preserve them.
         */
        $submissionStates = $this->stateMapper->findAll()->indexBy('displayAs');

        foreach ($assessment->getAllParticipants() as $participant) {
            $hasChanges = false;

            /* Process authoritative published feedback and any existing drafts
             */
            $feedbacks = [];

            $publishedFeedback = $participant->findMostRecentInstructorGivenPublishedFeedback();
            $draftFeedback = $participant->findMostRecentInstructorGivenFeedback();

            /* These two feedbacks might actually be the same, so uniquify this list
             */
            if ($publishedFeedback) {
                $feedbacks[$publishedFeedback->getId()] = $publishedFeedback;
            }

            if ($draftFeedback) {
                $feedbacks[$draftFeedback->getId()] = $draftFeedback;
            }

            /** @var \Schoolbox\Learning\Assessment\DueWork\Feedback\Model $authoritativeFeedback */
            foreach ($feedbacks as $authoritativeFeedback) {
                if ($authoritativeFeedback) {
                    /* If it's a Submission State, just leave everything as it was: preserve the status quo
                     */
                    if (null !== $submissionStates->getOffset($authoritativeFeedback->getHumanReadableMark())) {
                        $newStudentMark = $authoritativeFeedback->getHumanReadableMark();
                        $newInstructorMark = $authoritativeFeedback->getHumanReadableInstructorMark();
                        $newNormalisedMark = $authoritativeFeedback->getNormalisedMark();
                    } else {
                        /* Note that feedbacks might simply be requests to resubmit: in this case, there'll be a null
                         * normalised mark and no data to change or re-evaluate.
                         */
                        if (null === $authoritativeFeedback->getNormalisedMark()) {
                            continue;
                        }

                        /* Recalibrate normalised mark from the human readable mark, as if you'd done that on the ui
                         * This prevents you having a normalised mark that would otherwise have been unattainable,
                         * such as a value between the lower and upper bounds of Letter Grades.
                         */
                        $newStudentMark = $assessment->getMarkScheme()->getHumanReadableMark($authoritativeFeedback->getNormalisedMark());
                        $newInstructorMark = $assessment->getInstructorMarkScheme()->getHumanReadableMark($authoritativeFeedback->getNormalisedMark());
                        $newNormalisedMark = $assessment->getInstructorMarkScheme()->getNormalisedMark($newInstructorMark);

                        $hasChanges = true;
                    }

                    $changes[] = [
                        'user' => $participant->getUser(),
                        'published' => $authoritativeFeedback->isPublished(),
                        'old' => [
                            'normalised' => $authoritativeFeedback->getNormalisedMark(),
                            'student' => $authoritativeFeedback->getHumanReadableMark(),
                            'instructor' => $authoritativeFeedback->getHumanReadableInstructorMark(),
                        ] ,
                        'new' => [
                            'normalised' => $newNormalisedMark,
                            'student' => $newStudentMark,
                            'instructor' => $newInstructorMark
                        ]
                    ];

                    /* Mutate changes
                     */
                    if ($perform && $hasChanges) {
                        $authoritativeFeedback->setNormalisedMark($newNormalisedMark)
                            ->setHumanReadableMark($newStudentMark)
                            ->setHumanReadableInstructorMark($newInstructorMark);

                        $this->feedbackDataSource->save($authoritativeFeedback);
                    }
                }
            }
        }

        return $changes;
    }
}
