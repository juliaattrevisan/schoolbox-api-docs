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
        $isLoggedIn = new Rule\IsLoggedIn();

        $preloadUser = function ($params) {
            return $this->preloadUser($params);
        };

        $canSeeParticipation = $this->buildRuleCanViewParticipationReport();

        $this->register('GET', '/user/{userId}', 'getParticipationReport', $canSeeParticipation, $preloadUser);
        $this->register('GET', '/{userId}/folder/{folderId}', 'getSubjectReport', $isLoggedIn);
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
     * Returns an auth rule for viewing the Participation Report
     *
     * * Viewee must be in years 7-12
     * * Viewer must be SU or Staff, or student/parent and config enabled
     *
     * @return Rule\IAuthorizationRule
     */
    private function buildRuleCanViewParticipationReport(): Rule\IAuthorizationRule
    {
        return new Rule\LazyCallback(function () {
            return new Rule\All(
                new Rule\Callback(function (array $params) {
                    // Because preload exceptions are not handled immediately, we can't fatal if the preload failed.
                    if (!($params['user'] ?? null) instanceof User) {
                        throw new InvalidPath("No user found in route params");
                    }
                    $student  = $params['user'];
                    $userYear = $student->getYear();
                    if (!\is_numeric($userYear) || $userYear < 7 || $userYear > 12) {
                        throw new InvalidPath("User is not in years 7-12, found '$userYear'");
                    }
                }),
                new Rule\Any(
                    new Rule\IsSuperuser(),
                    new Rule\HasRole(Role::STAFF),
                    new Rule\All(
                        new Rule\Callback(function () {
                            if (!$this->getConfig()->get(Config::_TIGS_PARTICIPATION_REPORT_ENABLED)) {
                                throw new Alaress\Authorizer\Failure\Unauthorized('Participation report is disabled');
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
