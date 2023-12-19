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
use Schoolbox\Learning\Authorizer\Rule\IsInteractionUnLocked;
use Schoolbox\Mapper;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Mapper\DefinitionException;
use Schoolbox\Permission\Access\Access;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Resource\Folder\Model as FolderModel;
use Schoolbox\Role;
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
        $instructorPerms = $this->buildAuthRuleHasInstructorAccess();

        /* Someone who can complete an attempt
         *
         * The current user must match {studentId} in the routes
         * ie. you're the owner of something in the url, among other rules
         */
        $learnerCanAttempt = $this->buildAuthRuleCanSubmit();

        /* Someone who has completed an attempt
         *
         * This turns out to be a special subset of $learnerCanAttempt which is identical
         * except we remove the check for Attempt counts; it's not needed on the outro screen.
         * It's also not needed when you're trying to review it.
         */
        $learnerCompletedAttempt = $this->buildAuthRuleCompletion();

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

        /* Conclude an attempt
         */
        $this->register('GET', '/{assessmentId}/attempt/{studentId}/{learnositySessionReference}', 'getCompleteAttempt', $learnerCompletedAttempt, $preloadAttempt);

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
         */
        $this->register('GET', '/{assessmentId}/review/{studentId}/{learnositySessionReference}', 'getReview', $learnerCompletedAttempt, $preloadAttempt);

        /* Provide feedback to a student's attempt
         */
        $this->register('GET', '/{assessmentId}/feedback/{studentId}/{learnositySessionReference}', 'getFeedback', $instructorPerms, $preloadAttemptForReview);

        /* Persist feedback to a student's attempt
         */
        $this->register('POST', '/{assessmentId}/feedback/{studentId}/{learnositySessionReference}', 'postFeedback', $instructorPerms, $preloadAttemptForReview);

        /* Start horizontal marking using a Learnosity Report
         */
        $this->register('GET', '/{assessmentId}/horizontal', 'getHorizontalMarking', $instructorPerms, $preloadAssessment);

        /* Finalise a set of attempts via their learnosity_references
         */
        $this->register('POST', '/{assessmentId}/horizontal', 'postHorizontalMarking', $instructorPerms, $preloadAssessment);

        /* Provide updated score for a particular student response
         * (used predominantly in horizontal marking)
         */
        $this->register('POST', '/{assessmentId}/horizontal/{studentId}/feedback/{learnositySessionReference}/score', 'postFeedbackScore', $instructorPerms, $preloadAttemptForReview);

        /* Provide updated comment for a particular student response
         * (used predominantly in horizontal marking)
         */
        $this->register('POST', '/{assessmentId}/horizontal/{studentId}/feedback/{learnositySessionReference}/comment', 'postFeedbackComment', $instructorPerms, $preloadAttemptForReview);

        /* Provide updated comment for a particular student attempt
         * (used predominantly in horizontal marking)
         */
        $this->register('POST', '/{assessmentId}/horizontal/{studentId}/feedback/{learnositySessionReference}/attempt-comment', 'postFeedbackAttemptComment', $instructorPerms, $preloadAttemptForReview);

        /* Archive a set of reference identifiers in Learnosity
         */
        $this->register('GET', '/archive/{learnosityReference}', 'getArchiveReference', $instructorPerms, null);

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

        /* Older exploratory urls that aren't part of our conventional workflow
         */
        $this->register('GET', '/activityList', 'getActivityList', $instructorPerms, null);
        $this->register('GET', '/items', 'getItems', $instructorPerms, null);
        $this->register('GET', '/sessionList', 'getSessionListReport', $instructorPerms, null);
    }


    /**
     * Prevent non-instructors from marking or modifying assessments.
     *
     * This uses the `IsInstructor` validator, which is the new standard for
     * checking if a user has instructor permission over a folder.
     *
     * @return Rule\IAuthorizationRule
     * @throws InvalidArgumentException
     */
    private function buildAuthRuleHasInstructorAccess(): Rule\IAuthorizationRule
    {
        return new Rule\All(
            new Rule\IsLoggedIn(),
            new Rule\Any(
                new Rule\IsSuperuser(),
                new IsInstructor(
                    $this->getAccess(),
                    static function (array $params): FolderModel {
                        if (isset($params['assessment'])) {
                            $ctx = $params['assessment']->getContext()->getFolder();
                        } elseif (isset($params['participant'])) {
                            $ctx = $params['participant']->getAssessable()->getContext()->getFolder();
                        } elseif (isset($params['context'])) {
                            $ctx = $params['context']->getFolder();
                        } else {
                            throw new NotFound('No assessment or context preloaded');
                        }

                        return $ctx;
                    }
                )
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
    private function buildAuthRuleCanSubmit(): Rule\LazyCallback
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
                    $this->buildAuthRuleHasInstructorAccess(),
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
                         * they can make attempts (this double-checks IsAssesmentOpen again)
                         */
                        new CanRespondToAssessment($this->getAccess()),
                    )
                )
            );
        });
    }


    /**
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
    private function buildAuthRuleCompletion(): Rule\LazyCallback
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
                    $this->buildAuthRuleHasInstructorAccess(),
                    new Rule\IsParentOfUser($this->getExternalUser(), $this->getMapperFactory()->get(\Schoolbox\UserMapper::class), 'studentId'),
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
                    $this->buildAuthRuleHasInstructorAccess(),
                    new Rule\All(
                        new Rule\HasRole(Role::STUDENT),
                        new Rule\IsUser('studentId')
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
     */
    protected function preloadAssessment(array $params, User $student = null): array
    {
        try {
            $assessment = $this->getAssessmentMapperProxy()->findByIdForReview($params['assessmentId'], $student);
        } catch (MapperException\Deleted $e) {
            $assessment = $e->getDeletedObject();
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
