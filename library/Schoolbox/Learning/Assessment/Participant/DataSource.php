<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Learning\Assessment\Participant;

use External_ClassInterface;
use Schoolbox\Collection;
use Schoolbox\Learning\Assessment\Feedback\DataSource as FeedbackDataSource;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\Assessment\IResponse;
use Schoolbox\Learning\Assessment\Participant;
use Schoolbox\Learning\Assessment\Project;
use Schoolbox\Learning\Assessment\Response\DataSource as ResponseDataSource;
use Schoolbox\Learning\Course\DataModel\Specification\Unit\Model as UnitSpecification;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Permission\Access\UserAccessMapper;
use Schoolbox\User;
use Schoolbox\UserMapper;

/**
 * The single source of truth for assessment participants.
 */
class DataSource
{
    /**
     * Data source for class lists
     *
     * @var External_ClassInterface
     */
    protected $externalDb;

    /**
     * Data source for group lists
     *
     * @var UserAccessMapper
     */
    protected $userAccessMapper;

    /**
     * Data source for users themselves
     *
     * @var UserMapper
     */
    protected $userMapper;

    /**
     * Data source for assessment responses
     *
     * @var ResponseDataSource
     */
    protected $responseDataSource;

    /**
     * Data source for assessment feedback
     *
     * @var FeedbackDataSource
     */
    protected $feedbackDataSource;

    /**
     * A cached list of users; the key is the folder's ID.
     *
     * @var User[]
     */
    protected $cacheForFolder;

    /**
     * Constructor
     *
     * @param External_ClassInterface $externalDb
     * @param UserAccessMapper        $userAccessMapper
     * @param UserMapper              $userMapper
     * @param ResponseDataSource      $responseDataSource
     * @param FeedbackDataSource      $feedbackDataSource
     */
    public function __construct(
        External_ClassInterface $externalDb,
        UserAccessMapper $userAccessMapper,
        UserMapper $userMapper,
        ResponseDataSource $responseDataSource,
        FeedbackDataSource $feedbackDataSource
    ) {
        $this->externalDb               = $externalDb;
        $this->userAccessMapper         = $userAccessMapper;
        $this->userMapper               = $userMapper;
        $this->responseDataSource       = $responseDataSource;
        $this->feedbackDataSource       = $feedbackDataSource;
        $this->cacheForFolder           = new Collection();
    }


    /**
     * Decorator
     *
     * Recursive function for loading participation
     *
     * This includes records for responses and feedbacks for every participant in $assessment
     *
     * @param IAssessable $assessment
     * @param User        $user
     * @param bool        $includeResponses
     * @param bool        $includeFeedbacks
     */
    public function loadParticipationIntoAssessment(IAssessable $assessment, User $user = null, $includeResponses = true, $includeFeedbacks = true)
    {
        $assessment->setAllParticipants($this->findForAssessment($assessment, $user, $includeResponses, $includeFeedbacks));

        if ($assessment instanceof Project) {
            foreach ($assessment->getAssessables() as $currentAssessment) {
                // recurse
                $this->loadParticipationIntoAssessment($currentAssessment, $user, $includeResponses, $includeFeedbacks);
            }
        }
    }

    /**
     * @param Collection|IAssessable[] $assessments
     * @param User                     $user
     * @param bool                     $includeResponses
     * @param bool                     $includeFeedbacks
     */
    public function loadParticipationIntoAssessmentCollection(
        Collection $assessments,
        User $user = null,
        $includeResponses = true,
        $includeFeedbacks = true
    ) {
        $participantStack = $this->findForAssessmentCollection(
            $assessments,
            $user,
            $includeResponses,
            $includeFeedbacks
        );
        foreach ($assessments as $assessment) {
            if (isset($participantStack[$assessment->getId()])) {
                $assessment->setAllParticipants($participantStack[$assessment->getId()]);
            }
        }
    }

