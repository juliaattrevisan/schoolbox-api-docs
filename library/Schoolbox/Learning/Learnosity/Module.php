<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Learnosity;

use Alaress\Authorizer\Rule;
use Alaress\Module\Module as BaseModule;
use Alaress\Twig\Loader\IChainLoader;
use Alaress\Web\Controller\Base;
use DomainException;
use InvalidArgumentException;
use LogicException;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Schoolbox\Application\Exception\Exception;
use Schoolbox\Application\Exception\NotFound;
use Schoolbox\Assessment\Term\Mapper as TermMapper;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\Learning\Assessment\DataModel\MapperProxy as AssessmentMapperProxy;
use Schoolbox\Learning\Assessment\LockedChecker;
use Schoolbox\Learning\Assessment\Quiz\Model as QuizDomainObject;
use Schoolbox\Learning\Assessment\Visibility;
use Schoolbox\Learning\Authorizer\Rule\CanRespondToAssessment;
use Schoolbox\Learning\Authorizer\Rule\CanViewAssessable;
use Schoolbox\Learning\Authorizer\Rule\IsAssessmentOpen;
use Schoolbox\Learning\Authorizer\Rule\IsImplementation;
use Schoolbox\Learning\Authorizer\Rule\IsInstructor;
use Schoolbox\Learning\Authorizer\Rule\IsInstructorOrHasHomepageAccess;
use Schoolbox\Learning\Authorizer\Rule\IsInteractionUnLocked;
use Schoolbox\Learning\DueWork\StateMapper;
use Schoolbox\Mapper;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Mapper\DefinitionException;
use Schoolbox\Permission\Access\Access;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Resource\Folder\Model as FolderModel;
use Schoolbox\User;
use Schoolbox\UserMapper;
use UnexpectedValueException;

class Module extends BaseModule
{
    /**
     * @inheritdoc
     */
    protected $basename = 'learnosity';


