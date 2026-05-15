<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2026, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Reporting\Feedback;

use Alaress\Web\Controller\Base;
use Alaress_Date_Format;
use Aura\Router\Exception\RouteNotFound;
use Schoolbox\Application\Exception\NotFound;
use Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\GenericCollection;
use Schoolbox\Learning\Assessment\Breadcrumb\Factory as BreadcrumbFactory;
use Schoolbox\Learning\Assessment\DataModel\MapperProxy as AssessmentDataSource;
use Schoolbox\Learning\Assessment\Feedback\IFeedbackDataSource;
use Schoolbox\Learning\Assessment\LockedChecker;
use Schoolbox\Learning\Assessment\Participant;
use Schoolbox\Learning\Assessment\Project;
use Schoolbox\Learning\Assessment\Reporting;
use Schoolbox\Learning\Assessment\Reporting\Model;
use Schoolbox\Learning\DueWork\StateMapper;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Permission\Access\Access;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\User;
use Twig\Error\Error;

class Controller extends Base
{
    /**
     * Controller constructor.
     */
    public function __construct(
        private ErrorMapper $errorMapper,
        private BreadcrumbFactory $bakery,
        private \Schoolbox\Learning\Assessment\DataModel\Reporting\Mapper $reportingMapper,
        private \Schoolbox\Assessment\Term\Mapper $termMapper,
        private StateMapper $stateMapper,
        private Participant\DataSource $participantDataSource,
        private IFeedbackDataSource $feedbackDataSource,
        private \Schoolbox\Learning\Assessment\DataModel\Reporting\Feedback\Mapper $feedbackDataMapper,
        private AssessmentDataSource $assessmentDataSource,
        private Project\ResultCache\Mapper $projectResultCacheMapper,
        private Access $access,
    ) {
        /* TODO Technical Uplift Candidate
         *  StateMapper.
         *  Submission States are currently in the Due Work folder, but should be promoted into a more global space.
         *  They are used among multiple learning activities: due work, quiz, reporting (at time of writing) as are the
         *  Mark Types stuff.
         */
    }


    /**
     * A redirect to the first participant in the set.
     * It doesn't care whether they already have a feedback or not.
     *
     * @param Reporting\Model $assessment
     * @throws RouteNotFound
     * @throws \Exception
     */
    public function getFirstParticipantFeedback(Reporting\Model $assessment): void
    {
        $participants = $assessment->getAllParticipants()->filter(fn($participant) => !$participant->isExcluded());
        $firstParticipant = $participants->first()?->getUser();

        if ($firstParticipant instanceof User) {
            $this->response = $this->redirectFound(
                $this->routeGenerator->generate('learningReportingFeedback.getParticularFeedback', [
                    'assessmentId' => $assessment->getId(),
                    'assessment' => $assessment,
                    'learnerId' => $firstParticipant->getId(),
                    'learner' => $firstParticipant,
                ])
            );

            return;
        }

        /* If the participant list is empty, nothing can be shown
         */
        throw $this->errorMapper->generateException(ErrorMapper::ERROR_PAGE_NOT_FOUND);
    }