    /**
     * Find all Participants for a given assessment.
     *
     * This is a union of:
     * * any students in the context's folder's class
     * * any read-level members of the context's folder's group
     * * anyone who has submitted a Response for this assessment
     * * anyone who has been given Feedback for this assessment
     *
     * Currently, this method sets the participants into $assessment as well as
     * returning them.
     *
     * @param IAssessable $assessment
     * @param User $user
     * @param bool $includeResponses
     * @param bool $includeFeedbacks
     * @return Collection|\Schoolbox\Learning\Assessment\Participant[]
     */
    protected function findForAssessment(IAssessable $assessment, User $user = null, $includeResponses = true, $includeFeedbacks = true)
    {
        // Build a participant list
        $participants = new Collection();
        foreach ($this->findAllStudentsForAssessment($assessment) as $currentUser) {
            $participants->add(
                new Participant($currentUser, $assessment)
            );
        }

        // Need to add them into the assessment
        $assessment->setAllParticipants($participants);

        /* Add responses
         *
         * If if you've specified a particular user to accumulate information about,
         * ensure that they are the submitter of the Response before adding it
         *
         * Otherwise just add all of them
         *
         * TODO findForAssessment() should filter for $user at the query level instead of traversing around them
         */
        if ($includeResponses) {
            foreach ($this->responseDataSource->findForAssessment($assessment) as $response) {
                if ($user === null || ($user instanceof User && $response->isAuthoredBy($user))) {
                    $responseUser = $response->getSubmittedOnBehalfOf();

                    // Find participant, creating if required
                    try {
                        $responseParticipant = $assessment->getParticipant($responseUser);

                    } catch (\UnexpectedValueException $e) {
                        // Not a participant: add them
                        $responseParticipant = new Participant($responseUser, $assessment);
                        $assessment->getAllParticipants()->add($responseParticipant);
                    }

                    $responseParticipant->getAllResponses()->add($response);
                }
            }
        }

        /* Add feedback
         *
         * If if you've specified a particular user to accumulate information about,
         * ensure that they are the reviewee of the Feedback before adding it
         *
         * Otherwise just add all of them
         *
         * TODO findForAssessment() should filter for $user at the query level instead of traversing around them
         */
        if ($includeFeedbacks) {
            foreach ($this->feedbackDataSource->findForAssessment($assessment) as $feedback) {
                if ($user === null || ($user instanceof User && $feedback->isReviewedFor($user))) {
                    $feedbackUser = $feedback->getReviewee();

                    // Find participant, creating if required
                    try {
                        $responseParticipant = $assessment->getParticipant($feedbackUser);

                    } catch (\UnexpectedValueException $e) {
                        // Not a participant: add them
                        $responseParticipant = new Participant($feedbackUser, $assessment);
                        $assessment->getAllParticipants()->add($responseParticipant);
                    }

                    // Feedbacks may have a stub response.
                    // If so, use it to find the actual response, and associate them together.
                    $stubResponse = $feedback->getResponse();
                    if ($stubResponse instanceof IResponse && $stubResponse->getId()) {
                        $stubResponseId = $stubResponse->getId();

                        foreach ($responseParticipant->getAllResponses() as $response) {
                            // For now assume that the types of response and feedback match
                            if ($stubResponseId == $response->getId()) {
                                $response->getAllFeedback()->add($feedback);
                                $feedback->setResponse($response);
                                break;
                            }
                        }
                    }

                    $responseParticipant->getAllFeedbacks()->add($feedback);
                }
            }
        }

        // Sort by name after all that
        $participants = $assessment->getAllParticipants()->sortByCallback([$this, 'sortCallback']);
        $assessment->setAllParticipants($participants);

        return $participants;
    }

    /**
     * Sort callback: sort by surname, then first name, then ID.
     *
     * @param Participant $a
     * @param Participant $b
     * @return int
     */
    public function sortCallback(Participant $a, Participant $b)
    {
        $lastCmp = strcasecmp($a->getUser()->getLastname(), $b->getUser()->getLastname());
        if ($lastCmp != 0) {
            return $lastCmp;
        }
        $firstCmp = strcasecmp($a->getUser()->getFirstname(), $b->getUser()->getFirstname());
        if ($firstCmp != 0) {
            return $firstCmp;
        }
        // ID comparison:
        // if a.user.id < b.user.id, then a < b
        // if a.user.id > b.user.id, then a > b
        return ($a->getUser()->getId() - $b->getUser()->getId());
    }

    /**
     * Finds all students who are participating in this assessment.
     *
     * @param IAssessable $assessment
     * @return Collection|User[]
     */
    protected function findAllStudentsForAssessment(IAssessable $assessment)
    {
        $students = new Collection();

        $context = $assessment->getContext();
        if ($context instanceof UnitSpecification) {
            // Unit specifications have no participants
            return $students;
        }

        $folder = $context->getFolder();

        // Use cached list of users for a folder
        if (!isset($this->cacheForFolder[$folder->getId()])) {
            // Cache isn't set - populate

            // Class members: convert to list of users
            $classCodes = $this->externalDb->getStudents($folder->getCodeString());
            if (!empty($classCodes)) {
                $students = $students->merge($this->userMapper->findByColumn('synergy_id', array_column($classCodes, 'synergy_id')));
            }

            // I want to search for ALL read-level users, not just students
            // Deliberately don't search external DB, I have already done that
            $students = $students->mergeUnique(
                $this->userAccessMapper->findUsersInCategory($folder, null, UserAccess::ACCESS_READ, false, true),
                'id'
            );
            $this->cacheForFolder[$folder->getId()] = $students;
        }
        return $this->cacheForFolder[$folder->getId()];
    }

}