    /**
     * Hook method for setting up routes via self::register()
     *
     * @return void
     * @throws InvalidArgumentException
     */
    protected function registerRoutes(): void
    {
        /* Instructors only
         */
        $instructorPerms = $this->buildAuthRuleHasInstructorOrHomepageAccess();

        /* Someone who can complete an attempt
         *
         * The current user must match {studentId} in the routes
         * ie. you're the owner of something in the url, among other rules
         */
        $learnerCanAttempt = $this->buildAuthRuleCanAttempt();

        /* Someone who is completing an attempt
         *
         * This turns out to be a special subset of $learnerCanAttempt which is identical
         * except we remove the check for Attempt counts; it's not needed on the outro screen.
         * It's also not needed when you're trying to review it.
         */
        $learnerCanComplete = $this->buildAuthRuleComplete();

        /* Someone trying to access an attempt to see what a student did
         * This could be a parent, or the student themselves.
         */
        $learnerCanReview = $this->buildAuthRuleReview();

        /* A special rule about who can finish an attempt
         * (Staff can arbitrarily finish a student's unfinished attempt)
         */
        $canFinishAttempt = $this->canFinishAttempt();

        $preloadAssessment = function ($params) {
            return $this->preloadAssessment($params);
        };

        $preloadParticipant = function ($params) {
            return $this->preloadAssessmentAndStudent($params);
        };

        $preloadAttempt = function ($params) {
            return $this->preloadAttempt($params);
        };

        $preloadAttemptForReview = function ($params) {
            return $this->preloadAttemptForReview($params);
        };

        /* Modify Learnosity contents for a given schoolbox quiz id
         */
        $this->register('GET', '/{assessmentId}/content', 'getContent', $instructorPerms, $preloadAssessment);

        /* Async complete an attempt (set finished datetime)
         */
        $this->register('POST', '/{assessmentId}/attempt/{studentId}/complete', 'postCompleteAttempt', $canFinishAttempt, $preloadParticipant);

        /* Monitor an attempt; learnosity has this strange issue where it drops attempts
         * we we use this to try and log what might have gone wrong
         */
        $this->register('POST', '/{assessmentId}/attempt/{studentId}/{learnositySessionReference}/monitor', 'postAttemptMonitor', $learnerCanComplete, $preloadAttempt);

        /* Conclude an attempt
         */
        $this->register('GET', '/{assessmentId}/attempt/{studentId}/{learnositySessionReference}', 'getCompleteAttempt', $learnerCanComplete, $preloadAttempt);

        /* Start a new attempt
         */
        $this->register('GET', '/{assessmentId}/attempt/{studentId}', 'getAttempt', $learnerCanAttempt, $preloadParticipant);

        /* Delete an attempt
         */
        $this->register('POST', '/{assessmentId}/attempt/delete', 'postDeleteAttempts', $instructorPerms, $preloadAssessment);

        /* Delete unreviewed attempts
         */
        $this->register('POST', '/{assessmentId}/attempt/delete-unreviewed', 'postDeleteUnreviewedAttempts', $instructorPerms, $preloadAssessment);

        /* Mass-review multiple attempts
         */
        $this->register('POST', '/{assessmentId}/attempt/mass-review', 'postMassReviewAttempts', $instructorPerms, $preloadAssessment);

        /* Generic report screen you see after completing an attempt
         * This is a Learnosity redirect upon submit
         *
         * People use this url to see what a student did in their attempt.
         * Applies to student/parent/staff
         */
        $this->register('GET', '/{assessmentId}/review/{studentId}/{learnositySessionReference}', 'getReview', $learnerCanReview, $preloadAttempt);

        /* Provide feedback to a student's attempt
         */
        $this->register('GET', '/{assessmentId}/feedback/{studentId}/{learnositySessionReference}', 'getFeedback', $instructorPerms, $preloadAttemptForReview);

        /* Start horizontal marking using a Learnosity Report
         */
        $this->register('GET', '/{assessmentId}/horizontal', 'getHorizontalMarking', $instructorPerms, $preloadAssessment);

        /* Finalise a set of attempts via their learnosity_references
         */
        $this->register('POST', '/{assessmentId}/horizontal', 'postHorizontalMarking', $instructorPerms, $preloadAssessment);

        /* Each part of the process where teacher provides feedback now has granular endpoints
         * There's a unique endpoint for:
         *     - updating the score for a particular Question
         *     - updating the comment for a particular Item
         *     - updating the overall comment for a particular Attempt
         *     - updating the overall score for a particular Attempt
         *
         * The conversion to vue for horizontal marking meant all these vue components became
         * really granular and ended up having their own behaviours. A full synchronous endpoint
         * is no longer required, which is what postFeedback() used to be.
         */

        /* Provide updated score for a particular student response to a particular question
         * (used predominantly in horizontal marking)
         */
        $this->register('POST', '/{assessmentId}/feedback/{studentId}/question/{learnositySessionReference}/score', 'postFeedbackScore', $instructorPerms, $preloadAttemptForReview);

        /* Provide updated comment for a particular student response to a particular question
         * (used predominantly in horizontal marking)
         */
        $this->register('POST', '/{assessmentId}/feedback/{studentId}/item/{learnositySessionReference}/comment', 'postFeedbackComment', $instructorPerms, $preloadAttemptForReview);

        /* Provide updated overall attempt score for a particular student attempt
         * (used predominantly in horizontal marking)
         */
        $this->register('POST', '/{assessmentId}/feedback/{studentId}/attempt/{learnositySessionReference}/attempt-score', 'postFeedbackAttemptScore', $instructorPerms, $preloadAttemptForReview);

        /* Provide updated overall attempt comment for a particular student attempt
         * (used predominantly in horizontal marking)
         */
        $this->register('POST', '/{assessmentId}/feedback/{studentId}/attempt/{learnositySessionReference}/attempt-comment', 'postFeedbackAttemptComment', $instructorPerms, $preloadAttemptForReview);

        /* polld endpoints: these exist to combat learnosity's universally asynchronous nature.
         *
         * Sometimes learnosity misinforms you because it hasn't finished some asynchronous task.
         * You have to keep asking it if it's finished processing whatever it's doing before
         * telling users the outcome.
         *
         * They won't tell you unless you've asked. And you have to ask a lot.
         */

        // Async callback for copying contents from one quiz to another
        $this->register(
            self::METHOD_POST,
            '/{sourceId}/copy/{destinationId}',
            'postCopyQuizConstituents',
            $this->buildAuthRuleCanCopy(),
            fn ($params) => $this->preloadAssessmentAndDestinationAssessment($params),
        );

        // Async callback for polling quiz import's status
        $this->register(
            self::METHOD_POST,
            '/{assessmentId}/poll',
            'postPollQuizStatus',
            $instructorPerms,
            $preloadAssessment,
        );

        // Async callback for polling a quiz attempt's status
        $this->register(
            self::METHOD_POST,
            '/{assessmentId}/attempt/{studentId}/{learnositySessionReference}/poll',
            'postPollQuizAttemptStatus',
            $learnerCanComplete,
            $preloadAttempt,
        );

        /* Update a particular attempt due to rescoring being finished asynchronously via polld
         */
        $this->register('POST', '/{assessmentId}/rescore/{studentId}/{learnositySessionReference}', 'postRescoreAttemptsCallback', $instructorPerms, $preloadAttemptForReview);

        /* Request learnosity to rescore a particular quiz's attempts via polld
         */
        $this->register('POST', '/{assessmentId}/rescore', 'postRescoreAttempts', $instructorPerms, $preloadAssessment);

        /* CSV Export of students' most recent response
         */
        $this->register('GET', '/{assessmentId}/attempts/csv', 'getExportAttempts', $instructorPerms, $preloadAssessment);
        /* TODO: Miscellaneous endpoints that we can probably look at culling soon
         *  These were originally early explorations in how their api worked.
         */

        /* Older exploratory urls that aren't part of our conventional workflow
         */
        $this->register('GET', '/activityList', 'getActivityList', $instructorPerms, null);
        $this->register('GET', '/items', 'getItems', $instructorPerms, null);
        $this->register('GET', '/sessionList', 'getSessionListReport', $instructorPerms, null);

        /* Archive a set of reference identifiers in Learnosity
         */
        $this->register('GET', '/archive/{learnosityReference}', 'getArchiveReference', $instructorPerms, null);
    }