    /**
     * The screen that lets you provide feedback to a particular $learner
     *
     * @param Model $assessment
     * @param User $learner
     * @param Reporting\Feedback\Model|null $feedback
     * @throws Error
     * @throws RouteNotFound
     */
    public function getParticularFeedback(
        Reporting\Model $assessment,
        \Schoolbox\User $learner,
        ?Reporting\Feedback\Model $feedback = null,
    ): void {
        /**
         * Generate the participant carousel, only if you have access to the status page
         */
        $statusRoute = $this->getRouteIfAuthorized('learningAssessment.getStatus', [
            'assessment' => $assessment,
            'assessmentId' => $assessment->getId(),
        ]);

        /* TODO Technical Uplift Candidate
         *  Consolidate the ParticipantCarousel into a standardised structure that we can reuse across all the activities
         */
        /* Calculate ParticipantCarousel people: note that this reuses the $participant var, so be careful about that.
         */
        $participantCarousel = [];

        if ($statusRoute) {
            /** since this route is loaded for a specific participant, only that participant is hydrated by the module. we need the rest */
            $assessment = $this->participantDataSource->loadParticipationIntoAssessment($assessment);
            foreach ($assessment->getAllParticipants() as $participant) {
                /* Are they excluded? ignore them */
                if ($participant->isExcluded()) {
                    continue;
                }

                $authoritativeFeedback = null;

                if ($participant->getAllFeedbacks()->count()) {
                    /** @var \Schoolbox\Learning\Assessment\Reporting\Feedback\Model|null $authoritativeFeedback */
                    $authoritativeFeedback = $assessment->getAuthoritativeFeedback($participant, false);
                }

                /* These now require the Status enum for the state of a feedback. In dialog agents this represented the
                 * state of a response, but we have no such scenario in reporting activities. The closest synonym seems
                 * to be the existence (and state) of a feedback
                 */
                $status = Reporting\Feedback\Status::INCOMPLETE;

                if ($authoritativeFeedback) {
                    if ($authoritativeFeedback->isDraft()) {
                        $status = Reporting\Feedback\Status::DRAFT;
                    } elseif ($authoritativeFeedback->isPublished()) {
                        $status = Reporting\Feedback\Status::PUBLISHED;
                    }
                }

                $participantCarousel[] = [
                    'id' => $participant->getUser()->getId(),
                    'value' => $this->getRouteIfAuthorized('learningReportingFeedback.getParticularFeedback', [
                        'assessment' => $assessment,
                        'assessmentId' => $assessment->getId(),
                        'learner' => $participant->getUser(),
                        'learnerId' => $participant->getUser()->getId(),
                        'feedback' => $authoritativeFeedback,
                    ]),
                    'user' => (new User\Translator\Author\ModelToArray())->__invoke($participant->getUser()),
                    'status' => $status,
                    'statusLabel' => $status->translate(),
                ];
            }
        }

        /* After we've figured out the ParticipantCarousel we can reuse this $participant var
         */
        $participant = $assessment->getParticipant($learner);

        $this->response = $this->responseFromTemplate('@learningReportingFeedback/index.twig', [
            'assessment' => $assessment,
            'learner' => $learner,
            'feedback' => $feedback ?? $assessment->getAuthoritativeFeedback($participant, false),
            // Is the page being displayed in readonly mode?
            'interactionLocked' => (new LockedChecker($this->termMapper))->isLockedForInteractions($assessment),
            'breadcrumbs' => $this->bakery->produceFromAssessment($assessment, $this->getUser())
                ->add([ $assessment->getTitle(), $this->routeGenerator->generate('learningAssessment.getStatus', ['assessmentId' => $assessment->getId()]) ])
                ->add(['Feedback', '']),
            /* Participant Carousel; note that this is aliased to "students" in the twig fragment
             */
            'participantCarousel' => $participantCarousel,
            /* For saving feedbacks
             */
            'errors' => $this->stateMapper->listDisplayValues(),
            'participant' => $participant,
        ], 'Feedback');
    }


