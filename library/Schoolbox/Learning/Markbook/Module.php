<?php
/**
 * @author    Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
namespace Schoolbox\Learning\Markbook;

use Alaress\Authorizer\Rule;
use Alaress\Module\Module as BaseModule;
use Schoolbox\Application\Exception\NotFound;
use Schoolbox\Mapper;
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

class Module extends BaseModule
{
    /**
     * @inheritdoc
     */
    protected $basename = 'learningMarkbook';

    /**
     * @inheritdoc
     */
    public function registerRoutes()
    {
        $preload = function ($params) {
            $extraParams = [];
            try {
                $extraParams['class'] = $this->getFolderMapper()->findById($params['classId']);
            } catch (MapperException $e) {
                throw new NotFound("Class does not exist");
            }
            if ($params['termCode']) {
                $extraParams['term'] = $this->getTermMapper()->findOneByColumn('code', $params['termCode']);
            }
            return $extraParams;
        };

        $isStaffOrSuper = new Rule\Any(
            new Rule\HasRole(Role::STAFF),
            new Rule\IsSuperuser()
        );

        $this->register('GET', '/{classId}', 'getMarkbook', $isStaffOrSuper, $preload);
        $this->register('GET', '/{classId}/term/{termCode}', 'getMarkbookForTerm', $isStaffOrSuper, $preload);
        $this->register('GET', '/{classId}/term/{termCode}/export', 'getMarkbookExport', $isStaffOrSuper, $preload);
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
     */
    public function initControllers(ContainerInterface $injector)
    {
        $injector->set(
            'controller:' . $this->basename,
            $injector->lazyNew(Controller::class, [
                'errorMapper'                => $this->getMapperFactory()->getLazy(ErrorMapper::class),
                'defaultGradeGroup'          => $this->getInjector()->get('defaultGradeGroup'),
                'assessmentMapperProxy'      => $injector->lazyGet('assessmentDataSource'),
                'courseImplementationMapper' => $this->getMapperFactory()->getLazy(CourseImplementationMapper::class),
                'termMapper'                 => $this->getMapperFactory()->getLazy(TermMapper::class),
                'dueWorkErrorMapper'         => $this->getMapperFactory()->getLazy(DueWork_ErrorMapper::class),
            ])
        );
    }

    /* Getters / Setters
     */

    /**
     * @return Mapper|UserMapper
     */
    protected function getUserMapper()
    {
        return $this->getMapperFactory()->get(UserMapper::class);
    }

    /**
     * @return Mapper|FolderMapper
     */
    protected function getFolderMapper()
    {
        return $this->getMapperFactory()->get(FolderMapper::class);
    }

    /**
     * @return Mapper|TermMapper
     */
    protected function getTermMapper()
    {
        return $this->getMapperFactory()->get(TermMapper::class);
    }

    /**
     * @return Mapper|ErrorMapper
     */
    protected function getErrorMapper()
    {
        return $this->getMapperFactory()->get(ErrorMapper::class);
    }
}
