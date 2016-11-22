<?php
/**
 * @author    Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
namespace Schoolbox\Learning\Markbook;

use Alaress\Authorizer\Authorizer;
use Alaress\Authorizer\Rule;
use Alaress\Module\Module as BaseModule;
use Schoolbox\Application\Exception\NotFound;
use Schoolbox\Permission\Access\Access;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Alaress\Web\Router\PathMatcher;
use Aura\Di\ContainerInterface;
use Aura\Router\Router;
use Twig_Loader_Chain;
use Schoolbox\Assessment\Component\SubmissionBox\GradeLevel\Group\Mapper as GradeGroupMapper;
use Schoolbox\Assessment\Term\Mapper as TermMapper;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\UserMapper;
use Schoolbox\Error\Mapper as ErrorMapper;

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
        // Assuming these are the correct permissions
        $access = new Rule\HasHomepageAccess(
            UserAccess::ACCESS_WRITE,
            function ($params) {
                return $params['class']->getId();
            },
            Access::getInstance()
        );

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

        $this->register('GET', '/{classId}', 'getMarkbook', $access, $preload);
        $this->register('GET', '/{classId}/term/{termCode}', 'getMarkbookForTerm', $access, $preload);
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
                'errorMapper'                    => $this->getMapperFactory()->getLazy(ErrorMapper::class),
                'defaultGradeGroup'              => $this->getInjector()->get('defaultGradeGroup'),
                'assessmentMapperProxy'          => $injector->lazyGet('assessmentDataSource'),
                'courseImplementationDataSource' => $injector->lazyGet('courseImplementationDataSource'),
                'termMapper'                     => $this->getMapperFactory()->getLazy(TermMapper::class),
            ])
        );
    }

    /* Getters / Setters
     */

    /**
     * @return UserMapper
     */
    protected function getUserMapper()
    {
        return $this->getMapperFactory()->get(UserMapper::class);
    }

    /**
     * @return FolderMapper
     */
    protected function getFolderMapper()
    {
        return $this->getMapperFactory()->get(FolderMapper::class);
    }

    /**
     * @return TermMapper
     */
    protected function getTermMapper()
    {
        return $this->getMapperFactory()->get(TermMapper::class);
    }

    /**
     * @return ErrorMapper
     */
    protected function getErrorMapper()
    {
        return $this->getMapperFactory()->get(ErrorMapper::class);
    }
}
