<?php
/**
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Custom\Loyola\Survey;

use Alaress\Authorizer\Authorizer;
use Alaress\Authorizer\Rule;
use Alaress\Module\Module as BaseModule;
use Alaress\Web\Router\PathMatcher;
use Aura\Di\ContainerInterface;
use Schoolbox\Role;
use Schoolbox\UserMapper;
use Schoolbox\Survey\Mapper as SurveyMapper;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Alaress\Twig\Loader\IChainLoader;

class Module extends BaseModule
{
    /**
     * @var string $basename Module basename
     */
    protected $basename = 'surveySnapshot';

    /**
     * {@inheritdoc}
     */
    protected function registerRoutes()
    {
        $isStaff = new Rule\HasRole(Role::STAFF);

        $this->register('GET', '/class/{folderId}', 'getClassSnapshotList', $isStaff);
        $this->register('GET', '/class/snapshot/{surveyId}', 'getClassSnapshot', $isStaff);
    }

    /**
     * {@inheritdoc}
     */
    public function initRoutePermissions(Authorizer $authorizer)
    {
        $this->addRoutePermission($authorizer, '.*', new Rule\IsLoggedIn());
    }

    /**
     * @param PathMatcher $matcher
     */
    public function initRoutePreloads(PathMatcher $matcher)
    {
        $this->addRoutePreload($matcher, '.*', function ($params) {

            $preloads = [];

            if (isset($params['surveyId'])) {
                $preloads['survey'] = $this->getMapperFactory()->get(SurveyMapper::class)->findById($params['surveyId']);
            }

            if (isset($params['folderId'])) {
                $preloads['folder'] = $this->getMapperFactory()->get(FolderMapper::class)->findById($params['folderId']);
            }

            return $preloads;
        });
    }

    /**
     * {@inheritdoc}
     */
    public function initControllers(ContainerInterface $injector)
    {
        $mappers = [
            'userMapper' => UserMapper::getInstance(),
            'folderMapper' => FolderMapper::getInstance(),
            'surveyMapper' => SurveyMapper::getInstance(),
        ];

        $injector->set('controller:' . $this->basename, $injector->lazyNew(Controller::class, $mappers));
    }

    /**
     * {@inheritdoc}
     */
    public function initTemplatePaths(IChainLoader $templateLoader)
    {
        $this->addTemplatePath($templateLoader, __DIR__ . '/templates');
    }
}
