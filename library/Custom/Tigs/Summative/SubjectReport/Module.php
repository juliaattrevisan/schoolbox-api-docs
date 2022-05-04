<?php

/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Custom\Tigs\Summative\SubjectReport;

use Alaress;
use Alaress\Authorizer\Failure\InvalidPath;
use Alaress\Authorizer\Rule;
use Alaress\Twig\Loader\IChainLoader;
use Alaress\Web\Controller\Base;
use Custom\Tigs\Summative\Config;
use Custom\Tigs\Summative\ParticipationReport\FolderFetcher;
use Custom\Tigs\Summative\StudentAttributes;
use InvalidArgumentException;
use Schoolbox\Assessment\Rubric\DataModel\DataSource as RubricDataSource;
use Schoolbox\Learning\Assessment\DataModel as Assessment;
use Schoolbox\Learning\Assessment\Visibility;
use Schoolbox\Learning\Course\DataModel\Implementation as CourseImpl;
use Schoolbox\Mapper;
use Schoolbox\Permission\Access\Access;
use Schoolbox\Resource\Folder;
use Schoolbox\Resource\Textbox;
use Schoolbox\Role;
use Schoolbox\User;
use Schoolbox\UserMapper;
use UnexpectedValueException;

class Module extends Alaress\Module\Module
{
    /**
     * @var string
     */
    protected $basename = 'tigsSubjectReport';

    /**
     * @inheritdoc
     */
    protected function registerRoutes(): void
    {
        $preloadUserAndFolder = function ($params) {
            return $this->preloadUserAndFolder($params);
        };

        $canSeeSubjectReport = $this->buildRuleCanViewSubjectReport();

        $this->register('GET', '/{userId}/folder/{folderId}{/reportDate}', 'getReport', $canSeeSubjectReport, $preloadUserAndFolder);
    }

    /**
     * @inheritdoc
     */
    public function buildController(): Base
    {
        return new Controller(
            $this->injector->get('externalUser'),
            $this->injector->get('externalClass'),
            $this->getMapperFactory()->get(CourseImpl\Mapper::class),
            $this->injector->get('assessmentDataSource'),
            $this->getMapperFactory()->get(RubricDataSource::class),
            $this->getMapperFactory()->get(UserMapper::class),
            $this->getMapperFactory()->get(Assessment\Mapper::class),
            $this->getMapperFactory()->get(Textbox\Mapper::class),
            new Visibility($this->getMapperFactory()->get(Access::class)),
            $this->injector->get('databaseInternal')
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
     * Loads a User and Folder Model instance from route params
     *
     * @param array $params
     * @return array
     * @throws InvalidArgumentException
     * @throws InvalidPath
     * @throws UnexpectedValueException
     * @throws Mapper\DefinitionException
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
                throw new InvalidPath('Folder Not Found', 0, $e);
            }
        }
        return $preload;
    }


    /**
     * Returns an auth rule for the Subject Report route
     *
     * * Viewer is logged in
     * * Specified user is student in years 7-12
     * * Viewer must be SU or Staff, or student/parent and config enabled
     * * Specified folder exists, and student is enrolled in it
     *
     * @return Rule\IAuthorizationRule
     */
    private function buildRuleCanViewSubjectReport(): Rule\IAuthorizationRule
    {
        return new Rule\LazyCallback(function () {
            return new Rule\All(
                new Rule\IsLoggedIn(),
                new Rule\Callback(function (array $params, User $currentUser) {
                    // Because preload exceptions are not handled immediately, we can't fatal if the preload failed.
                    if (!($params['user'] ?? null) instanceof User) {
                        throw new InvalidPath("No user found in route params");
                    }
                    if (!($params['folder'] ?? null) instanceof Folder\Model) {
                        throw new InvalidPath("No folder model found in route params");
                    }

                    /** @var User $student */
                    $student = $params['user'];
                    // User must be a student
                    if (!$student->getRole()->isStudent()) {
                        throw new InvalidPath("User must be a student. Found '{$student->getRole()->getType()}'");
                    }
                    try {
                        $attributes = new StudentAttributes($this->getConfig(), $student, null, $params['reportDate'] ?? null);
                    } catch (InvalidArgumentException $e) {
                        throw new InvalidPath("Student attributes appear invalid", 0, $e);
                    }
                    $userYear = $attributes->getYearLevel();
                    if ($userYear < 7 || $userYear > 12) {
                        throw new InvalidPath("User is not in years 7 to 12, found '$userYear'");
                    }

                    /** @var Folder\Model $folder */
                    $folder = $params['folder'];
                    // Folder must exist in SB (empty folder models are created for subjects in the SIS but not in SB)
                    if (null === $folder->getId()) {
                        throw new InvalidPath("Folder does not exist in SB");
                    }

                    // User must be enrolled in the specified folder and not excluded
                    /** @var User $user */
                    $user = $params['user'];
                    if (!$this->isUserEnrolledInFolder($user, $currentUser, $folder, $params['reportDate'] ?? null)) {
                        throw new InvalidPath("User {$user->getId()} is not enrolled in folder {$folder->getId()}");
                    }
                }),
                new Rule\Any(
                    new Rule\IsSuperuser(),
                    new Rule\HasRole(Role::STAFF),
                    new Rule\All(
                        new Rule\Callback(function () {
                            if (!$this->getConfig()->get(Config::_TIGS_SUBJECT_REPORT_ENABLED)) {
                                throw new Alaress\Authorizer\Failure\Unauthorized("Subject Report is disabled");
                            }
                        }),
                        new Rule\IsUserOrParentOfUser($this->getExternalUser(), $this->getUserMapper(), 'userId')
                    )
                )
            );
        });
    }


    /**
     * Returns whether a user is enrolled in a class folder by examining external SIS class membership
     *
     * @param User         $user
     * @param User         $currentUser
     * @param Folder\Model $folder
     * @param string|null  $reportDate
     * @return bool
     * @throws InvalidArgumentException
     * @throws InvalidPath
     * @throws UnexpectedValueException
     * @throws \DomainException
     * @throws \PDOException
     */
    private function isUserEnrolledInFolder(User $user, User $currentUser, Folder\Model $folder, ?string $reportDate): bool
    {
        try {
            $attributes = new StudentAttributes($this->getConfig(), $user, null, $reportDate);
        } catch (InvalidArgumentException $e) {
            throw new InvalidPath("Cannot parse 'reportDate' url fragment as date time. Found: {$reportDate}", 0, $e);
        }

        $fetcher = new FolderFetcher($this->getFolderMapper(), $this->getExternalUser());

        /* I guess Role Type is null for this check; it should be derived from $currentUser's roletype anyway
         */
        $folders = $fetcher->findForStudent($user, $attributes, $reportDate, $this->getConfig()->get(Config::_TIGS_EXCLUDED_CLASS_CODES), $currentUser, null);

        /** @var Folder\Model $involvedFolder */
        foreach ($folders as $involvedFolder) {
            if ($involvedFolder->getId() === $folder->getId()) {
                return true;
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
