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

use Alaress\Module\Module as BaseModule;
use Alaress\Authorizer\Rule;
use Aura\Di\ContainerInterface;
use Aura\Router\Router;
use Alaress\Web\Router\PathMatcher;
use Twig_Loader_Chain;

use Group;
use Schoolbox\Collection;
use Schoolbox\Mapper\Exception;
use Schoolbox\Assessment\Course\UnitInstanceMapper;
use Schoolbox\Assessment\Term\Mapper as TermMapper;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;

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
        $get = [

            /**
             * For a Course - aggregating all Folders which use this Course
             */

            /* folderId is associated with a course, a set of units. This finds all
             * the units associated with all the folders that use the course.
             *
             * Extra args can focus the results on all Tasks within a Course; and
             * also further focus on a particular time range via Term args
             */
            'course'                 => "/course/{courseId}",
            'courseWithTerms'        => "/course/{courseId}/term/{term}",
            'courseWithTask'         => "/course/{courseId}/task/{taskId}",
            'courseWithTaskAndTerms' => "/course/{courseId}/task/{taskId}/term/{term}",

            /**
             * Per Folder that uses a particular Course
             */

            /* folderId is associated with a course, a set of units. This finds all
             * the units associated with a particular folder that uses the course.
             *
             * Extra args can focus the results on all Tasks within a Folder; and
             * also further focus on a particular time range via Term args
             */
            'folder'                 => "/course/{courseId}/folder/{folderId}",
            'folderWithTerms'        => "/course/{courseId}/folder/{folderId}/term/{term}",
            'folderWithTask'         => "/course/{courseId}/folder/{folderId}/task/{taskId}",
            'folderWithTaskAndTerms' => "/course/{courseId}/folder/{folderId}/task/{taskId}/term/{term}",
        ];

        foreach ($get as $method => $route) {
            $this->addGetRoute($router, $method, $basePath, $route);
        }
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
        $this->addRoutePreload(
            $matcher,
            '(?:course|courseWithTerms|courseWithTask|courseWithTaskAndTerms)',
            function($params) {
                return $this->courseDependencies($params);
            }
        );

        /* Inject ingredients into folder controller method
         */
        $this->addRoutePreload(
            $matcher,
            '(?:folder|folderWithTerms|folderWithTask|folderWithTaskAndTerms)',
            function($params) {
                return $this->folderDependencies($params);
            }
        );
    }

    /**
     * Pass params when using course view
     *
     * @param $params
     * @return array
     * @throws Exception
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
        $termMapper = new TermMapper();

        /* Try to find the parent course folder which spawned minions
         */
        try {
            $courseFolder = $folderMapper->findById($params['courseId']);
        }
        catch (\Exception $e) {
            error(148);
        }

        /* Try to find all the folders which used $courseFolder as a source
         *
         * If a date range was specified, use that.
         */
        if (isset($params['term'])) {
            try {
                $term = $termMapper->findOneByColumn('code', $params['term']);

                $from = $term->getStart();
                $to = $term->getActualFinish();
            }
            catch (\Exception $e) {
                throw new Exception('Invalid date range.');
            }
        }
        else {
            $term = $termMapper->createModel();

            $from = $termMapper->findOldest()->getStart();
            $to   = \Alaress_Date_Format::now();
        }

        $allFoldersUsingCourse = $unitInstanceMapper->findAllFoldersUsingCourse($courseFolder, $from, $to);

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
            'allUnitInstances' => $allUnitInstances,
            'term' => $term,
//            'from' => $from,
//            'to' => $to
        );
    }

    /**
     * Pass params when using folder view
     *
     * @param $params
     * @return array
     * @throws Exception
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
        $termMapper = new TermMapper();

        /* Try to find the parent course folder which spawned minions
         */
        try {
            $courseFolder = $folderMapper->findById($params['courseId']);
        }
        catch (\Exception $e) {
            error(148);
        }

        /* Try to find all the folders which used $courseFolder as a source
         *
         * If a date range was specified, use that.
         */
        if (isset($params['term'])) {
            try {
                $term = $termMapper->findOneByColumn('code', $params['term']);

                $from = $term->getStart();
                $to = $term->getActualFinish();
            }
            catch (\Exception $e) {
                throw new Exception('Invalid date range.');
            }
        }
        else {
            $term = $termMapper->createModel();

            $from = $termMapper->findOldest()->getStart();
            $to   = \Alaress_Date_Format::now();
        }

        $allFoldersUsingCourse = $unitInstanceMapper->findAllFoldersUsingCourse($courseFolder, $from, $to);

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
            'allUnitInstances' => $allUnitInstances,
            'term' => $term,
//            'from' => $from,
//            'to' => $to
        );
    }

    /**
     * Template locations for this module
     *
     * {@inheritdoc}
     */
    public function initTemplatePaths(Twig_Loader_Chain $templateLoader)
    {
        $this->addTemplatePath($templateLoader, __DIR__ . '/templates');
    }

    /**
     * Set up controllers
     *
     * {@inheritdoc}
     */
    public function initControllers(ContainerInterface $injector)
    {
        $injector->set(
            'controller:'.$this->basename,
            $injector->lazyNew('Schoolbox\\LearningAnalytics\\Controller')
        );
    }
}