    /**
     * Prevent non-instructors from marking or modifying implementation
     * assessments, and users without homepage write access from modifying spec
     * assessments.
     *
     * @return Rule\IAuthorizationRule
     * @throws InvalidArgumentException
     */
    private function buildAuthRuleHasInstructorOrHomepageAccess(): Rule\IAuthorizationRule
    {
        return new Rule\All(
            new Rule\IsLoggedIn(),
            new Rule\Any(
                new Rule\IsSuperuser(),
                new IsInstructorOrHasHomepageAccess(
                    $this->getAccess(),
                    function (array $params) {
                        if (isset($params['assessment'])) {
                            $ctx = $params['assessment']->getContext();
                        } elseif (isset($params['participant'])) {
                            $ctx = $params['participant']->getAssessable()->getContext();
                        } elseif (isset($params['context'])) {
                            $ctx = $params['context'];
                        } else {
                            throw new NotFound('No assessment or context preloaded');
                        }
                        return $ctx;
                    },
                ),
            )
        );
    }


    /**
     * Auth rule for whether an assessment can be copied from one place to
     * another.
     *
     * The copying user must have read permission on the source, and write
     * permission on the destination.
     *
     * @return Rule\IAuthorizationRule
     * @throws InvalidArgumentException
     */
    private function buildAuthRuleCanCopy(): Rule\IAuthorizationRule
    {
        return new Rule\All(
            new Rule\IsLoggedIn(),
            new Rule\Any(
                new Rule\IsSuperuser(),
                new Rule\All(
                    new Rule\HasHomepageAccess(
                        UserAccess::ACCESS_READ,
                        static function (array $params): int {
                            if (isset($params['source'])) {
                                return $params['source']->getContext()->getFolder()->getId();
                            }
                            throw new NotFound('No source assessment preloaded');
                        },
                        $this->getAccess(),
                    ),
                    new IsInstructor(
                        $this->getAccess(),
                        static function (array $params): FolderModel {
                            if (isset($params['destination'])) {
                                return $params['destination']->getContext()->getFolder();
                            }
                            throw new NotFound('No destination assessment preloaded');
                        }
                    )
                ),
            )
        );
    }


