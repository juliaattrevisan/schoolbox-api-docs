<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Learnosity;

use DomainException;
use InvalidArgumentException;
use LogicException;
use Schoolbox\Application\Exception\Exception;
use Schoolbox\Application\Exception\NotFound;
use Schoolbox\Mapper\DefinitionException;
use Schoolbox\Permission\Access\Access;
use Schoolbox\UserMapper;
use UnexpectedValueException;
use Alaress\Authorizer\Rule;
use Alaress\Module\Module as BaseModule;
use Alaress\Twig\Loader\IChainLoader;
use Alaress\Web\Controller\Base;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Schoolbox\Application\Exception as ApplicationException;
use Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\Learning\Assessment\DataModel\MapperProxy as AssessmentMapperProxy;
use Schoolbox\Learning\Assessment\Quiz\Model as QuizDomainObject;
use Schoolbox\Mapper;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Role;
use Schoolbox\User;

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
     */
    protected function registerRoutes(): void
    {
        /* Staff only
         */
        $instructorPerms = $this->instructorPerms();

        /* The current user must match {studentId} in the routes
         * ie. you're the owner of something in the url
         *
         * (All staff are also allowed here)
         */
        $learnerIsUserPerms = $this->learnerIsUserPerms();

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
        $this->register('POST', '/{assessmentId}/attempt/{studentId}/complete', 'postCompleteAttempt', $learnerIsUserPerms, $preloadParticipant);

        /* Conclude an attempt
         */
        $this->register('GET', '/{assessmentId}/attempt/{studentId}/{learnositySessionReference}', 'getCompleteAttempt', $learnerIsUserPerms, $preloadAttempt);

        /* Start a new attempt
         */
        $this->register('GET', '/{assessmentId}/attempt/{studentId}', 'getAttempt', $learnerIsUserPerms, $preloadParticipant);

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
        $this->register('GET', '/{assessmentId}/review/{studentId}/{learnositySessionReference}', 'getReview', $learnerIsUserPerms, $preloadAttempt);

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
            $instructorPerms,
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
     * @return Rule\LazyCallback
     */
    private function instructorPerms(): Rule\LazyCallback
    {
        return new Rule\LazyCallback(function () {
            return new Rule\All(
                new Rule\IsLoggedIn(),
                new Rule\Any(
                    new Rule\IsSuperuser(),
                    new Rule\HasRole(Role::STAFF)
                )
            );
        });
    }


    /**
     * The logged-in user is either:
     *  - the student user referenced in the url (meant to stop students accessing other student's urls),
     *  - or a staff member.
     *
     * @return Rule\LazyCallback
     */
    private function learnerIsUserPerms(): Rule\LazyCallback
    {
        return new Rule\LazyCallback(function () {
            return new Rule\All(
                new Rule\IsLoggedIn(),
                new Rule\Any(
                    new Rule\HasRole(Role::STAFF),
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
     * @param array     $params
     * @param null|User $student If provided, only load this student's participation
     * @return array
     * @throws ApplicationException\Exception If assessment is not found
     * @throws DomainException If user has interaction but not participant record
     * @throws InvalidArgumentException If assessment class not registered
     * @throws LogicException If cyclic graph detected
     * @throws Mapper\DefinitionException If a specified join mapper is not found
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
     * @param array     $params
     * @param User|null $student
     * @return array
     * @throws ApplicationException\Exception If assessment is not found
     * @throws DomainException If user has interaction but not participant record
     * @throws InvalidArgumentException If assessment class not registered
     * @throws LogicException If cyclic graph detected
     * @throws Mapper\DefinitionException If a specified join mapper is not found
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
     * @throws ApplicationException\Exception If assessment is not found
     * @throws DomainException If user has interaction but not participant record
     * @throws InvalidArgumentException If assessment class not registered
     * @throws LogicException If cyclic graph detected
     * @throws Mapper\DefinitionException If a specified join mapper is not found
     * @throws NotFound
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
     * @throws Exception
     * @throws DomainException
     * @throws InvalidArgumentException
     * @throws LogicException
     * @throws DefinitionException
     * @throws NotFound
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
     * @throws Exception
     * @throws DomainException
     * @throws InvalidArgumentException
     * @throws LogicException
     * @throws DefinitionException
     * @throws NotFound
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
}
