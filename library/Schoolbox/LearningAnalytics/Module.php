<?php
/**
 * Module
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\LearningAnalytics;

use Alaress\Authorizer\Authorizer;
use Alaress\Module\Module as BaseModule;
use Alaress\Authorizer\Rule;
use Aura\Di\ContainerInterface;
use Aura\Router\Router;
use Alaress\Web\Router\PathMatcher;
use Twig_Loader_Chain;
use Twig_Loader_Filesystem;
use Schoolbox\Permission\Access\UserAccess;

use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Schoolbox\Mapper\Exception;
use Schoolbox\Assessment\Course\UnitInstanceMapper;
use Group;
use Schoolbox\Collection;

class Module extends BaseModule
{
    protected $basename = 'learningAnalytics';

    /**
     * Basic routes for this module
     *
     * {@inheritdoc}
     */
    public function initRoutes(Router $router, $basePath)
    {
        // All of these URLs relate to the resource ID, not a booking ID.
        // Therefore, they should probably be moved to the item module.

        /* folderId is associated with a course, a set of units. This finds all
         * the units associated with all the folders that use the course.
         */
        $this->addGetRoute($router, 'course', $basePath, "/course/{courseId}");

        /* With optional task filter
         */
        $this->addGetRoute($router, 'courseWithTask', $basePath, "/course/{courseId}/task/{taskId}");

        /* folderId is associated with a course, a set of units. This finds all
         * the units associated with a particular folder that uses the course.
         */
        $this->addGetRoute($router, 'folder', $basePath, "/course/{courseId}/folder/{folderId}");

        /* With optional task filter
         */
        $this->addGetRoute($router, 'folderWithTask', $basePath, "/course/{courseId}/folder/{folderId}/task/{taskId}");
    }

    /**
     * Pass params when using course view
     *
     * @param $params
     * @return array
     */
    private function courseDependencies($params) {

        /* Check permissions
         */
        $group = new Group();

        if ($group->getPermission($params['courseId']) < WRITE_ACCESS) {
            error(6);
        }

        /* Load folders
         */
        $unitInstanceMapper = new UnitInstanceMapper();
        $folderMapper = new FolderMapper();
        $allUnitInstances = new Collection();

        /* Try to find the parent course folder which spawned minions
         */
        try {
            $courseFolder = $folderMapper->findById($params['courseId']);
        }
        catch (\Exception $e) {
            error(148);
        }

        /* Try to find all the folders which used $courseFolder as a source
         */
        $allFoldersUsingCourse = $unitInstanceMapper->findAllFoldersUsingCourse($courseFolder, date('Y'));

        /* Acrobatics
         */
        if ($allFoldersUsingCourse->count()) {
            try {

                /* All the unit instances associated with $params['folderId']
                 */
                $allCategoryIds = array();

                foreach ($allFoldersUsingCourse as $currentFolder) {
                    $allCategoryIds[] = $currentFolder->getId();
                }

                /* Uniquify
                 */
                $allCategoryIds = array_unique($allCategoryIds);

                /* All the units associated with folders which used the same
                 * $courseFolder as a source
                 */
                $allUnitInstances = $unitInstanceMapper->findByColumn('category_id', $allCategoryIds);
            }
            catch (Exception $e) {
                error(159);
            }
        }
        else {
            /* TODO: No folders using this course
             */
        }

        /* Inject (this is used in twig)
         */
        $params['route'] = 'course';

        return array(
            'params' => $params,
            'courseFolder' => $courseFolder,
            'allFoldersUsingCourse' => $allFoldersUsingCourse,
            'allUnitInstances' => $allUnitInstances
        );
    }

    /**
     * Pass params when using folder view
     *
     * @param $params
     * @return array
     */
    private function folderDependencies($params) {

        /* Check permissions
         */
        $group = new Group();

        if ($group->getPermission($params['folderId']) < WRITE_ACCESS) {
            error(6);
        }

        /* Load folders
         */
        $unitInstanceMapper = new UnitInstanceMapper();
        $folderMapper = new FolderMapper();
        $allUnitInstances = new Collection();

        /* Try to find the parent course folder which spawned minions
         */
        try {
            $courseFolder = $folderMapper->findById($params['courseId']);
        }
        catch (\Exception $e) {
            error(148);
        }

        /* Try to find all the folders which used $courseFolder as a source
         */
        $allFoldersUsingCourse = $unitInstanceMapper->findAllFoldersUsingCourse($courseFolder, date('Y'));

        /* No acrobatics required
         */
        try {
            $allUnitInstances = $unitInstanceMapper->findByColumn('category_id', $params['folderId']);
        }
        catch (Exception $e) {
            error(159);
        }

        /* Inject (this is used in twig)
         */
        $params['route'] = 'folder';

        return array(
            'params' => $params,
            'courseFolder' => $courseFolder,
            'allFoldersUsingCourse' => $allFoldersUsingCourse,
            'allUnitInstances' => $allUnitInstances
        );
    }

    /**
     * Inject params into controller
     *
     * {@inheritdoc}
     */
    public function initRoutePreloads(PathMatcher $matcher)
    {
        /* Inject ingredients into course controller method
         */
        $this->addRoutePreloadForBasename(
            $matcher,
            '(?:course|courseWithTask)',
            function($params) {
                return $this->courseDependencies($params);
            }
        );

        /* Inject ingredients into folder controller method
         */
        $this->addRoutePreloadForBasename(
            $matcher,
            '(?:folder|folderWithTask)',
            function($params) {
                return $this->folderDependencies($params);
            }
        );
    }

    /**
     * Template locations for this module
     *
     * {@inheritdoc}
     */
    public function initTemplatePaths(Twig_Loader_Chain $templateLoader)
    {
        $loader = new Twig_Loader_Filesystem(array());
        $loader->addPath(__DIR__.'/templates', 'learningAnalytics');
        $templateLoader->addLoader($loader);
    }

    /**
     * Set up controllers
     *
     * {@inheritdoc}
     */
    public function initControllers(ContainerInterface $injector)
    {
        $injector->set(
            'controller:learningAnalytics',
            $injector->lazyNew('Schoolbox\\LearningAnalytics\\Controller')
        );
    }
}