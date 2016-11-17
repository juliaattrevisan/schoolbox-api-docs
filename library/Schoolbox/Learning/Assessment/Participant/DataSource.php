<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Learning\Assessment\Participant;

use External_ClassInterface;
use Schoolbox\Collection;
use Schoolbox\Learning\Assessment\DataModel\MapperProxy as AssessmentDataSource;
use Schoolbox\Learning\Assessment\Feedback\DataSource as FeedbackDataSource;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\Assessment\IResponse;
use Schoolbox\Learning\Assessment\Participant;
use Schoolbox\Learning\Assessment\Project;
use Schoolbox\Learning\Assessment\Response\DataSource as ResponseDataSource;
use Schoolbox\Learning\Course\Unit\Specification as UnitSpecification;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Permission\Access\UserAccessMapper;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
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
     * @param User $user
     */
    public function loadParticipationIntoAssessment(IAssessable $assessment, User $user = null)
    {
        $assessment->setAllParticipants($this->findForAssessment($assessment, $user));

        if ($assessment instanceof Project) {
            foreach ($assessment->getAssessables() as $currentAssessment) {
                // recurse
                $this->loadParticipationIntoAssessment($currentAssessment);
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
     * @return Collection|Participant[]
     */
    public function findForAssessment(IAssessable $assessment, User $user = null)
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
        foreach ($this->responseDataSource->findForAssessment($assessment) as $response) {
            if ($user === null || ($user instanceof User) && ($response->hasAuthor($user))) {

                // Currently submitted by and on behalf of are always the same.
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

        /* Add feedback
         *
         * If if you've specified a particular user to accumulate information about,
         * ensure that they are the reviewee of the Feedback before adding it
         *
         * Otherwise just add all of them
         *
         * TODO findForAssessment() should filter for $user at the query level instead of traversing around them
         */
        foreach ($this->feedbackDataSource->findForAssessment($assessment) as $feedback) {
            if ($user === null || ($user instanceof User) && ($feedback->hasReviewee($user))) {
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

        // Sort by name after all that
        $participants = $assessment->getAllParticipants()->sortByCallback([$this, 'sortCallback']);
        $assessment->setAllParticipants($participants);

        return $participants;
    }

    /**
     * @param IAssessable $assessment
     * @param User $user
     * @param bool $returnResponses
     * @param bool $returnFeedbacks
     * @return Participant
     */
    public function findForAssessmentAndUser(IAssessable $assessment, User $user, $returnResponses = true, $returnFeedbacks = true)
    {
        /* Only add a Participant if they don't already exist
         * Don't secretly mutate the contents of allParticipants!
         */
        if ($assessment->isParticipant($user)) {
            $participant = $assessment->getParticipant($user);
        }
        else {
            $participant = new Participant($user, $assessment);

            // Need to add them into the assessment
            $assessment->getAllParticipants()->add($participant);
        }

        // Add responses
        if ($returnResponses) {
            foreach ($this->responseDataSource->findForAssessment($assessment) as $response) {
                if ($response->getSubmittedOnBehalfOf()->getId() == $user->getId()) {
                    $participant->getAllResponses()->setOffset($response->getId(), $response);

                    /* Also add it to the assessment instance
                     *
                     * This allows us to build an entire history of Responses and Feedbacks
                     * for everyone, or just for a particular person
                     */
                    $assessment->getResponses()->setOffset($response->getId(), $response);
                }
            }
        }

        // Add feedback
        if ($returnFeedbacks) {
            foreach ($this->feedbackDataSource->findForAssessment($assessment) as $feedback) {
                if ($feedback->getReviewee()->getId() == $user->getId()) {
                    $feedback->setAssessable($assessment);

                    // index the collection according to the ID of the feedback you retrieved
                    $participant->getAllFeedbacks()->setOffset($feedback->getId(), $feedback);

                    // Feedbacks may have a stub response.
                    // If so, use it to find the actual response, and associate them together.
                    $stubResponse = $feedback->getResponse();

                    if ($stubResponse instanceof IResponse && $stubResponse->getId()) {
                        $stubResponseId = $stubResponse->getId();

                        foreach ($participant->getAllResponses() as $response) {
                            // For now assume that the types of response and feedback match
                            if ($stubResponseId == $response->getId()) {
                                $response->getAllFeedback()->setOffset($feedback->getId(), $feedback);
                                $feedback->setResponse($response);
                                break;
                            }
                        }
                    }

                    /* Also add it to the assessment instance
                     *
                     * This allows us to build an entire history of Responses and Feedbacks
                     * for everyone, or just for a particular person
                     */
                    $assessment->getFeedbacks()->setOffset($feedback->getId(), $feedback);
                }
            }
        }

        return $participant;
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
    public function findAllStudentsForAssessment(IAssessable $assessment)
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
                $students->merge($this->userMapper->findByColumn('synergy_id', array_column($classCodes, 'synergy_id')));
            }

            // I want to search for ALL read-level users, not just students
            // Deliberately don't search external DB, I have already done that
            $students->mergeUnique(
                $this->userAccessMapper->findUsersInCategory($folder, null, UserAccess::ACCESS_READ, false, true),
                'id'
            );
            $this->cacheForFolder[$folder->getId()] = $students;
        }
        return $this->cacheForFolder[$folder->getId()];
    }

//    /**
//     * Finds all assessments which this student is participating within.
//     *
//     * @param User $student
//     * @return Collection|IAssessable[]
//     */
//    public function findAllAssessmentsForStudent(User $student)
//    {
//        $assessments = new Collection();
//
//        // Find all classes by class code (students only)
//        $folderIds = [];
//        // TODO inject this
//        $folderMapper = FolderMapper::getInstance();
//        if ($student->getRole()->isStudent()) {
//            // TODO inject this
//            $externalUser = new \External_User();
//            foreach ($externalUser->getSubjectList($student) as $subject) {
//                try {
//                    $folderIds[] = $folderMapper->findByCode($subject['code'], $subject['campusCode'])->getId();
//                } catch (MapperException $e) {
//                    // This folder does not exist. That's okay.
//                    continue;
//                }
//            }
//        }
//
//        // Find all of this user's group memberships
//        return array_merge(
//            $folderIds,
//                $this->userAccessMapper->findByColumnList([
//                'uid'     => $student->getId(),
//                'type'    => UserAccess::ACCESS_READ,
//                'service' => UserAccess::SERVICE_HOMEPAGE,
//            ])->getColumnValues('serviceIdentifier')
//        );
//
//        // Find every assessment the student has created a response to
//
//        // Find every assessment the student has had a feedback created for
//
//        // TODO inject this
//        $assessmentDataSource = new AssessmentDataSource();
//
//        return $assessmentDataSource->findAllForFolderId($folderIds);
//    }
}