    /**
     * Rules in order of preference:
     *     - You must be logged in
     *     - Assessment must be an implementation, not a specification
     *     - Instructors can submit if their context allows (staff must have WRITE+ perm to folder)
     *     - staff/students cannot if due in archived term (ie 'interaction locked')
     *     - students cannot if not open
     *     - Students cannot submit if reached response limit
     *     - students cannot if not participant, or a different student to that indicated in URL
     *
     * @return Rule\LazyCallback
     */
    private function buildAuthRuleCanAttempt(): Rule\LazyCallback
    {
        return new Rule\LazyCallback(function () {
            return new Rule\All(
                new Rule\IsLoggedIn(),
                /* You can create attempts for Implementations only; not Specifications
                 */
                new IsImplementation(),
                /* Assessment must be in a living (unarchived) academic period
                 */
                new IsInteractionUnLocked(new LockedChecker($this->getTermMapper())),
                /* Otherwise, you have to be an instructor
                 * or a student that adheres all the non-instructor criteria
                 */
                new Rule\Any(
                    $this->buildAuthRuleHasInstructorOrHomepageAccess(),
                    /* TODO I think there's a duplication between IsAssessmentOpen() and
                     *  canLearnerCreateResponse() in Quiz Model; the latter already
                     *  checks the former.
                     */
                    new Rule\All(
                        /* You must be the user referenced in the url
                         * You can't sobo a quiz attempt
                         */
                        new Rule\IsUser('studentId'),
                        /* Assessment must be published in the past, closed in the future,
                         * and you must be a participant (also, non-excluded)
                         */
                        new CanViewAssessable(new Visibility($this->getAccess())),
                        /* Open Date for this particular student's Participation Plan (if extant)
                         * must have been reached, and not yet be closed; each student can have their
                         * own versions of Open and Close dates.
                         */
                        new IsAssessmentOpen(),
                        /* Students have a set of criteria to fulfil when we're deciding if
                         * they can make attempts (this double-checks IsAssessmentOpen again)
                         */
                        new CanRespondToAssessment($this->getAccess()),
                    )
                )
            );
        });
    }


    /**
     * Decide if someone can complete (finish) an attempt
     *
     * Rules in order of preference:
     *     - You must be logged in
     *     - Assessment must be an implementation, not a specification
     *     - Instructors can submit if their context allows (staff must have WRITE+ perm to folder)
     *     - staff/students cannot if due in archived term (ie 'interaction locked')
     *     - students cannot if not open
     *     - students cannot if not participant, or a different student to that indicated in URL
     *
     * @return Rule\LazyCallback
     */
    private function buildAuthRuleComplete(): Rule\LazyCallback
    {
        return new Rule\LazyCallback(function () {
            return new Rule\All(
                new Rule\IsLoggedIn(),
                /* You can create attempts for Implementations only; not Specifications
                 */
                new IsImplementation(),
                /* Assessment must be in a living (unarchived) academic period
                 */
                new IsInteractionUnLocked(new LockedChecker($this->getTermMapper())),
                /* Otherwise, you have to be an instructor
                 * or a student that adheres all the non-instructor criteria
                 */
                new Rule\Any(
                    $this->buildAuthRuleHasInstructorOrHomepageAccess(),
                    /* TODO I think there's a duplication between IsAssessmentOpen() and
                     *  canLearnerCreateResponse() in Quiz Model; the latter already
                     *  checks the former.
                     */
                    new Rule\All(
                        /* You must be the user referenced in the url
                         * You can't sobo a quiz attempt
                         */
                        new Rule\IsUser('studentId'),
                        /* Assessment must be published in the past, closed in the future,
                         * and you must be a participant (also, non-excluded)
                         */
                        new CanViewAssessable(new Visibility($this->getAccess())),
                        /* Open Date for this particular student's Participation Plan (if extant)
                         * must have been reached, and not yet be closed; each student can have their
                         * own versions of Open and Close dates.
                         */
                        new IsAssessmentOpen()
                    )
                )
            );
        });
    }


