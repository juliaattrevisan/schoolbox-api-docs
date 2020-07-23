<?php
/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Filter;

use Alaress\Web\Controller\Base as BaseController;
use Aura\Di\Exception\ServiceNotFound;
use Schoolbox\RoleMapper;
use function count;
use Schoolbox\GenericCollection;
use Schoolbox\L10n\RosettaStone;
use Schoolbox\Role;
use Schoolbox\Learning\Assessment\DataModel as Assessment;


class Controller extends BaseController
{
    /** @var Mapper $filterMapper */
    private $filterMapper;

    /** @var Page\Mapper $pageMapper */
    private $pageMapper;

    /** @var Page\Option\Mapper $optionsMapper */
    private $optionsMapper;

    /** @var Page\Option\Defaults\Mapper $defaultsMapper */
    private $defaultsMapper;

    /** @var RosettaStone */
    private $rosettaStone;


    /**
     * @param Mapper                      $filterMapper
     * @param Page\Mapper                 $pageMapper
     * @param Page\Option\Mapper          $optionsMapper
     * @param Page\Option\Defaults\Mapper $defaultsMapper
     * @param RosettaStone                $rosettaStone
     */
    public function __construct(
        Mapper $filterMapper,
        Page\Mapper $pageMapper,
        Page\Option\Mapper $optionsMapper,
        Page\Option\Defaults\Mapper $defaultsMapper,
        RosettaStone $rosettaStone
    ) {
        $this->filterMapper   = $filterMapper;
        $this->pageMapper     = $pageMapper;
        $this->optionsMapper  = $optionsMapper;
        $this->defaultsMapper = $defaultsMapper;
        $this->rosettaStone   = $rosettaStone;
    }


    /**
     * Display a summary of filter configurations for Admins
     *
     * @api   GET /adminv2/filters
     * @since 17.0
     * @throws ServiceNotFound
     */
    public function getIndex(): void
    {
        $filtersPerPage = $this->findAvailableFiltersPerPage();

        $this->getResponse()->content->set($this->buildHtmlOutput('@filters/index.twig', [
            'filtersPerPage'           => $filtersPerPage,
            'pageLabels'               => $this->buildFilteredPageLabels(),
            'showCourseMarkbook'       => $this->getConfigLoader()->get('course_markbook_enable'),
            'ENTITY_ASSESSMENTS'       => Assessment\Mapper::class,
            'pageCurrent'              => basename($this->getRequest()->query->get('page')),
        ])->setTitle('Filterable pages'));
    }


    /**
     * Display a page where admins can alter default filter selections
     *
     * @api   GET /adminv2/filters/{pageKey}
     * @since 17.0
     * @param IFactoryFactory $factoryFactory The factory responsible for the entity filtered by this page
     * @param string          $page
     * @throws ServiceNotFound
     * @throws \InvalidArgumentException If a filter definition class not found, or filter factory not registered
     * @throws \Schoolbox\Mapper\DefinitionException If join $name isn't on this mapper
     * @throws \Schoolbox\Mapper\Exception If an alias is incorrect
     */
    public function getPageSettings(IFactoryFactory $factoryFactory, $page): void
    {
        $enabledOnly = false;
        $definitions = $factoryFactory->buildCurrentState($page, $enabledOnly);

        $pageFilters = $this->pageMapper->createCollection();
        foreach ($definitions as $class => $definition) {
            $pageFilters[$class] = $definition->getFilter()->getPages()[$page];
        }

        // Only output roles in the role types this filter supports
        $roleTypes      = Role::getRoleTypes();
        $rolesForFilter = [];
        foreach ($roleTypes as $roleType) {
            foreach ($pageFilters as $pageFilter) {
                foreach ($pageFilter->getOptions() as $option) {
                    if (isset($option->getDefaults()[$roleType])) {
                        if (!isset($rolesForFilter[$roleType])) {
                            // TODO inject role mapper
                            $rolesForFilter[$roleType] = RoleMapper::getInstance()->findByColumn('group_type', $roleType);
                        }
                        break 2;
                    }
                }
            }
        }

        $this->getResponse()->content->set($this->buildHtmlOutput('@filters/settings.twig', [
            'page'                     => $page,
            'definitions'              => $definitions,
            'pageFilters'              => $pageFilters,
            'roleTypes'                => $roleTypes,
            'rolesForFilter'           => $rolesForFilter,
            'pageLabels'               => $this->buildFilteredPageLabels(),
            'ENTITY_ASSESSMENTS'       => Assessment\Mapper::class,
        ])->setTitle(ucfirst($page) . ' filters'));
    }


