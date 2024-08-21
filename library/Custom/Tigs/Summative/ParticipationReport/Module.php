<?php

/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Custom\Tigs\Summative\ParticipationReport;

use Alaress;
use Alaress\Authorizer\Failure\InvalidPath;
use Alaress\Twig\Loader\IChainLoader;
use Alaress\Web\Controller\Base;
use Alaress\Authorizer\Rule;
use Custom\Tigs\Summative\Config;
use Custom\Tigs\Summative\StudentAttributes;
use Psr\Container\ContainerInterface;
use Schoolbox\Assessment\Rubric\DataModel as RubricDataModel;
use Schoolbox\Permission\Access\Access;
use Schoolbox\Role;
use Schoolbox\UserMapper;
use InvalidArgumentException;
use UnexpectedValueException;
use Schoolbox\Mapper;
use Schoolbox\User;
use Schoolbox\Pastoral\Mapper as PastoralMapper;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Schoolbox\Resource\Textbox;
use Schoolbox\Learning\Assessment\DataModel as Assessment;
use Schoolbox\Learning\Course\DataModel\Implementation as CourseImpl;

class Module extends Alaress\Module\Module
{
    /**
     * @var string
     */
    protected $basename = 'tigsParticipationReport';

    /**
     * @inheritdoc
     */
    protected function registerRoutes(): void
    {
        $preloadUser = function ($params) {
            return $this->preloadUser($params);
        };

        $canSeeParticipationReport = $this->buildRuleCanViewReport();

        $this->register('GET', '/user/{userId}{/reportDate}', 'getReport', $canSeeParticipationReport, $preloadUser);
    }

    /**
     * @inheritdoc
     */
    public function buildController(): Base
    {
        return new Controller(
            $this->injector->get('databaseInternal'),
            $this->injector->get('externalUser'),
            $this->injector->get('externalClass'),
            $this->getMapperFactory()->get(CourseImpl\Mapper::class),
            $this->getMapperFactory()->get(UserMapper::class),
            $this->getMapperFactory()->get(PastoralMapper::class),
            $this->getMapperFactory()->get(FolderMapper::class),
            $this->getMapperFactory()->get(Textbox\Mapper::class),
            $this->getMapperFactory()->get(Access::class),
            $this->injector->get('databaseExternal'),
            $this->getMapperFactory()->get(Assessment\Mapper::class),
            $this->injector->get('assessmentDataSource'),
            $this->getMapperFactory()->get(RubricDataModel\DataSource::class)
        );
    }

    /**
     * @inheritdoc
     */
    public function initTemplatePaths(IChainLoader $templateLoader): void
    {
        $this->addTemplatePath($templateLoader, __DIR__ . '/templates');
    }

    /**
     * Preload a User instance
     *
     * @param array $params
     * @return array
     * @throws InvalidArgumentException
     * @throws InvalidPath
     * @throws UnexpectedValueException
     */
    private function preloadUser(array $params): array
    {
        if (($params['user'] ?? null) instanceof User) {
            return $params;
        }

        if (!\array_key_exists('userId', $params)) {
            throw new UnexpectedValueException("No 'userId' found in route params");
        }

        try {
            return ['user' => $this->getUserMapper()->findById($params['userId'])];
        } catch (Mapper\Exception $e) {
            throw new InvalidPath('User Not Found', 0, $e);
        }
    }


    /**
     * Returns an auth rule for viewing the participation report
     *
     * * Viewer must be logged in
     * * Viewee must be in years 7-12
     * * Viewer must be SU or Staff, or student/parent and config enabled
     *
     * @return Rule\IAuthorizationRule
     */
    private function buildRuleCanViewReport(): Rule\IAuthorizationRule
    {
        return new Rule\LazyCallback(function () {
            return new Rule\All(
                new Rule\IsLoggedIn(),
                new Rule\Callback(function (array $params): void {
                    // Because preload exceptions are not handled immediately, we can't fatal if the preload failed.
                    if (!($params['user'] ?? null) instanceof User) {
                        throw new InvalidPath("No user found in route params");
                    }
                    /** @var User $student */
                    $student = $params['user'];
                    // User must be a student
                    if (!$student->getRole()->isStudent()) {
                        throw new InvalidPath("User must be a student. Found '{$student->getRole()->getType()}'");
                    }
                    $userYear = $student->getYear() ? $student->getYear()->getName() : null;
                    if (!\is_numeric($userYear) || $userYear < 7) {
                        throw new InvalidPath("User is not in years 7 or above, found '$userYear'");
                    }
                    $attributes      = new StudentAttributes($this->getConfig(), $student, null, $params['reportDate'] ?? null);
                    $reportYearLevel = $attributes->getYearLevel();
                    if ($reportYearLevel < StudentAttributes::YEAR_LEVEL_MIN || $reportYearLevel > StudentAttributes::YEAR_LEVEL_MAX) {
                        throw new InvalidPath("User year level not valid for report date, found '$userYear'");
                    }
                }),
                new Rule\Any(
                    new Rule\IsSuperuser(),
                    new Rule\HasRole(Role::STAFF),
                    new Rule\All(
                        new Rule\Callback(function (): void {
                            if (!$this->getConfig()->get(Config::_TIGS_PARTICIPATION_REPORT_ENABLED)) {
                                throw new Alaress\Authorizer\Failure\Unauthorized("Participation report is disabled");
                            }
                        }),
                        new Rule\IsUserOrParentOfUser($this->getExternalUser(), $this->getUserMapper(), 'userId')
                    )
                )
            );
        });
    }


    /**
     * @return UserMapper|Mapper
     * @throws InvalidArgumentException
     */
    private function getUserMapper(): UserMapper
    {
        return $this->getMapperFactory()->get(UserMapper::class);
    }
}