    /**
     * Decide if someone can review an attempt
     *
     * This should not have any of the usual date constraints: publish open due close
     * but is subject to the academic period being archived.
     *
     * Rules in order of preference:
     *     - You must be logged in
     *     - Assessment must be an implementation, not a specification
     *     - Instructors see what they did in their own attempt
     *     - Parents of a student can see that student's attempt
     *     - The student themselves can see their own attempt
     *
     * @return Rule\LazyCallback
     */
    private function buildAuthRuleReview(): Rule\LazyCallback
    {
        return new Rule\LazyCallback(function () {
            return new Rule\All(
                new Rule\IsLoggedIn(),
                /* You can create attempts for Implementations only; not Specifications
                 */
                new IsImplementation(),
                /* Assessment must be in a living (unarchived) academic period
                 */
                new IsInteractionUnLocked(new LockedChecker($this->getTermMapper())),
                /* Obey mark visibility setting
                 */
                new Rule\Callback(function (array $params, User $currentUser): void {
                    if (!$params['assessment']->isFeedbackVisibleToUser($currentUser)) {
                        throw new \Alaress\Authorizer\Failure\Unauthorized('Feedback is not allowed by mark visibility.');
                    }
                }),
                /* Otherwise, you have to be an instructor
                 * or a student that adheres all the non-instructor criteria
                 */
                new Rule\Any(
                    /* Parents can view students' attempts if:
                     *   - They are the parent of this particular student (and thus their attempt)
                     *   - restrict_student_submissions config is disabled
                     */
                    new Rule\All(
                        new Rule\IsParentOfUser($this->getExternalUser(), $this->getMapperFactory()->get(\Schoolbox\UserMapper::class), 'studentId'),
                        /* Other parts of this auth rule block already took this into account when visiting
                         * via the quiz summary screen...but we must implement this in case parents access
                         * the url directly, instead of via the summary screen
                         */
                        new Rule\IsConfigSetTo(
                            ConfigMapper::NAME_RESTRICT_STUDENT_SUBMISSIONS,
                            false,
                            $this->getConfig(),
                        ),
                    ),
                    new Rule\All(
                        /* You must be the user referenced in the url
                         * You can't sobo a quiz attempt
                         */
                        new Rule\IsUser('studentId')
                    )
                )
            );
        });
    }


    /**
     * Students usually finish attempts themselves, but sometimes they don't;
     * Teachers have the option to arbitrarily finish it on their behalf.
     * This used to happen when people never clicked the final Submit button
     * and the same could occur for Learnosity where they skip the last modal
     *
     * @return Rule\LazyCallback
     */
    private function canFinishAttempt(): Rule\LazyCallback
    {
        return new Rule\LazyCallback(function () {
            return new Rule\All(
                new Rule\IsLoggedIn(),
                new Rule\Any(
                    $this->buildAuthRuleHasInstructorOrHomepageAccess(),
                    new Rule\All(
                        // This section is the same as in buildAuthRuleCanAttempt().
                        // Note that the user referenced in the url need not be a student.  Some quizzes are designed for teachers.

                        /* You must be the user referenced in the url
                         * You can't sobo a quiz attempt
                         */
                        new Rule\IsUser('studentId'),
                        /* Assessment must be published in the past, closed in the future,
                         * and you must be a participant (also, non-excluded)
                         */
                        new CanViewAssessable(new Visibility($this->getAccess())),
                        /* Open Date for this particular student's Participation Plan (if extant)
                         * must have been reached, and not yet be closed; each student can have their
                         * own versions of Open and Close dates.
                         */
                        new IsAssessmentOpen(),
                        /* Students have a set of criteria to fulfil when we're deciding if
                         * they can make attempts (this double-checks IsAssessmentOpen again)
                         */
                        new CanRespondToAssessment($this->getAccess()),
                    )
                ),
            );
        });
    }


