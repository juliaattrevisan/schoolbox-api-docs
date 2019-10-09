<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */
namespace Schoolbox\Learning\Upcoming;

use Alaress\Authorizer\Rule;
use Alaress_Date_Format;
use Alaress\Module\Module as BaseModule;
use Aura\Di\Container;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\L10n\RosettaStone;
use Schoolbox\Permission\Access\Access;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Aura\Di\ContainerInterface;
use Schoolbox\Role;
use Alaress\Twig\Loader\IChainLoader;
use Schoolbox\Assessment\Term\Mapper as TermMapper;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\UserMapper;
use Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\Filter;
use Schoolbox\Learning\Assessment\DataModel as Assessment;

/**
 * Sub-module for a student's upcoming due work.
 */
class Module extends BaseModule
{
    const BASENAME = 'learningUpcoming';
    /**
     * @inheritdoc
     */
    protected $basename = self::BASENAME;


    /**
     * @inheritdoc
     * @throws \InvalidArgumentException
     */
    public function registerRoutes()
    {
        // Permissions:
        // All users may view their own work
        // staff may view anyone's due work
        $access = new Rule\Any(
            new Rule\IsUser('studentId'),
            new Rule\HasRole(Role::STAFF)
        );
        // parents may view their child's work only if config.restrict_parent_duework_access is off
        if (!$this->getConfigMapper()->get('restrict_parent_duework_access')) {
            $access->addRule( new Rule\LazyCallback(function () {
                return new Rule\IsParentOfUser($this->getExternalUser(), $this->getUserMapper(), 'studentId');
            }));
        }
        $access = new Rule\All(
            new Rule\IsLoggedIn(),
            $access
        );

        $preloadStudent = function (array $params) {
            if (\array_key_exists('student', $params)) {
                $student = $params['student'];
            } else {
                try {
                    $student = $this->getUserMapper()->findById($params['studentId']);
                } catch (MapperException $e) {
                    throw $this->getErrorMapper()->generateException(ErrorMapper::ERROR_USER_NOT_FOUND);
                }
            }
            return ['student' => $student,];
        };

        $preloadStudentAndDate = function (array $params) use ($preloadStudent) {

            $preload = $preloadStudent($params);

            // Build a date
            if (isset($params['date'])) {
                try {
                    $date = Alaress_Date_Format::newUrlDate($params['date']);
                } catch (\Exception $e) {
                    // noop
                }
            }
            if (!isset($date)) {
                $date = Alaress_Date_Format::now();
            }
            $preload['date'] = $date;
            return $preload;
        };

        $this->register('GET',  '',                        'getUpcoming', new Rule\IsLoggedIn());
        $this->register('GET',  '/events/{studentId:\d+}', 'getEvents', $access, $preloadStudent);
        $this->register('GET',  '/{studentId:\d+}{/date}', 'getUpcomingForStudent',  $access, $preloadStudentAndDate);
        $this->register('POST', '/{studentId:\d+}',        'postUpcomingForStudent', $access, $preloadStudent);
    }

    /**
     * @inheritdoc
     */
    public function initTemplatePaths(IChainLoader $templateLoader)
    {
        $this->addTemplatePath($templateLoader, __DIR__ . '/templates');
    }


    /**
     * Override with any steps required for a controller and its routes
     *
     * You should use this method to initialize any mappers or other objects
     * which the controller needs, but are too heavy to initialize at module
     * init time.
     *
     * @param Container|ContainerInterface $injector The dependency injection container.
     * @throws \InvalidArgumentException
     */
    protected function preInitControllerDependencies(ContainerInterface $injector)
    {
        $injector->params[Controller::class] = [
            'userMapper'            => $this->getUserMapper(),
            'folderMapper'          => $this->getMapperFactory()->getLazy(FolderMapper::class),
            'termMapper'            => $this->getMapperFactory()->getLazy(TermMapper::class),
            'externalClass'         => $this->getInjector()->lazyGet('externalClass'),
            'assessmentDataSource'  => $injector->lazyGet('assessmentDataSource'),
            'participantDataSource' => $injector->lazyGet('participantDataSource'),
            'defaultGradeGroup'     => $this->getInjector()->lazyGet('defaultGradeGroup'),
            'filterFactoryFactory'  => $injector->lazyGet('filters.factoryFactory'),
            'assessmentIdFinder'    => $injector->lazy(function () {
                /** @var Filter\Mapper $filterMapper */
                $filterMapper = $this->getMapperFactory()->get(Filter\Mapper::class);
                return Filter\Filters\Assessment\IdFinder::getInstance(
                    $filterMapper, $this->getMapperFactory()->get(Assessment\Mapper::class)
                );
            }),
            'filterSelectionMapper' => $this->getMapperFactory()->getLazy(Filter\Selection\Mapper::class),
            'access'                => $this->getMapperFactory()->getLazy(Access::class),
        ];
    }


    /**
     * @inheritdoc
     */
    public function initControllers(ContainerInterface $injector)
    {
        $injector->set('controller:' . $this->basename, $injector->lazyNew(Controller::class));
    }

    /* Getters / Setters
     */

    /**
     * @return UserMapper|\Schoolbox\Mapper
     * @throws \InvalidArgumentException
     */
    private function getUserMapper()
    {
        return $this->getMapperFactory()->get(UserMapper::class);
    }

    /**
     * @return ConfigMapper|\Schoolbox\Mapper
     * @throws \InvalidArgumentException
     */
    private function getConfigMapper()
    {
        return $this->getMapperFactory()->get(ConfigMapper::class);
    }


    /**
     * @return ErrorMapper|\Schoolbox\Mapper
     * @throws \InvalidArgumentException
     */
    private function getErrorMapper()
    {
        return $this->getMapperFactory()->get(ErrorMapper::class);
    }
}
