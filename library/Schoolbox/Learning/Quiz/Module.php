<?php

/**
 * @author    Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Learning\Quiz;

use Alaress\Authorizer\Authorizer;
use Alaress\Web\Controller\Base;
use Alaress\Web\Router\PathMatcher;
use Aura\Router\Router;
use Psr\Container\ContainerInterface;
use Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\Quiz\Attempt\Answer\Mapper as QuizAnswerMapper;
use Schoolbox\Learning\Quiz\Attempt\Mapper as QuizAttemptMapper;
use Schoolbox\Learning\Quiz\Mapper as QuizMapper;
use Schoolbox\Learning\Quiz\Section\Mapper as QuizSectionMapper;
use Schoolbox\Learning\Quiz\Section\Question\Mapper as QuizQuestionMapper;
use Schoolbox\Learning\Quiz\Section\Question\Solution\Mapper as QuizSolutionMapper;
use Schoolbox\Module\RestfulModule;
use Schoolbox\Module\StandardModuleTrait;
use Alaress\Authorizer\Rule;
use Schoolbox\Learning\Authorizer\Rule\IsModifyUnLocked;
use Schoolbox\Learning\Assessment\LockedChecker;
use Schoolbox\Permission\Access\Access;
use Schoolbox\Permission\Access\UserAccess;
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
     * Initializes any routes for this module, with a given base path.
     *
     * The base path for each route needs to match up to the name of the
     * controller in $this->initControllers(), so if its base path is
     * "user", there must be a controller in the DI container called
     * "controller:user"
     *
     * @param Router $router
     * @param string $basePath The base route for this module
     */
    public function initRoutes(Router $router, $basePath)
    {
        parent::initRoutes($router, $basePath);

        $routes = [
            'GET' => [
                '/quiz/review/{assessmentId}/horizontal'                    => 'getQuizReviewHorizontal',
                '/quiz/review/{assessmentId}/{firstSectionId}/{responseId}' => 'getQuizResponseFirstSection',
                '/quiz/{assessmentId}/{userId}/attempts'                    => 'getQuizAttemptsByUser',
                '/quiz/summary/{assessmentId}'                              => 'getQuizSummary',
                '/quiz/intro/{assessmentId}'                                => 'getQuizIntro',
                '/quiz/{assessmentId}/attempts/export'                      => 'getExportAttempts',
                '/quiz/section/create/{assessmentId}'                       => 'getQuizSectionCreate',
            ],
            'POST' => [
                '/quiz/submit'                                              => 'postQuizSubmit',
                '/quiz/attempts/delete'                                     => 'postAttemptDelete',
            ]
        ];

        foreach ($routes['GET'] as $path => $alias) {
            $this->addGetRoute($router, $alias, $basePath, $path);
        }

        foreach ($routes['POST'] as $path => $alias) {
            $this->addPostRoute($router, $alias, $basePath, $path);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function initRoutePermissions(Authorizer $authorizer)
    {
        $canWrite = new Rule\All(
            new Rule\IsLoggedIn(),
            new Rule\Any(
                new Rule\HasRole(Role::STAFF),
                new Rule\IsSuperuser(),
                new Rule\HasHomepageAccess(
                    UserAccess::ACCESS_WRITE,
                    static function ($params) {
                        if ($params['assessment'] instanceof IAssessable) {
                            return $params['assessment']->getBaseContext()->getFolder()->getId();
                        }
                        return null;
                    },
                    Access::getInstance()
                )
            )
        );

        $canModify = new Rule\Any(
            new Rule\IsSuperuser(),
            new Rule\All(
                $canWrite,
                new IsModifyUnLocked(new LockedChecker($this->getTermMapper()))
            )
        );

        $this->addRoutePermission($authorizer, 'postAttemptDelete', $canWrite);
        $this->addRoutePermission($authorizer, 'getQuizSummary', $canModify);
    }

    /**
     * @return Term\Mapper
     * @throws \InvalidArgumentException If unable to find mapper class
     */
    private function getTermMapper()
    {
        return $this->getMapperFactory()->get(Term\Mapper::class);
    }

    public function initRoutePreloads(PathMatcher $matcher)
    {
        $matcher->add('#getQuizSummary#', function ($params) {
            /** @var \Schoolbox\Learning\Assessment\DataModel\MapperProxy $dataSource */
            $dataSource = $this->injector->get('assessmentDataSource');

            return [
                "assessmentId"   => $params["assessmentId"],
                "assessment"     => $dataSource->findById($params["assessmentId"])
            ];
        });
    }

    /**
     * @inheritdoc
     */
    public function buildController(): Base
    {
        return new Controller(
            $this->getMapperFactory()->get(Mapper::class),
            $this->getMapperFactory()->get(ErrorMapper::class),
            $this->getMapperFactory()->get(QuizSectionMapper::class),
            $this->getMapperFactory()->get(QuizQuestionMapper::class),
            $this->getMapperFactory()->get(QuizSolutionMapper::class),
            $this->getMapperFactory()->get(QuizAttemptMapper::class),
            $this->getMapperFactory()->get(QuizAnswerMapper::class)
        );
    }
}
