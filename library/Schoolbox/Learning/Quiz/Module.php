<?php

/**
 * @author    Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Learning\Quiz;

use Alaress\Web\Controller\Base;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use Schoolbox\Application\Exception\NotFound;
use Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\Learning\AssessmentContext;
use Schoolbox\Learning\Assessment\AssessmentContextType;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\Course\DataModel\Implementation\Unit\Mapper as UnitImplementationMapper;
use Schoolbox\Learning\Course\DataModel\Specification\Unit\Mapper as UnitSpecificationMapper;
use Schoolbox\Learning\Authorizer\Rule\IsInstructorOrHasHomepageAccess;
use Schoolbox\Learning\Quiz\Attempt\Answer\Mapper as QuizAnswerMapper;
use Schoolbox\Learning\Quiz\Attempt\Mapper as QuizAttemptMapper;
use Schoolbox\Learning\Quiz\Mapper as QuizMapper;
use Schoolbox\Learning\Quiz\Section\Mapper as QuizSectionMapper;
use Schoolbox\Learning\Quiz\Section\Question\Mapper as QuizQuestionMapper;
use Schoolbox\Learning\Quiz\Section\Question\Solution\Mapper as QuizSolutionMapper;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Module\RestfulModule;
use Schoolbox\Module\StandardModuleTrait;
use Alaress\Authorizer\Rule;
use Schoolbox\Learning\Authorizer\Rule\IsModifyUnLocked;
use Schoolbox\Learning\Assessment\LockedChecker;
use Schoolbox\Permission\Access\Access;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Schoolbox\Role;
use Schoolbox\Assessment\Term;

/**
 * The Purpose of this class is to:
 * * Provide 302 Redirection between the new Assessment Quiz routes and the old Assess Quiz routes, and
 * * Act as a placeholder for the planned Quiz rewrite of ~170
 *
 * During the Quiz re-write, all routes here can be considered TODOs.
 */
class Module extends RestfulModule
{
    use StandardModuleTrait;

    /**
     * @var string
     */
    protected $basename = 'learningQuiz';

    /**
     * @param ContainerInterface $injector
     * @param RestfulModule|null $context
     */
    public function __construct(ContainerInterface $injector, ?RestfulModule $context)
    {
        parent::__construct($injector, QuizMapper::class, 'quiz', $context);
    }


    /**
     * Hook method for setting up routes via self::register()
     *
     * @return void
     */
    protected function registerRoutes()
    {
        $canWrite = new Rule\All(
            new Rule\IsLoggedIn(),
            new Rule\Any(
                new Rule\HasRole(Role::STAFF),
                new Rule\IsSuperuser(),
                new IsInstructorOrHasHomepageAccess(
                    Access::getInstance(),
                    self::resolveHomepageAccessContext(...),
                )
            )
        );

        $canModify = new Rule\Any(
            new Rule\IsSuperuser(),
            new Rule\All(
                $canWrite,
                new IsModifyUnLocked(new LockedChecker($this->getMapperFactory()->get(Term\Mapper::class)))
            )
        );

        $preloadContext = function ($params) {
            return $this->preloadContext($params);
        };

        $preloadAssessment = function ($params) {
            /** @var \Schoolbox\Learning\Assessment\DataModel\MapperProxy $dataSource */
            $dataSource = $this->injector->get('assessmentDataSource');

            return [
                "assessmentId"   => $params["assessmentId"],
                "assessment"     => $dataSource->findById($params["assessmentId"])
            ];
        };

        // pluralise for REST
        $contextTypes = AssessmentContextType::getStringValuesAsRegex(function ($name) {
            return $name . 's';
        });

        $this->register('POST', "/quiz/attempts/delete", 'postAttemptDelete', $canWrite);
        $this->register('GET', "/quiz/summary/{assessmentId}", 'getQuizSummary', $canModify, $preloadAssessment);
//        $this->register('GET', "/quiz/{assessmentId}/attempts/export", 'getExportAttempts', $canModify, $preloadAssessment);
        $this->register('POST', "/quiz/import/{contextType:$contextTypes}/{contextId:\\d+}", 'postImport', $canWrite, $preloadContext);
    }