    /**
     * Posts feedback for a particular person. If the $feedback existed already, update that with the new POST payload
     * or spawn a whole new $feedback instance and then persist that.
     *
     * @param Model $assessment
     * @param User $learner
     * @param Reporting\Feedback\Model|null $feedback
     * @return void
     * @throws \Exception
     */
    public function postParticularFeedback(
        Reporting\Model $assessment,
        \Schoolbox\User $learner,
        ?Reporting\Feedback\Model $feedback = null,
    ): void {
        /* Check that $learner is indeed a participant of $assessment
         */
        $participant = $assessment->getParticipantIfExtant($learner);

        /* Not a participant? Or, you're excluded:
         * 404, I suppose: you came here directly, or multiplayer schoolbox happened. The participant carousel we built
         * was meant to omit excluded participants.
         */
        if (null === $participant) {
            throw $this->errorMapper->generateException(ErrorMapper::ERROR_PAGE_NOT_FOUND);
        }

        if ($participant->isExcluded()) {
            throw $this->errorMapper->generateException(ErrorMapper::ERROR_PAGE_NOT_FOUND);
        }

        /* Check that the subobject exists: annoyingly, this is required
         */
        try {
            $reporting = $this->reportingMapper->findOneByColumn('assessment_id', $assessment->getId());
        } catch (\Schoolbox\Mapper\Exception $e) {
            /* For some reason this couldn't be found: maybe multiplayer schoolbox where someone deleted it while you
             * were actively using it
             */
            throw $this->errorMapper->generateException(ErrorMapper::ERROR_PAGE_NOT_FOUND);
        }

        /* Check if $feedback is null; if so, we're spawning a whole new one for this person,
         * or we can resume an existing draft
         */
        if (null === $feedback) {
            $authoritativeFeedback = $participant->getAuthoritativeFeedback(false);

            /* Resume an existing draft feedback if it exists, otherwise spawn a new feedback instance.
             *
             * If we didn't find an authoritative feedback spawn a whole new one; this person never had feedback.
             * Or, if we found an authoritative feedback but it was published, spawn a whole new one; this creates a new
             * version of the feedback and adds it to the stack like every other learning activity's behaviour. Without
             * this check you'd end up recycling exactly one feedback every time.
             */
            if (null !== $authoritativeFeedback && $authoritativeFeedback->isDraft()) {
                /* Resume an existing draft feedback
                 */
                $feedback = $authoritativeFeedback;
            } else {
                /* All other scenarios spawn a new feedback instance
                 */
                $feedback = new Reporting\Feedback\Model();
            }
        }

        /* Update props and persist it
         */
        $post = $this->requestPost->getArrayCopy();

        /** @var \Schoolbox\Learning\Assessment\Reporting\Feedback\Model $feedback */
        $feedback
            ->setAssessable($assessment)
            ->setReporting($reporting)
            ->setReviewee($learner)
            ->setComment(null)
            ->setReviewer($this->getUser());

        /* Feedback Comments are optional based on the Reporting toggle
         */
        if ($assessment->getFeedbackCommentsEnable()) {
            $feedback->setComment($post['comment'] ?? '');
        }

        /** Get mark schemes
         * - Instructor mark scheme is used to normalise what was inputted by the user
         * - Learner mark scheme is used when displaying the mark back to learners/parents
         */
        $instructorScheme = $assessment->getInstructorMarkScheme();
        $learnerScheme = $assessment->getMarkScheme();


        /* This human readable mark is given by the instructor, from which we derive the normalised mark
        */
        $mark = trim((string)$post['mark']);

        /* Default values
         * Note that $mark might be empty string at this point:
         * this occurs when you add a comment before the grade, in the Quiz Quickmark screen (and for reporting, likely at least the markbook)
         */
        $normalisedMark = null;
        $learnerHumanReadableMark = null;
        $instructorHumanReadableMark = null;

        /* Apparently this is how you detect a Submission State: an exception is thrown
         * trying to interpret it as one of the Mark Schemes. No wonder it was classified
         * as the $errors var. Wild!
         *
         * Shanked from Learning/Assessment/DueWork/Feedback/Factory.php::210
         */
        try {
            /* Determine the normalised mark based on the expected mark type being used for input.
             * Reporting assumes that the mark is always entered against the instructor scheme,
             * as there's no self-assessment like due work.
             */
            $normalisedMark = $instructorScheme->getNormalisedMark($mark);

            /* Always rederive/sanitise both the learner and instructor human readable marks, even if they're the same.
             * For example, this ensures a value of "5" using a percentage mark type becomes "5 %"
             */
            $learnerHumanReadableMark = $learnerScheme->getHumanReadableMark($normalisedMark);
            $instructorHumanReadableMark = $instructorScheme->getHumanReadableMark($normalisedMark);
        } catch (\InvalidArgumentException $e) {
            if ('' !== $mark) {
                /* If it's a Submission State, save the text as the human readable mark
                 * and derive its normalised mark from the uh Lists in admin
                 */
                $code = $this->stateMapper->findOneByColumn('display_as', $mark);

                $normalisedMark = $code->getNormaliseAs();

                /* There isn't a learner-specific Submission State: it's the same for both parties.
                 */
                $learnerHumanReadableMark = $mark;
                $instructorHumanReadableMark = $mark;
            }
        }

        $feedback
            ->setNormalisedMark($normalisedMark)
            ->setHumanReadableMark($learnerHumanReadableMark)
            ->setHumanReadableInstructorMark($instructorHumanReadableMark);

        /* This publish=1 param tuple is sent only when Publish And Continue is clicked
         */
        if (1 === (int)($post['publish'] ?? null)) {
            $feedback
                ->setPublishedBy($this->getUser()->getId())
                ->setPublishedAt(\Alaress_Date_Format::now());
        }

        $this->feedbackDataSource->save($feedback);

        /**
         * We need to retrieve the new plan below, but it won't have been hydrated into $participant yet
         * Force re-hydration so that we can get the actual plan
         */
        $this->participantDataSource->loadParticipationIntoAssessment(
            $assessment,
            $learner
        );

        $plan = $participant->getPlan();

        $data = [
            'success' => true,
            'message' => 'OK',
            'id'      => $feedback->getId(),
            /* these extra fields are currently used by the Markbook to keep things responsive */
            /* Presumably that ^^^ means these fields are used to dynamically update the ui since posting feedback is an
             * asynchronous request in most usages.
             */
            'normalisedMark'              => $feedback->getNormalisedMark(),
            'humanReadableMark'           => $feedback->getHumanReadableMark(),
            'humanReadableInstructorMark' => $feedback->getHumanReadableInstructorMark(),
            'plan' => [
                'isDynamic' => $plan->getIsDynamic(),
                'name' => $plan->getName(),
                'dueDate' => $plan->getDueDate()?->getShort() ?? null,
            ],
        ];

        $this->response = $this->responseAsJson($data);
    }


