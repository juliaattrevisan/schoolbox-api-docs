<?php
/**
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
declare(strict_types=1);

namespace Custom\Tigs\Summative\Report;

use Alaress;
use Alaress\Authorizer\Failure\InvalidPath;
use Alaress\Twig\Loader\IChainLoader;
use Aura\Di\Container;
use Aura\Di\ContainerInterface;
use Alaress\Authorizer\Rule;
use Custom\Tigs\Summative\Config;
use Schoolbox\Application\Exception\NotFound;
use Schoolbox\Role;
use Schoolbox\UserMapper;
use InvalidArgumentException;
use UnexpectedValueException;
use Schoolbox\Mapper;
use Schoolbox\User;
use Schoolbox\Resource\Folder;
use Schoolbox\Assessment\Term;

class Module extends Alaress\Module\Module
{

    public const BASE_NAME = 'tigsSummativeReport';


    /**
     * @inheritdoc
     */
    public function __construct(ContainerInterface $injector, string $basename = '')
    {
        parent::__construct($injector, $basename ?: self::BASE_NAME);
    }


    /**
     * @inheritdoc
     */
    protected function registerRoutes(): void
    {
        $preloadUser = function ($params) {
            return $this->preloadUser($params);
        };

        $preloadUserAndFolder = function ($params) {
            return $this->preloadUserAndFolder($params);
        };

        $canSeeParticipation = $this->buildRuleCanViewReport(Config::_TIGS_PARTICIPATION_REPORT_ENABLED, 'Participation');
        $canSeeSubjectReport = $this->buildRuleCanViewSubjectReport();

        $this->register('GET', '/user/{userId}', 'getParticipationReport', $canSeeParticipation, $preloadUser);
        $this->register('GET', '/{userId}/folder/{folderId}', 'getSubjectReport', $canSeeSubjectReport, $preloadUserAndFolder);
    }


    /**
     * @inheritdoc
     */
    public function initControllers(ContainerInterface $injector): void
    {
        $injector->set('controller:' . self::BASE_NAME, $injector->lazyNew(Controller::class));
    }


    /**
     * @inheritdoc
     */
    public function initTemplatePaths(IChainLoader $templateLoader): void
    {
        $this->addTemplatePath($templateLoader, __DIR__ . '/templates');
    }


    /**
     * @inheritdoc
     * @param Container|ContainerInterface $injector The dependency injection container.
     * @throws InvalidArgumentException
     */
    protected function preInitControllerDependencies(ContainerInterface $injector): void
    {
        $injector->params[Controller::class] = [
            'externalUser' => $injector->lazyGet('externalUser'),
            'termMapper'   => $this->getMapperFactory()->getLazy(Term\Mapper::class),
        ];
    }


    /**
     * Preload a User instance
     *
     * @param array $params
     * @return array
     * @throws InvalidArgumentException
     * @throws NotFound
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
            throw new NotFound('User Not Found', 0, $e);
        }
    }


    /**
     * Loads a User and Folder Model instance from route params
     *
     * @param array $params
     * @return array
     * @throws InvalidArgumentException
     * @throws NotFound
     * @throws UnexpectedValueException
     */
    private function preloadUserAndFolder(array $params): array
    {
        $preload = $this->preloadUser($params);

        if (($params['folder'] ?? null) instanceof Folder\Model) {
            $preload['folder'] = $params['folder'];
        } elseif (!\array_key_exists('folderId', $params)) {
            throw new UnexpectedValueException("No 'folderId' found in route params");
        } else {
            try {
                $preload['folder'] = $this->getFolderMapper()->findById($params['folderId']);
            } catch (Mapper\Exception $e) {
                throw new NotFound('Folder Not Found', 0, $e);
            }
        }
        return $preload;
    }


    /**
     * Returns an auth rule for viewing the one of the Reports
     *
     * * Viewee must be in years 7-12
     * * Viewer must be SU or Staff, or student/parent and config enabled
     *
     * @param string $configName
     * @param string $reportName
     * @return Rule\IAuthorizationRule
     */
    private function buildRuleCanViewReport(string $configName, string $reportName): Rule\IAuthorizationRule
    {
        return new Rule\LazyCallback(function () use ($configName, $reportName) {
            return new Rule\All(
                new Rule\Callback(function (array $params) {
                    // Because preload exceptions are not handled immediately, we can't fatal if the preload failed.
                    if (!($params['user'] ?? null) instanceof User) {
                        throw new InvalidPath("No user found in route params");
                    }
                    /** @var User $student */
                    $student  = $params['user'];
                    // User must be a student
                    if (!$student->getRole()->isStudent()) {
                        throw new InvalidPath("User must be a student. Found '{$student->getRole()->getType()}'");
                    }
                    $userYear = $student->getYear();
                    if (!\is_numeric($userYear) || $userYear < 7 || $userYear > 12) {
                        throw new InvalidPath("User is not in years 7-12, found '$userYear'");
                    }
                }),
                new Rule\Any(
                    new Rule\IsSuperuser(),
                    new Rule\HasRole(Role::STAFF),
                    new Rule\All(
                        new Rule\Callback(function () use ($configName, $reportName) {
                            if (!$this->getConfig()->get($configName)) {
                                throw new Alaress\Authorizer\Failure\Unauthorized("{$reportName} report is disabled");
                            }
                        }),
                        new Rule\IsUserOrParentOfUser($this->getExternalUser(), $this->getUserMapper(), 'userId')
                    )
                )
            );
        });
    }


    /**
     * Returns an auth rule for the subject report route
     *
     * @return Rule\IAuthorizationRule
     */
    private function buildRuleCanViewSubjectReport(): Rule\IAuthorizationRule
    {
        return new Rule\LazyCallback(function () {
            return new Rule\All(
                $this->buildRuleCanViewReport(Config::_TIGS_SUBJECT_REPORT_ENABLED, 'Subject'),
                new Rule\Callback(function (array $params) {
                    // Because preload exceptions are not handled immediately, we can't fatal if the preload failed.
                    if (!($params['folder'] ?? null) instanceof Folder\Model) {
                        throw new InvalidPath("No folder model found in route params");
                    }
                    /** @var Folder\Model $folder */
                    $folder = $params['folder'];
                    // Folder must exist in SB (empty folder models are created for subjects in the SIS but not in SB)
                    if (null === $folder->getId()) {
                        throw new InvalidPath("Folder does not exist in SB");
                    }
                    // Student must be provided
                    if (!($params['user'] ?? null) instanceof User) {
                        throw new InvalidPath("No user found in route params");
                    }
                    // .. and enrolled in the specified folder
                    /** @var User $user */
                    $user = $params['user'];
                    if (!$this->isUserEnrolledInFolder($user, $folder)) {
                        throw new InvalidPath("User {$user->getId()} is not enrolled in folder {$folder->getId()}");
                    }
                })
            );
        });
    }


    /**
     * Returns whether a user is enrolled in a class folder by examining external SIS class membership
     *
     * @param User         $user
     * @param Folder\Model $folder
     * @return bool
     */
    private function isUserEnrolledInFolder(User $user, Folder\Model $folder): bool
    {
        foreach ($this->getExternalUser()->getSubjectList($user) as $subject) {
            foreach ($folder->getCodes() as $code) {
                if ($code->getCode() === $subject['code']) {
                    return true;
                }
            }
        }
        return false;
    }


    /**
     * @return UserMapper|Mapper
     * @throws InvalidArgumentException
     */
    private function getUserMapper(): UserMapper
    {
        return $this->getMapperFactory()->get(UserMapper::class);
    }


    /**
     * @return Folder\Mapper|Mapper
     * @throws InvalidArgumentException
     */
    private function getFolderMapper(): Folder\Mapper
    {
        return $this->getMapperFactory()->get(Folder\Mapper::class);
    }
}