    /**
     * Load up all settings for this page, overlay new settings, and save
     *
     * @param IFactoryFactory $factoryFactory The factory responsible for the entity filtered by this page
     * @param string          $page
     * @throws \UnexpectedValueException
     * @throws \PDOException
     * @throws \Alaress\Database\Predicate\InvalidColumnException
     * @throws \InvalidArgumentException If a filter definition class not found, or filter factory not registered
     * @throws \Schoolbox\Mapper\DefinitionException If join $name isn't on this mapper
     * @throws \Schoolbox\Mapper\Exception If an alias is incorrect
     * @throws \UnderflowException
     */
    public function postSavePageSettings(IFactoryFactory $factoryFactory, $page)
    {
        $enabledOnly = false;
        $definitions = $factoryFactory->buildCurrentState($page, $enabledOnly);

        $pagesToSave    = $this->pageMapper->createCollection();
        $optionsToSave  = $this->optionsMapper->createCollection();
        $defaultsToSave = $this->defaultsMapper->createCollection();

        /** @var GenericCollection|Page\Model[] $pageFilters */
        $pageFilters = $this->pageMapper->createCollection();
        foreach ($definitions as $class => $definition) {
            $pageFilters[$class] = $definition->getFilter()->getPages()[$page];
        }

        // Load current system options and defaults, and overlay with posted settings
        /** @var string[] $postEnabled */
        $postEnabled = $this->getRequest()->post->get('filter_enabled');
        /** @var string[] $postUserConfigurable */
        $postUserConfigurable = $this->getRequest()->post->get('filter_user_configurable');
        /** @var string[][] $postOptions */
        $postOptions = $this->getRequest()->post->get('filter_options');
        /** @var string[][][] $postDefaults */
        $postDefaults = $this->getRequest()->post->get('filter_defaults');

        foreach ($pageFilters as $pageFilter) {
            $filterId    = $pageFilter->getFilter()->getId();
            $class       = $pageFilter->getFilter()->getClass();
            $isMandatory = $class::isMandatory($pageFilter->getPage());

            $pageFilter->setEnabled($isMandatory || isset($postEnabled[$filterId]));
            $pageFilter->setUserConfigurable(isset($postUserConfigurable[$filterId]));

            // Must add() instead of offsetSet($id) as $id will be null if not yet in DB
            $pagesToSave->add($pageFilter);

            foreach ($pageFilter->getOptions() as $option) {
                $option->setAvailable(isset($postOptions[$filterId][$option->getKey()]));
                $optionsToSave->add($option);

                foreach ($option->getDefaults() as $default) {
                    $default->setSelected(isset($postDefaults[$filterId][$option->getKey()][$default->getRoleType()]));
                    $defaultsToSave->add($default);
                }
            }
        }

        $this->pageMapper->save($pagesToSave);
        $this->optionsMapper->save($optionsToSave);
        $this->defaultsMapper->save($defaultsToSave);

        // Delete any invalid pageFilters. This can occur when:
        // * a filter is configured to no longer be available for a page
        $pageBuilder = $this->pageMapper->getPredicateBuilder();
        $pageBuilder
            ->equals('page', $page)
            ->notIn('filter_id', $pagesToSave->map(function (Page\Model $pageFilter) {
                return $pageFilter->getFilter()->getId();
            })->getAll());
        $this->pageMapper->deleteByPredicates($pageBuilder);

        // Delete any invalid pageFilters and Options. This can occur when:
        // * a lookup list has an item removed
        foreach ($pagesToSave as $pageFilter) {
            $optionsBuilder = $this->optionsMapper->getPredicateBuilder();
            $optionsBuilder->equals('filter_page_id', $pageFilter->getId());
            if ($pageFilter->getOptions()->count()) {
                $optionsBuilder->notIn('key', $pageFilter->getOptions()->getColumnValues('key'));
            }
            $this->optionsMapper->deleteByPredicates($optionsBuilder);
        }

        // Delete any defaults for roleTypes that don't have access to the page
        $defaultsBuilder = $this->defaultsMapper->getPredicateBuilder();
        $defaultsBuilder
            ->in('filter_option_id', $optionsToSave->getColumnValues('id'))
            ->notIn('role_type', Page\Model::getPageRoleTypes()[$page]);
        $this->defaultsMapper->deleteByPredicates($defaultsBuilder);

        $this->response->content->set($this->buildJsonOutput(['status' => 200]));
    }


    /**
     * Push default selections to users in the given roles.
     *
     * @api   POST /adminv2/filters/{page}/pushDefaultSelections
     * @since 20.1
     *
     * @param IFactoryFactory $factoryFactory
     * @param string          $page
     */
    public function postPushDefaultSelections(IFactoryFactory $factoryFactory, string $page): void
    {
        Selection\Mapper::getInstance()->deleteSelectionsForRoleIds($this->request->post->get('role', []), $page);

        $this->response->content->set($this->buildJsonOutput(['status' => 200]));
    }



    /**
     * @return GenericCollection[]|Model[][] $visibleFilters Indexed by page, then filterDefinition.name
     */
    private function findAvailableFiltersPerPage(): array
    {
        /** @var GenericCollection|Model[] $visibleFilters */
        $visibleFilters = $this->filterMapper->findAll()->filter(static function (Model $instance) {
            $definitionClass = $instance->getClass();
            return count($definitionClass::getPages()) > 0;
        });

        // Group filters by which pages they appear on
        $filtersPerPage = [];
        foreach ($visibleFilters as $instance) {
            $class = $instance->getClass();
            foreach ($class::getPages() as $page) {
                $filtersPerPage[$page][$class::getName()] = $instance;
            }
        }
        return $filtersPerPage;
    }


    /**
     * Returns labels for each type of filtered page, indexed by page
     *
     * @return string[]
     */
    private function buildFilteredPageLabels(): array
    {
        $assessmentLocalised = $this->rosettaStone->translate('assessment', true, true);
        return [
            Page\Model::PAGE_PENDING_ASSESSMENTS  => "Pending {$assessmentLocalised}",
            Page\Model::PAGE_GRADES               => 'Grades',
            Page\Model::PAGE_CLASS_MARK_BOOK      => 'Class Markbook',
            Page\Model::PAGE_COURSE_MARK_BOOK     => 'Course Markbook',
            Page\Model::PAGE_RUBRIC_MARK_BOOK     => 'Rubric Markbook',
            Page\Model::PAGE_LEARNING_ANALYTICS   => 'Learning Analytics',
            Page\Model::PAGE_UPCOMING_ASSESSMENTS => "Upcoming Due Work", // intentionally not localised.
            Page\Model::PAGE_ASSESSMENT_CALENDAR  => "{$assessmentLocalised} Calendar",
        ];
    }
}