    /**
     * Convert route params into an assessment object
     *
     * @param array $params
     * @param null|User $student If provided, only load this student's participation
     * @return array
     * @throws ContainerExceptionInterface
     * @throws DefinitionException If a specified join mapper is not found
     * @throws DomainException If user has interaction but not participant record
     * @throws Exception If assessment is not found
     * @throws InvalidArgumentException If assessment class not registered
     * @throws LogicException If cyclic graph detected
     * @throws NotFoundExceptionInterface
     * @throws UnexpectedValueException If unable to derive project participant collections
     * @throws \Exception If not found or deleted
     */
    protected function preloadAssessment(array $params, User $student = null): array
    {
        try {
            $assessment = $this->getAssessmentMapperProxy()->findByIdForReview($params['assessmentId'], $student);
        } catch (MapperException $e) {
            // Possibly thrown by base findByOne() mapper call, but masked by overriding @method annotation
            throw $this->getErrorMapper()->generateException(ErrorMapper::ERROR_GENERIC_NOT_FOUND);
        }

        // Not a quiz: invalid
        if (!$assessment instanceof QuizDomainObject) {
            throw $this->getErrorMapper()->generateException(ErrorMapper::ERROR_GENERIC_NOT_FOUND);
        }

        return [
            'assessment' => $assessment
        ];
    }


    /**
     * Convert route params into an assessment object, and a destination
     * assessment object.
     *
     * @param array $params
     * @param User|null $student
     * @return array
     * @throws ContainerExceptionInterface
     * @throws DefinitionException If a specified join mapper is not found
     * @throws DomainException If user has interaction but not participant record
     * @throws Exception If assessment is not found
     * @throws InvalidArgumentException If assessment class not registered
     * @throws LogicException If cyclic graph detected
     * @throws NotFoundExceptionInterface
     * @throws UnexpectedValueException If unable to derive project participant collections
     */
    protected function preloadAssessmentAndDestinationAssessment(array $params, User $student = null): array
    {
        $sourceAssessment      = $this->preloadAssessment(
            ['assessmentId' => $params['sourceId']],
            $student
        )['assessment'];
        $destinationAssessment = $this->preloadAssessment(
            ['assessmentId' => $params['destinationId']],
            $student,
        )['assessment'];
        return [
            'source'      => $sourceAssessment,
            'destination' => $destinationAssessment,
        ];
    }


    /**
     * Convert route params into a participant object
     *
     * @param array $params
     * @return array
     * @throws ContainerExceptionInterface
     * @throws DefinitionException If a specified join mapper is not found
     * @throws DomainException If user has interaction but not participant record
     * @throws Exception If assessment is not found
     * @throws InvalidArgumentException If assessment class not registered
     * @throws LogicException If cyclic graph detected
     * @throws NotFound
     * @throws NotFoundExceptionInterface
     */
    protected function preloadAssessmentAndStudent(array $params): array
    {
        try {
            // obtain the assessment from our other preload
            $student     = $this->getUserMapper()->findById($params['studentId']);
            $assessment  = $this->preloadAssessment($params, $student)['assessment'];
        } catch (UnexpectedValueException $e) {
            // Student isn't a participant
            throw new NotFound();
        } catch (MapperException $e) {
            // either assessment or user was not found
            throw new NotFound();
        }

        return [
            'assessment'  => $assessment,
            'student'     => $student, //IsParentOfStudent accesses this
        ];
    }


    /**
     * Preloads an attempt via its learnosity reference identifier
     *
     * @param array $params
     * @return array
     * @throws ContainerExceptionInterface
     * @throws DefinitionException
     * @throws DomainException
     * @throws Exception
     * @throws InvalidArgumentException
     * @throws LogicException
     * @throws NotFound
     * @throws NotFoundExceptionInterface
     */
    protected function preloadAttempt(array $params): array
    {
        $participantPreload = $this->preloadAssessmentAndStudent($params);

        try {
            $attempt = $this->getAttemptMapper()->findOneByColumnList([
                'user_id' => $params['studentId'],
                'learnosity_reference' => $params['learnositySessionReference']
            ]);
        } catch (\Schoolbox\Mapper\Exception $e) {
            throw new NotFound();
        }

        return array_merge($participantPreload, [
            'attempt' => $attempt
        ]);
    }