    /**
     * Submit multiple feedbacks in single call
     *
     * This is a dueWork/reporting-only route
     *
     * @param Reporting\Model $assessment
     * @throws MapperException
     * @throws MapperException\Deleted
     */
    public function postParticipantFeedbacks(Reporting\Model $assessment)
    {
        /** @var int[] $draftFeedbackIds */
        $draftFeedbackIds = $this->requestPost->get('drafts', []);
        $now              = Alaress_Date_Format::now();
        $results = [];
        $projectCache = (new Project\ResultCache\ResultCache($this->projectResultCacheMapper));

        /* if this is a spec assessment, we want all implementation children assessments,
         * with hydrated participants so we can publish all the users quickly and easily.
         *
         * we should be using filters, so we aren't hydrating all participation for all current assessments
         * but we can hydrate only students for assessments that are still active, and we have write access to.
        */
        $aImpls = new GenericCollection(Reporting\Model::class);
        if ($assessment->isSpecification()) {
            $lockChecker = (new LockedChecker($this->termMapper));
            $user = $this->getUser();
            $access = $this->access;

            // only fetch implementation assessments that are 'not locked' (i.e. not in archived terms)
            $aImpls = $this->assessmentDataSource
                ->findAllImplementationsBySpecificationId($assessment->getId())
                ->filter(
                    function (Reporting\Model $aImpl) use ($lockChecker, $access, $user) {
                        if ($lockChecker->isLockedForInteractions($aImpl)) {
                            // exit early if the assessment is locked
                            return false;
                        }

                        // ensure the current user has write+ access to the homepage (See Rule\HasHomepageAccess)
                        try {
                            $permission = $access->getFolderAccess($user, $aImpl->getBaseContext()->getFolder()->getId());
                        } catch (MapperException $e) {
                            // exit early
                            return false;
                        }
                        return $permission >= UserAccess::ACCESS_WRITE;
                    }
                )
                ->indexBy('id');

            // hydrate participants
            $this->assessmentDataSource->loadParticipantsIntoAssessmentCollection($aImpls, null, true);
        } else {
            $aImpls->add($assessment)->indexBy('id');
        }

        foreach ($draftFeedbackIds as $draftFeedbackId) {
            // Verify that this feedback is for this assessment / find the relevant implementation for this feedback
            $feedback = $this->feedbackDataMapper->findById($draftFeedbackId);
            $publishSuccess   = false;

            $aImpl = $aImpls->getBy('id', $feedback->getReporting()->getAssessmentModel()->getId());
            if (!$aImpl) {
                $results[$draftFeedbackId] = ['publishSuccess' => false, 'error' => 'Assessment not found'];
                continue;
            }

            $feedback->setPublishedAt($now);

            try {
                $this->feedbackDataMapper->save($feedback);
                $publishSuccess = true;

                /* Update project result cache when Publish All is used
                 */
                $projectCache->refresh($this->assessmentDataSource, $aImpl, $feedback->getLearner());
            } catch (\PDOException | \DomainException | \InvalidArgumentException | \LogicException | \UnexpectedValueException $e) {
                // TODO: ^ do we reallllly need to catch all of those? surely we want to know about some of this stuff
                $publishSuccess = false;
            }

            $results[$draftFeedbackId] = ['publishSuccess'   => $publishSuccess];
        }

        $this->response = $this->responseAsJson($results);
    }


    /**
     * Publish last draft feedback for a participant.
     *
     * This is a dueWork/reporting-only route. It is not applicable to Project, Quiz, Task, or Dialog Agent.
     *
     * @param Reporting\Model $assessment
     * @param User $learner
     */
    public function postPublishFeedback(Reporting\Model $assessment, User $learner)
    {
        $participant = $assessment->getParticipant($learner);
        $feedback = $participant->findMostRecentInstructorGivenFeedback();

        if (null === $feedback) {
            throw new NotFound("No Feedback for assessment.");
        }

        if (!$feedback->isPublished()) {
            // publish this
            $feedback->setPublishedAt(Alaress_Date_Format::now());
        }

        $this->feedbackDataSource->save($feedback);

        // Update project mark as the published feedback may affect the project cache
        (new Project\ResultCache\ResultCache($this->projectResultCacheMapper))
            ->refresh($this->assessmentDataSource, $assessment, $learner);

        $this->response = $this->responseAsJson([
            'success' => true,
            'message' => 'OK',
            'id'      => $feedback->getId(),
        ]);
    }
}
