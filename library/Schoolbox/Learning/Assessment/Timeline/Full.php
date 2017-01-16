<?php
/**
 * Timeline
 *
 * Takes a Due Work's Responses and Feedbacks and arranges
 * them into a neatish timeline suitable for a forum-like
 * nested structure ordered by submittedAt and publishedAt
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Learning\Assessment\Timeline;

use Alaress_Date_Format;
use \Schoolbox\Collection;
use Schoolbox\Learning\Assessment\IFeedback;
use Schoolbox\Learning\Assessment\IResponse;
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
     * Get the timeline, as seen by a teacher on their child's summary page
     * As a parent, you cannot see draft submissions or unpublished feedback
     *
     * @return Collection
     */
    public function getForParent()
    {
        return $this->getTimeline(false, false);
    }

    /**
     * Get the timeline, as seen by a teacher grading a student.
     * As a teacher, you cannot see submissions the student has drafted but not yet submitted.
     *
     * @return Collection
     */
    public function getForTeacher()
    {
        return $this->getTimeline(true, false);
    }


    /**
     * Get the timeline, as seen by a student on their summary page
     * As a student, you cannot see feedback the teacher has drafted but not yet published
     *
     * @return Collection
     */
    public function getForStudent()
    {
        return $this->getTimeline(false, true);
    }


    /**
     * Get the admin-only form of a timeline, including EVERYTHING for when you need to perform admin functions
     * As an admin, you can see damn well everything.
     *
     * @return Collection
     */
    public function getForAdmin()
    {
        return $this->getTimeline(true, true);
    }


    /**
     * Get the timeline appropriate to the given user.
     * Is probably a *bit* heavy-handed in its permission checking...
     *
     * @param User $u
     * @return Collection
     */
    public function getForUser(User $u)
    {
        $r = $u->getRole();
        $p = $this->getParticipant()->getUser();

        if ($u->isSuperuser() || ($u->getId() == $p->getId() && $r->isStaff())) {
            return $this->getForAdmin();
        } elseif ($r->isStudent()) {
            if ($u->getId() == $p->getId()) {
                return $this->getForStudent();
            }
        } elseif ($r->isStaff()) {
            return $this->getForTeacher();
        } elseif ($r->isParent()) {
            $p->loadParents();
            if ($p->isChildOf($u)) {
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
     * @param bool $includeUnpublishedFeedback
     * @param bool $includeDraftResponses
     * @return Collection
     */
    protected function getTimeline($includeUnpublishedFeedback, $includeDraftResponses)
    {
        // TODO evaluate the appropriateness of caching here
//        if (!$this->getHasRecombined()) {
        $this->recombine($includeUnpublishedFeedback, $includeDraftResponses);
//            $this->setHasRecombined(true);
//        }

        return $this->getRecombined();
    }


    /**
     * Combining the collections
     *
     * 1 + 1 = 1
     *
     * @param bool $includeUnpublishedFeedback
     * @param bool $includeDraftResponses
     */
    protected function recombine($includeUnpublishedFeedback, $includeDraftResponses)
    {
        $processedFeedback = [];

        $isFeedbackVisible = $this->getParticipant()->getAssessable()->isFeedbackVisibleToUser($this->getParticipant()->getUser());

        foreach ($this->getParticipant()->getAllResponses() as $currentResponse) {

            /* Only show Submitted submissions (ignore drafts)
             */
            if ($includeDraftResponses || $currentResponse->isSubmitted()) {

                /* If $includeUnpublishedFeedback is false,
                 * Filter out unpublished Feedback from each $response
                 *
                 * Otherwise just use the stack that was given to us
                 * which has the unpublished feedbacks in there already
                 */
                $includedFeedback = new Collection();
                if ($isFeedbackVisible) {
                    if ($includeUnpublishedFeedback) {
                        $includedFeedback = $currentResponse->getAllFeedback();

                    } else {
                        foreach ($currentResponse->getAllFeedback() as $currentFeedback) {
                            if ($currentFeedback->isPublished()) {
                                $includedFeedback->add($currentFeedback);
                            }
                        }
                    }
                }

                /* Maintain a list of all the Feedbacks that were discovered
                 * as being part of Responses to ensure they don't get duped
                 * in the timeline
                 */
                foreach ($includedFeedback as $currentFeedback) {
                    $processedFeedback[] = $currentFeedback->getId();

                    /* Every associated feedback has a set of attached files
                     * that were added via the attachzone
                     *
                     * It also has a set of crocodocked files. These crocodocked
                     * files don't get added via the attachzone; they won't appear
                     * here normally. In the case of the Timeline, Feedback includes
                     * both the set of attached files and any Submission Files that
                     * were annotated via crocodoc.
                     */
                    foreach ($currentResponse->getAllFiles() as $currentFile) {
                        if ($currentFile->getCrocodocId()) {
                            $currentFeedback->getAllFiles()->add($currentFile);
                        }
                    }

                    /* Sort files alphabetically
                     */
                    $currentFeedback->getAllFiles()->sortBy('filename', 'asc');
                }

                /* Mutate collection :/
                 * This seems pretty deadly, but I kinda have to mutate it anyway
                 * to display crocodocked files in the Feedback File list
                 */
                $currentResponse->setAllFeedback($includedFeedback);

                /* Sort files alphabetically
                 */
                $currentResponse->getAllFiles()->sortBy('filename', 'asc');

                $this->getRecombined()->add($currentResponse);
            }
        }

        /* Only grab unique Floating Feedbacks
         */
        if ($isFeedbackVisible) {
            foreach ($this->getParticipant()->getAllFeedbacks() as $currentFeedback) {
                if (!in_array($currentFeedback->getId(), $processedFeedback)) {

                    /* Only show Published feedbacks
                     */
                    if ($includeUnpublishedFeedback || $currentFeedback->isPublished()) {
                        $this->getRecombined()->add($currentFeedback);

                        $processedFeedback[] = $currentFeedback->getId();
                    }
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
        $this->getRecombined()->sortByCallback(function($a, $b) {
            $date1 = null;
            $date2 = null;

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
        });
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
            }
        }

        // still draft; sift to the top of any list
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
