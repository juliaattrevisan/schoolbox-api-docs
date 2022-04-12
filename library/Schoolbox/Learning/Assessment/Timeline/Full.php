<?php

/**
 * Timeline
 *
 * Takes a Due Work's Responses and Feedbacks and arranges
 * them into a neatish timeline suitable for a forum-like
 * nested structure ordered by submittedAt and publishedAt
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Learning\Assessment\Timeline;

use Alaress_Date_Format;
use Closure;
use Schoolbox\Collection;
use Schoolbox\Learning\Assessment\AbstractAssessment;
use Schoolbox\Learning\Assessment\IFeedback;
use Schoolbox\Learning\Assessment\IResponse;
use Schoolbox\Learning\Assessment\DueWork;
use Schoolbox\Learning\Assessment\Participant;
use Schoolbox\User;

class Full
{

    /**
     * @var Participant
     */
    protected $participant;

    /**
     * @var Collection
     */
    protected $recombined;

    /**
     * Save yourself some time by returning the cached
     * version of the recombination if it exists
     *
     * @var bool
     */
    protected $hasRecombined = false;

    /**
     * Timeline constructor.
     *
     * @param Participant $participant
     */
    public function __construct(Participant $participant)
    {
        $this->setParticipant($participant);
        $this->setRecombined(new Collection());
    }


    /**
     * Get the timeline, as seen by a teacher on their child's summary page.
     * As a parent, you:
     * * cannot see unpublished self-reflection feedback
     * * cannot see unpublished instructor feedback
     * * cannot see draft responses
     * * can see published feedback if assessment allows parents to see it
     *
     * @return Collection
     */
    public function getForParent()
    {
        return $this->getTimeline(false, false, false, AbstractAssessment::MARK_VISIBILITY_PARENT_STUDENT);
    }

    /**
     * Get the timeline, as seen by a teacher grading a student.
     * As a teacher, you:
     * * cannot see unpublished self-reflection feedback
     * * can see unpublished instructor feedback
     * * cannot see draft responses
     * * can see published feedback
     *
     * @return Collection
     */
    public function getForTeacher()
    {
        return $this->getTimeline(false, true, true, AbstractAssessment::MARK_VISIBILITY_PRIVATE);
    }


    /**
     * Get the timeline, as seen by a student on their summary page.
     * As a student, you:
     * * can see unpublished self-reflection feedback
     * * cannot see unpublished instructor feedback
     * * can see draft responses
     * * can see published feedback if assessment allows students to see it
     *
     * @return Collection
     */
    public function getForStudent()
    {
        return $this->getTimeline(true, false, true, AbstractAssessment::MARK_VISIBILITY_STUDENT);
    }


    /**
     * Get the admin-only form of a timeline, including EVERYTHING for when you
     * need to perform admin functions.
     * As an admin, you:
     * * can see damn well everything
     *
     * @return Collection
     */
    public function getForAdmin()
    {
        return $this->getTimeline(true, true, true, AbstractAssessment::MARK_VISIBILITY_PRIVATE);
    }


    /**
     * Get the timeline appropriate to the given user.
     * Is probably a *bit* heavy-handed in its permission checking...
     *
     * @param User $user
     * @return Collection
     */
    public function getForUser(User $user)
    {
        $role        = $user->getRole();
        $participant = $this->getParticipant()->getUser();

        if ($user->isSuperuser() || ($user->getId() == $participant->getId() && $role->isStaff())) {
            return $this->getForAdmin();
        }
        if ($role->isStudent()) {
            if ($user->getId() == $participant->getId()) {
                return $this->getForStudent();
            }
        } elseif ($role->isStaff()) {
            return $this->getForTeacher();
        } elseif ($role->isParent()) {
            $participant->loadParents();
            if ($participant->isChildOf($user)) {
                return $this->getForParent();
            }
        }

        // no timeline for you
        return new Collection();
    }

    /**
     * Get the timeline
     *
     * Recombine if required, otherwise return a cached
     * version of the recombination because reasons
     *
     * @param bool $includeUnpublishedSelfReflectionFeedback
     * @param bool $includeUnpublishedInstructorFeedback
     * @param bool $includeDraftResponses
     * @param int  $minimumVisibility          Assessment mark's visibility must be at least this
     * @return Collection
     */
    protected function getTimeline(
        $includeUnpublishedSelfReflectionFeedback,
        $includeUnpublishedInstructorFeedback,
        $includeDraftResponses,
        $minimumVisibility
    ) {
        // TODO evaluate the appropriateness of caching here
//        if (!$this->getHasRecombined()) {
        $this->recombine(
            $includeUnpublishedSelfReflectionFeedback,
            $includeUnpublishedInstructorFeedback,
            $includeDraftResponses,
            $minimumVisibility
        );
//            $this->setHasRecombined(true);
//        }

        return $this->getRecombined();
    }


    /**
     * Combining the collections
     *
     * 1 + 1 = 1
     *
     * @param bool $includeUnpublishedSelfReflectionFeedback
     * @param bool $includeUnpublishedInstructorFeedback
     * @param bool $includeDraftResponses
     * @param int  $minimumVisibility
     */
    protected function recombine(
        $includeUnpublishedSelfReflectionFeedback,
        $includeUnpublishedInstructorFeedback,
        $includeDraftResponses,
        $minimumVisibility
    ) {
        $processedFeedback = [];

        $isFeedbackVisible = $this->getParticipant()->getAssessable()->getMarkVisibility() >= $minimumVisibility;

        // Filter for feedback display:
        // If somehow not due work feedback: exclude
        // If a self-assess:                 if published show,
        //                                   if not respect $includeUnpublishedSelfReflectionFeedback
        // If instructor:                    if published respect $isFeedbackVisible,
        //                                   if not respect $includeUnpublishedInstructorFeedback
        $feedbackFilter = function (IFeedback $feedback) use (
            $includeUnpublishedSelfReflectionFeedback,
            $includeUnpublishedInstructorFeedback,
            $isFeedbackVisible
        ) {
            if ($feedback instanceof Duework\Feedback\Model) {
                if ($feedback->isSelfAssessment()) {
                    if ($feedback->isPublished()) {
                        return true;
                    }
                    return $includeUnpublishedSelfReflectionFeedback;
                }
                if ($feedback->isPublished()) {
                    return $isFeedbackVisible;
                }
                return $includeUnpublishedInstructorFeedback;
            }
            return false;
        };

        /** @var DueWork\Response\Model $currentResponse */
        foreach ($this->getParticipant()->getAllResponses() as $currentResponse) {
            /* Only show Submitted submissions (ignore drafts)
             */
            if ($includeDraftResponses || $currentResponse->isSubmitted()) {
                // Exclude feedback which doesn't match the filter
                /** @var Duework\Feedback\Model[] $includedFeedback */
                $includedFeedback = $currentResponse->getAllFeedback()->filter($feedbackFilter);

                /* Maintain a list of all the Feedbacks that were discovered
                 * as being part of Responses to ensure they don't get duped
                 * in the timeline
                 */
                foreach ($includedFeedback as $currentFeedback) {
                    $processedFeedback[] = $currentFeedback->getId();

                    /* Every associated feedback has a set of attached files
                     * that were added via the attachzone
                     *
                     * * If feedback is for specific response, for all files in that *response* that have a KamiDoc,
                     *   add the file to the feedback.
                     * * If feedback is floating, we do *not* add any response files to it. Floating feedbacks now
                     *   only exist if no response exists, hence no response file could have been annotated at the
                     *   time of feedback creation.
                     *   --> This doesn't apply for the case where a teacher annotated on a KamiDoc and then
                     *       published a feedback comment on the quick mark screen. (SA-6760)
                     */
                    foreach ($currentResponse->getAllFiles() as $currentFile) {
                        if ($currentFile->getKami()) {
                            $currentFeedback->getAllFiles()->add($currentFile);
                        }
                    }

                    /* Sort files alphabetically
                     */
                    $currentFeedback->getAllFiles()->sortBy('filename', 'asc');
                }

                /* Sort files alphabetically
                 */
                $currentResponse->getAllFiles()->sortBy('filename', 'asc');

                // Exclude all feedback except ones explicitly allowed
                $currentResponse->setAllFeedback($includedFeedback);

                $this->getRecombined()->add($currentResponse);
            }
        }

        /* Only grab unique Floating Feedbacks that match the filter
         */
        if ($isFeedbackVisible) {
            /** @var DueWork\Feedback\Model $currentFeedback */
            foreach ($this->getParticipant()->getAllFeedbacks() as $currentFeedback) {
                if (
                    !\in_array($currentFeedback->getId(), $processedFeedback, true)
                    && $feedbackFilter($currentFeedback)
                ) {
                    // Adding Kami documents for visibility.
                    // TODO: This needs to be reconsidered once the core solution for SA-6464 is provided.
                    if (isset($currentResponse)) {
                        foreach ($currentResponse->getAllFiles() as $currentFile) {
                            // If the status is other than "done", nobody has viewed it online.
                            if ($currentFile->getKami() && $currentFile->getKami()->isStatusDone()) {
                                $currentFeedback->getAllFiles()->add($currentFile);
                            }
                        }
                    }
                    $this->getRecombined()->add($currentFeedback);

                    $processedFeedback[] = $currentFeedback->getId();
                }
            }
        }

        /* Now sort them with a callback attack
         * Four possible cases to consider:
         *
         * Response compared with Response
         * Response compared with Feedback
         * Feedback compared with Feedback
         * Feedback compared with Response
         *
         * Since both have different date fields to compare against
         */
        $this->getRecombined()->sortByCallback(Closure::fromCallable([$this, 'sortRecombined']));
    }

    /**
     * Sort callback for recombined responses+feedback.
     *
     * @param IResponse|IFeedback $a
     * @param IResponse|IFeedback $b
     * @return int
     */
    protected function sortRecombined($a, $b): int
    {
        /* Compare dates
         */
        $date1 = $this->getDate($a);
        $date2 = $this->getDate($b);

        /* Chooze wisely
         */
        if ($date1 && $date2) {
            return $date2->compare($date1);
        }

        throw new \InvalidArgumentException('Null date provided');
    }

    /**
     * @param IResponse|IFeedback $interaction
     * @return Alaress_Date_Format
     */
    protected function getDate($interaction)
    {
        if ($interaction instanceof IResponse) {
            if (!$interaction->getSubmittedAt()->isNull()) {
                return $interaction->getSubmittedAt();
            }
        } elseif ($interaction instanceof IFeedback) {
            if (!$interaction->getPublishedAt()->isNull()) {
                return $interaction->getPublishedAt();
            } elseif (!$interaction->getUpdatedAt()->isNull()) {
                return $interaction->getUpdatedAt();
            }
        }

        //Item had none of the above dates... We'll try "now"?
        return Alaress_Date_Format::now();
    }

    /* Getters/Setters
     */

    /**
     * @return Participant
     */
    protected function getParticipant()
    {
        return $this->participant;
    }

    /**
     * @param Participant $assessment
     * @return $this
     */
    protected function setParticipant($assessment)
    {
        $this->participant = $assessment;
        return $this;
    }

    /**
     * @return Collection
     */
    protected function getRecombined()
    {
        return $this->recombined;
    }

    /**
     * @param Collection $recombined
     * @return $this
     */
    protected function setRecombined($recombined)
    {
        $this->recombined = $recombined;
        return $this;
    }

    /**
     * @return boolean
     */
    protected function getHasRecombined()
    {
        return $this->hasRecombined;
    }

    /**
     * @param boolean $hasRecombined
     * @return $this
     */
    protected function setHasRecombined($hasRecombined)
    {
        $this->hasRecombined = $hasRecombined;
        return $this;
    }
}