    /**
     * Locates the assessment context needed for quiz write authorization.
     *
     * Routes that operate on a saved quiz preload an assessment or participant,
     * but the quiz import route used by the create page preloads only the
     * target context because there is no assessment yet. Resolving all
     * supported shapes here lets IsInstructorOrHasHomepageAccess check the
     * correct folder without assuming that every authorized quiz route has an
     * assessment.
     *
     * @param array $params Route parameters after preload callbacks have run.
     * @return AssessmentContext
     * @throws NotFound When no assessment, participant, or context has been preloaded.
     */
    private static function resolveHomepageAccessContext(array $params): AssessmentContext
    {
        if (($params['assessment'] ?? null) instanceof IAssessable) {
            return $params['assessment']->getContext();
        }

        if (isset($params['participant'])) {
            return $params['participant']->getAssessable()->getContext();
        }

        if (($params['context'] ?? null) instanceof AssessmentContext) {
            return $params['context'];
        }

        throw new NotFound('No assessment or context preloaded');
    }


    /**
     * Convert route params into a context object
     *
     * @param array $params
     * @return array
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    protected function preloadContext(array $params): array
    {
        try {
            $contextFactory = new \Schoolbox\Learning\Assessment\DataModel\Context\Factory(
                $this->getMapperFactory()->get(FolderMapper::class),
                $this->getMapperFactory()->get(UnitImplementationMapper::class),
                $this->injector->get('assessmentDataSource'),
                $this->getMapperFactory()->get(UnitSpecificationMapper::class)
            );
            $c = $contextFactory->produce($params['contextType'], $params['contextId']);
        } catch (MapperException\Deleted $e) {
            $c = $e->getDeletedObject();
        } catch (MapperException $e) {
            throw new NotFound();
        }

        return [
            'context' => $c
        ];
    }


    /**
     * @inheritdoc
     */
    public function buildController(): Base
    {
        /* Breadcrumb generator
         */
        $bakery = new \Schoolbox\Learning\Assessment\Breadcrumb\Factory(
            $this->getRouter()->getGenerator(),
            $this->getAuthorizer(),
            $this->getMapperFactory()->get(Access::class),
        );

        return new Controller(
            $this->getMapperFactory()->get(Mapper::class),
            $this->getMapperFactory()->get(ErrorMapper::class),
            $this->getMapperFactory()->get(QuizSectionMapper::class),
            $this->getMapperFactory()->get(QuizQuestionMapper::class),
            $this->getMapperFactory()->get(QuizSolutionMapper::class),
            $this->getMapperFactory()->get(QuizAttemptMapper::class),
            $this->getMapperFactory()->get(QuizAnswerMapper::class),
            $this->injector->get('assessmentDataSource'),
            $this->getMapperFactory()->get(\Schoolbox\Learning\Assessment\Project\ResultCache\Mapper::class),
            $this->getMapperFactory()->get(\Schoolbox\Learning\Assessment\WorkType\Mapper::class),
            $this->getMapperFactory()->get(\Schoolbox\Assessment\Component\SubmissionBox\MarkTypeMapper::class),
            $this->getMapperFactory()->get(\Schoolbox\Resource\Storage\Mapper::class),
            $this->getMapperFactory()->get(\Schoolbox\Assessment\Component\SubmissionBox\GradeLevel\Group\Mapper::class),
            $this->getMapperFactory()->get(\Schoolbox\Curriculum\Node\Mapper::class),
            $bakery,
            (string)$this->getConfig()->get('learnosity_version'),
            (string)$this->getConfig()->get('learnosity_consumer_key'),
            (string)$this->getConfig()->get('learnosity_consumer_secret'),
        );
    }
}
