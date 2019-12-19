<?php
/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Custom\Diocesan\AchievementReport;

use Alaress\Authorizer\Rule;
use Alaress\Module\Module as BaseModule;
use Alaress\Twig\Loader\IChainLoader;
use Aura\Di\Container;
use Aura\Di\ContainerInterface;
use InvalidArgumentException;
use Schoolbox\Application\Exception\NotFound;
use Schoolbox\Mapper;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Role;
use Schoolbox\UserMapper;

/**
 * Class Module
 *
 * @package Custom\Diocesan\AchievementReport
 */
class Module extends BaseModule
{

    /**
     * @inheritdoc
     */
    protected $basename = 'achievementreport';


    /**
     * Hook method for setting up routes via self::register()
     *
     * @return void
     * @throws InvalidArgumentException
     */
    protected function registerRoutes(): void
    {
        /* Anyone can see the form
         */
        $getPerms = new Rule\All(
            new Rule\IsLoggedIn(),
            new Rule\Any(
                new Rule\IsSuperuser(),
                new Rule\HasRole(Role::STAFF),
                new Rule\IsExternalUser('externalUserId'),
                new Rule\IsParentOfExternalUser($this->getExternalUser(), $this->getUserMapper(),'externalUserId')
            )
        );

        $preload = function ($params) { return $this->preloadUser($params); };

        $this->register('GET', '/{externalUserId}', 'getSelector', $getPerms, $preload);
        $this->register('GET', '/schoolGrades/{externalUserId}{/year}', 'getSchoolGrades', $getPerms, $preload);
        $this->register('GET', '/ib/{externalUserId}{/year}', 'getIb', $getPerms, $preload);
        $this->register('GET', '/ncea/{externalUserId}{/year}', 'getNcea', $getPerms, $preload);
        $this->register('GET', '/comments/{externalUserId}{/year}', 'getComments', $getPerms, $preload);
        $this->register('GET', '/standardisedTests/PAT/{externalUserId}{/year}', 'getStandardisedTestsPAT', $getPerms, $preload);
        $this->register('GET', '/standardisedTests/STAR/{externalUserId}{/year}', 'getStandardisedTestsSTAR', $getPerms, $preload);
        $this->register('GET', '/standardisedTests/reading/{externalUserId}{/year}', 'getStandardisedTestsReading', $getPerms, $preload);
        $this->register('GET', '/standardisedTests/writing/{externalUserId}{/year}', 'getStandardisedTestsWriting', $getPerms, $preload);
        $this->register('GET', '/standardisedTests/mathematics/{externalUserId}{/year}', 'getStandardisedTestsMathematics', $getPerms, $preload);
        $this->register('GET', '/standardisedTests/literacynumeracy/{externalUserId}{/year}', 'getStandardisedTestsLiteracyNumeracy', $getPerms, $preload);
        $this->register('GET', '/standardisedTests/{externalUserId}{/year}', 'getStandardisedTests', $getPerms, $preload);
        $this->register('GET', '/coCurricular/{externalUserId}{/year}', 'getCoCurricular', $getPerms, $preload);
    }


    /**
     * Preload a User instance
     *
     * @param array $params
     * @return array
     * @throws InvalidArgumentException
     * @throws NotFound
     */
    private function preloadUser(array $params): array
    {
        try {

            /* Check that the selected user exists in external db
             */
            $externalUser = $this->getExternalUser()->getStudent($params['externalUserId']);

            /* Check that the selected user exists in schoolbox
             */
            $user = $this->getUserMapper()->findByExternalId($params['externalUserId'], Role::STUDENT);
        } catch (MapperException $e) {

            /* If they weren't found in either, exit
             */
            throw new NotFound();
        }

        /* Preload data structure goes into perms chain
         * Pass the $user->getId() value to reuse IsUserOrParentOfUser() auth rule
         */
        return [
            'id' => $user->getId(),
            'db' => \MSSQL::getInstance(),
            'externalUser' => $externalUser,
            'user' => $user
        ];
    }


    /**
     * @inheritdoc
     */
    public function initControllers(ContainerInterface $injector): void
    {
        $injector->set('controller:' . $this->basename, $injector->lazyNew(Controller::class));
    }


    /**
     * {@inheritdoc}
     */
    public function initTemplatePaths(IChainLoader $templateLoader): void
    {
        $this->addTemplatePath($templateLoader, __DIR__ . '/SchoolGrades', 'schoolGrades');
        $this->addTemplatePath($templateLoader, __DIR__ . '/IB', 'ib');
        $this->addTemplatePath($templateLoader, __DIR__ . '/NCEA', 'ncea');
        $this->addTemplatePath($templateLoader, __DIR__ . '/Comments', 'comments');
        $this->addTemplatePath($templateLoader, __DIR__ . '/StandardisedTest', 'standardisedTest');
        $this->addTemplatePath($templateLoader, __DIR__ . '/CoCurricular', 'coCurricular');
        $this->addTemplatePath($templateLoader, __DIR__ . '/Templates', 'achievementreport');
    }


    /**
     * Override with any steps required for a controller and its routes
     *
     * You should use this method to initialize any mappers or other objects
     * which the controller needs, but are too heavy to initialize at module
     * init time.
     *
     * @param Container|ContainerInterface $injector The dependency injection container.
     * @throws InvalidArgumentException
     */
    protected function preInitControllerDependencies(ContainerInterface $injector): void
    {
        /** @var Container $injector */
        $injector->params[Controller::class] = [
            'externalUser' => $injector->lazyGet('externalUser'),
            'userMapper' => $this->getMapperFactory()->getLazy(UserMapper::class)
        ];
    }


    /**
     * @return Mapper|UserMapper
     * @throws InvalidArgumentException If cannot find mapper class
     */
    protected function getUserMapper()
    {
        return $this->getMapperFactory()->get(UserMapper::class);
    }
}
