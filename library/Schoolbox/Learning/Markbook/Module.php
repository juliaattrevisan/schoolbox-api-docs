<?php
/**
 * @author    Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
namespace Schoolbox\Learning\Markbook;

use Alaress\Authorizer\Rule;
use Alaress\Module\Module as BaseModule;
use Aura\Di\Exception\ServiceNotFound;
use Schoolbox\Application\Exception\NotFound;
use Schoolbox\Filter\IdFinder;
use Schoolbox\Mapper;
use Schoolbox\Permission\Access\Access;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Aura\Di\ContainerInterface;
use Schoolbox\Role;
use Twig_Loader_Chain;
use Schoolbox\Learning\Course\DataModel\Implementation\Mapper as CourseImplementationMapper;
use Schoolbox\Assessment\Term\Mapper as TermMapper;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\UserMapper;
use Schoolbox\Error\Mapper as ErrorMapper;
use DueWork_ErrorMapper;
use Schoolbox\Filter;
use \Schoolbox\Learning\Assessment\DataModel as Assessment;
use Schoolbox\Filter\Filters\Assessment as AssessmentFilter;


class Module extends BaseModule
{
    protected $basename = 'learningMarkbook';

    /**
     * @inheritdoc
     * @throws \InvalidArgumentException
     * @throws NotFound If a class was specified but it does not exist
     */
    public function registerRoutes()
    {
        $preload = function ($params) {
            $assessmentMapper = $this->getMapperFactory()->get(Assessment\Mapper::class);
            $args = [
                'filterFactoryFactory' => new AssessmentFilter\FactoryFactory($this->getMapperFactory()),
                'assessmentIdFinder'   => AssessmentFilter\IdFinder::getInstance($assessmentMapper),
            ];

            if(null !== $params['userId']){
                try{
                    $args['viewee'] = $this->getUserMapper()->findById($params['userId']);
                } catch (Mapper\Exception $e){
                    throw new NotFound("No user found for id: {$params['userId']}");
                }
            }
            return $args;
        };

        $isStaffOrSuper = new Rule\Any(
            new Rule\HasRole(Role::STAFF),
            new Rule\IsSuperuser()
        );

        $this->register('GET',  '/class/{folderId:\d+}{/userId}', 'getMarkbookForClass', $isStaffOrSuper, $preload);

        $this->register('GET',  '{/userId}',       'getMarkbook',       $isStaffOrSuper, $preload);
        $this->register('POST', '{/userId}',       'postMarkbook',       $isStaffOrSuper, $preload);  //AJAX in the report
        $this->register('GET', '/export', 'getMarkbookExport', $isStaffOrSuper, $preload);
    }

    /**
     * @inheritdoc
     */
    public function initTemplatePaths(Twig_Loader_Chain $templateLoader)
    {
        $this->addTemplatePath($templateLoader, __DIR__ . '/templates');
    }

    /**
     * @inheritdoc
     * @throws \InvalidArgumentException If class not found
     * @throws ServiceNotFound
     */
    public function initControllers(ContainerInterface $injector)
    {
        $injector->set(
            'controller:' . $this->getBasename(),
            $injector->lazyNew(Controller::class, [
                'errorMapper'                => $this->getMapperFactory()->getLazy(ErrorMapper::class),
                'defaultGradeGroup'          => $this->getInjector()->get('defaultGradeGroup'),
                'assessmentMapperProxy'      => $injector->lazyGet('assessmentDataSource'),
                'courseImplementationMapper' => $this->getMapperFactory()->getLazy(CourseImplementationMapper::class),
                'termMapper'                 => $this->getMapperFactory()->getLazy(TermMapper::class),
                'dueWorkErrorMapper'         => $this->getMapperFactory()->getLazy(DueWork_ErrorMapper::class),
                'access'                     => $this->getMapperFactory()->getLazy(Access::class), // Has a static 'getInstance()' method
            ])
        );
    }

    /* Getters / Setters
     */

    /**
     * @return Mapper|UserMapper
     * @throws \InvalidArgumentException If class not found
     */
    protected function getUserMapper()
    {
        return $this->getMapperFactory()->get(UserMapper::class);
    }

    /**
     * @return Mapper|FolderMapper
     * @throws \InvalidArgumentException If class not found
     */
    protected function getFolderMapper()
    {
        return $this->getMapperFactory()->get(FolderMapper::class);
    }

    /**
     * @return Mapper|TermMapper
     * @throws \InvalidArgumentException If class not found
     */
    protected function getTermMapper()
    {
        return $this->getMapperFactory()->get(TermMapper::class);
    }

    /**
     * @return Mapper|ErrorMapper
     * @throws \InvalidArgumentException If class not found
     */
    protected function getErrorMapper()
    {
        return $this->getMapperFactory()->get(ErrorMapper::class);
    }
}
