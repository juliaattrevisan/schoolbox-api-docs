<?php
/**
 * Controller
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\LearningAnalytics;

use Alaress\Web\Controller\Base;

use Schoolbox\Resource\Folder\Model as Folder;
use Schoolbox\Collection;
use Schoolbox\LearningAnalytics\Progress\Overall;
use Schoolbox\LearningAnalytics\Progress\AllUnits as AllUnitProgress;
use Schoolbox\LearningAnalytics\Results\Averages;
use Schoolbox\LearningAnalytics\Results\TaskList;
use Schoolbox\LearningAnalytics\Results\TaskResults;
use Schoolbox\LearningAnalytics\Results\TaskTypes;
use Schoolbox\LearningAnalytics\Rubrics\AllRubrics;
use Schoolbox\LearningAnalytics\Effort\AllEffort;
use Schoolbox\Assessment\Term\Model as Term;
use Schoolbox\Assessment\Term\Mapper as TermMapper;
use Schoolbox\Assessment\StudentReportCard\ContextSelector;

class Controller extends Base {

    /**
     * Same calculations as process(), just for a different set of units
     *
     * @param array $params
     * @param Folder $courseFolder
     * @param Collection $allFoldersUsingCourse
     * @param Collection $allUnitInstances
     * @param Term $term
     */
    public function course($params, Folder $courseFolder, Collection $allFoldersUsingCourse, Collection $allUnitInstances, Term $term) {
        $this->process(
            $allUnitInstances,
            new Metadata($params, $courseFolder, $allFoldersUsingCourse, $allUnitInstances, $term)
        );
    }

    /**
     * Same calculations as process(), just for a different set of units
     *
     * @param array $params
     * @param Folder $courseFolder
     * @param Collection $allFoldersUsingCourse
     * @param Collection $allUnitInstances
     * @param Term $term
     */
    public function courseWithTerms($params, Folder $courseFolder, Collection $allFoldersUsingCourse, Collection $allUnitInstances, Term $term) {
        $this->process(
            $allUnitInstances,
            new Metadata($params, $courseFolder, $allFoldersUsingCourse, $allUnitInstances, $term)
        );
    }

    /**
     * Same calculations as process(), just for a different set of units
     *
     * @param array $params
     * @param Folder $courseFolder
     * @param Collection $allFoldersUsingCourse
     * @param Collection $allUnitInstances
     * @param Term $term
     */
    public function courseWithTask($params, Folder $courseFolder, Collection $allFoldersUsingCourse, Collection $allUnitInstances, Term $term) {
        $this->process(
            $allUnitInstances,
            new Metadata($params, $courseFolder, $allFoldersUsingCourse, $allUnitInstances, $term)
        );
    }

    /**
     * Same calculations as process(), just for a different set of units
     *
     * @param array $params
     * @param Folder $courseFolder
     * @param Collection $allFoldersUsingCourse
     * @param Collection $allUnitInstances
     * @param Term $term
     */
    public function courseWithTaskAndTerms($params, Folder $courseFolder, Collection $allFoldersUsingCourse, Collection $allUnitInstances, Term $term) {
        $this->process(
            $allUnitInstances,
            new Metadata($params, $courseFolder, $allFoldersUsingCourse, $allUnitInstances, $term)
        );
    }

    /**
     * Same calculations as process(), just for a different set of units
     *
     * @param array $params
     * @param Folder $courseFolder
     * @param Collection $allFoldersUsingCourse
     * @param Collection $allUnitInstances
     * @param Term $term
     */
    public function folder($params, Folder $courseFolder, Collection $allFoldersUsingCourse, Collection $allUnitInstances, Term $term) {
        $this->process(
            $allUnitInstances,
            new Metadata($params, $courseFolder, $allFoldersUsingCourse, $allUnitInstances, $term)
        );
    }

    /**
     * Same calculations as process(), just for a different set of units
     *
     * @param array $params
     * @param Folder $courseFolder
     * @param Collection $allFoldersUsingCourse
     * @param Collection $allUnitInstances
     * @param Term $term
     */
    public function folderWithTerms($params, Folder $courseFolder, Collection $allFoldersUsingCourse, Collection $allUnitInstances, Term $term) {
        $this->process(
            $allUnitInstances,
            new Metadata($params, $courseFolder, $allFoldersUsingCourse, $allUnitInstances, $term)
        );
    }

    /**
     * Same calculations as process(), just for a different set of units
     *
     * @param array $params
     * @param Folder $courseFolder
     * @param Collection $allFoldersUsingCourse
     * @param Collection $allUnitInstances
     * @param Term $term
     */
    public function folderWithTask($params, Folder $courseFolder, Collection $allFoldersUsingCourse, Collection $allUnitInstances, Term $term) {
        $this->process(
            $allUnitInstances,
            new Metadata($params, $courseFolder, $allFoldersUsingCourse, $allUnitInstances, $term)
        );
    }

    /**
     * Same calculations as process(), just for a different set of units
     *
     * @param array $params
     * @param Folder $courseFolder
     * @param Collection $allFoldersUsingCourse
     * @param Collection $allUnitInstances
     * @param Term $term
     */
    public function folderWithTaskAndTerms($params, Folder $courseFolder, Collection $allFoldersUsingCourse, Collection $allUnitInstances, Term $term) {
        $this->process(
            $allUnitInstances,
            new Metadata($params, $courseFolder, $allFoldersUsingCourse, $allUnitInstances, $term)
        );
    }

    /**
     * Generate a context selector phplib fragment
     *
     * @param Term $term
     * @return string
     * @throws \Aura\Di\Exception\ServiceNotFound
     * @throws \Aura\Web\Exception\InvalidComponent
     */
    private function generateContextSelector(Term $term)
    {
        /* Url Acrobatics, since the contextselector just appends args continually
         *
         * Trim the leading slash
         * Replace any /term/* args that were previously chosen from contextselector
         * Append /term, since the contextselector term value is something like 2016-1
         */
        $currentUrl = ltrim($this->getRequest()->url->get(PHP_URL_PATH), '/');
        $currentUrl = preg_replace('/\/term\/(.*)/', '', $currentUrl);
        $currentUrl .= '/term';

        /* Construct it in phplib sigh
         */
        $termMapper = new TermMapper();
        $contextSelector = new ContextSelector($currentUrl);
        $contextSelector->setTerms($termMapper->findAll());
        return $contextSelector->renderAsString($this->getDi()->get('sitePage'), ['term' => $term]);
    }

    /**
     * Calculate all data about $allUnitInstances
     *
     * $allUnitInstances will be:
     *
     * + either all the units relevant to the course belonging to the
     * "learningAnalytics/$param['courseId']" route
     *
     * + or all the units relevant to a particular folder in the
     * "learningAnalytics/$param['courseId']/$param['folderId']" route
     *
     * Both are processed identically as data is grouped based on task
     * instances' originating task id, not the individual instance id
     *
     * @param Collection $allUnitInstances
     * @param Metadata $metadata
     */
    private function process(Collection $allUnitInstances, Metadata $metadata) {

        /* Get a list of all the students involved in each unit
         * and a list of units involved. This gets reused everywhere
         * so just hit the external db once
         */
        $registry = new Registry($allUnitInstances);

        /**
         * Overall Progress
         */
        $overallProgress = new Overall($registry);

        /**
         * Results for all the units in each $folder contained in $allFolders
         * (generated by Registry)
         */
        $taskList = new TaskList($registry, $metadata);
        $taskResults = new TaskResults($registry);
        $taskTypes = new TaskTypes($metadata);

        /**
         * Grade Averages for student rankings
         */
        $allAverages = new Averages($taskResults->getAllResults());

        /**
         * Individual Unit Progress for all the units in each $folder
         * contained in $allFolders (generated by Registry)
         */
        $stackedUnitProgress = new AllUnitProgress($registry);

        /**
         * Rubrics
         */
        $allRubrics = new AllRubrics($registry, $metadata);

        /**
         * Effort
         *
         * A metric based on Homepage accesses to grade average
         */
        $allEffort = new AllEffort($registry, $metadata, $allAverages);

        /**
         * Context Selector is a phplib -_-
         */
        $contextSelectorHtml = $this->generateContextSelector($metadata->getTerm());

        /* Twig data
         */
        $this->getResponse()->content->set([
            'template' => '@learningAnalytics/course.twig',
            'data' => [
                'contextSelector' => $contextSelectorHtml,
                'metadata' => [
                    'pageTitle' => $metadata->getPageTitle(),
                    'route' => $metadata->getRoute(),
                    'folder' => [
                        'all' => $metadata->getAllFolders(),
                        'course' => $metadata->getCourseFolder(),
                        'selected' => $metadata->getSelectedFolder(),
                    ],
                ],
                'overallProgress' => [
                    'mostAdvancedStudent' => $overallProgress->getMostAdvancedStudent(),
                    'classCompletions' => $overallProgress->getClassCompletions(),
                ],
                'results' => [
                    'all' => $taskResults->getAllResults(),
                    'tasks' => [
                        'all' => $taskList->getAllTasks(),
                        'types' => [
                            'all' => $taskTypes->getAllTaskTypes(),
                            'selected' => $taskTypes->getSelectedTaskFilter(),
                        ],
                    ],
                    'ranking' => [
                        'top' => $allAverages->findDescending('average'),
                        'bottom' => $allAverages->findAscending('average'),
                    ],
                ],
                'progress' => [
                    'allUnits' => $stackedUnitProgress->getStackedUnitProgress(),
                ],
                'rubrics' => [
                    'allRubrics' => $allRubrics->getAllRubrics(),
                ],
                'effort' => [
                    'allEffort' => $allEffort->getTally(),
                ],
            ]
        ]);
    }
}