    /**
     * Preloads an attempt via its learnosity session reference identifier
     * If you provided a learnosity response reference identifier it'll try
     * to populate and provide that too
     *
     * @param array $params
     * @return array
     * @throws ContainerExceptionInterface
     * @throws DefinitionException
     * @throws DomainException
     * @throws Exception
     * @throws InvalidArgumentException
     * @throws LogicException
     * @throws NotFound
     * @throws NotFoundExceptionInterface
     */
    protected function preloadAttemptForReview(array $params): array
    {
        /* Not in learnosity? No trabajo
         */
        if ('' === trim((string)$params['learnositySessionReference'])) {
            throw new NotFound();
        }

        $participantPreload = $this->preloadAssessmentAndStudent($params);

        try {
            $attempt = $this->getAttemptMapper()->findOneByColumnList([
                'user_id' => $params['studentId'],
                'learnosity_reference' => $params['learnositySessionReference'],
            ]);
        } catch (\Schoolbox\Mapper\Exception $e) {
            throw new NotFound();
        }

        /* Staff can always see it
         */
        return array_merge($participantPreload, [
            'attempt' => $attempt
        ]);
    }


    /**
     * @inheritdoc
     */
    public function buildController(): Base
    {
        /** @var AssessmentMapperProxy $assessmentDataSource */
        $assessmentDataSource = $this->injector->get('assessmentDataSource');

        /* Breadcrumb generator
         */
        $bakery = new \Schoolbox\Learning\Assessment\Breadcrumb\Factory(
            $this->getRouter()->getGenerator(),
            $this->getAuthorizer(),
            $this->getMapperFactory()->get(Access::class),
        );

        return new Controller(
            new \Schoolbox\Url\Factory($this->getConfig()),
            $bakery,
            (string)$this->getConfig()->get('learnosity_version'),
            (string)$this->getConfig()->get('learnosity_consumer_key'),
            (string)$this->getConfig()->get('learnosity_consumer_secret'),
            $this->getMapperFactory()->get(ErrorMapper::class),
            $assessmentDataSource,
            $this->getMapperFactory()->get(\Schoolbox\Learning\Assessment\Project\ResultCache\Mapper::class),
            $this->getMapperFactory()->get(\Schoolbox\Learning\Quiz\Mapper::class),
            $this->getMapperFactory()->get(\Schoolbox\Learning\Quiz\Attempt\Mapper::class),
            $this->getMapperFactory()->get(\Schoolbox\Learning\Learnosity\Feedback\Mapper::class),
            $this->getMapperFactory()->get(UserMapper::class),
            $this->getMapperFactory()->get(StateMapper::class),
            $this->injector->get('httpFactory'),
            $this->injector->get('taskd.httpRequestMessageFactory'),
            $this->injector->get('taskd.messageProducer'),
        );
    }


    /**
     * {@inheritdoc}
     */
    public function initTemplatePaths(IChainLoader $templateLoader): void
    {
        $this->addTemplatePath($templateLoader, __DIR__ . '/templates');
    }


    /**
     * @return Mapper|UserMapper
     * @throws InvalidArgumentException
     */
    protected function getUserMapper()
    {
        return $this->getMapperFactory()->get(UserMapper::class);
    }


    /**
     * @return ErrorMapper
     * @throws InvalidArgumentException
     */
    protected function getErrorMapper()
    {
        return $this->getMapperFactory()->get(ErrorMapper::class);
    }


    /**
     * @return Mapper|\Schoolbox\Learning\Quiz\Attempt\Mapper
     * @throws InvalidArgumentException
     */
    protected function getAttemptMapper()
    {
        return $this->getMapperFactory()->get(\Schoolbox\Learning\Quiz\Attempt\Mapper::class);
    }


    /**
     * @return object|AssessmentMapperProxy
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    protected function getAssessmentMapperProxy()
    {
        return $this->injector->get('assessmentDataSource');
    }


    /**
     * @return Access
     * @throws InvalidArgumentException
     */
    protected function getAccess()
    {
        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return $this->getMapperFactory()->get(Access::class);
    }


    /**
     * @return Mapper|TermMapper
     * @throws InvalidArgumentException If unable to find mapper class
     */
    private function getTermMapper()
    {
        return $this->getMapperFactory()->get(TermMapper::class);
    }
}
