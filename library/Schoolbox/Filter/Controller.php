<?php

/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Filter;

use Alaress\Database\Predicate\InvalidColumnException;
use Alaress\Web\Controller\Base as BaseController;
use Aura\Router\Exception\RouteNotFound;
use Exception;
use InvalidArgumentException;
use PDOException;
use Schoolbox\Admin\Navigation\Subnav;
use Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\Filter\Page\AvailabilityChecker;
use Schoolbox\Filter\Page\LabelBuilder;
use Schoolbox\GenericCollection;
use Schoolbox\L10n\RosettaStone;
use Schoolbox\Learning\Assessment\DataModel as Assessment;
use Schoolbox\Mapper\DefinitionException;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Role;
use Schoolbox\RoleMapper;
use Schoolbox\Learning\Badge;
use Twig\Error\Error as TwigError;
use UnderflowException;
use UnexpectedValueException;
use function count;

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

    /**
     * @var Selection\Mapper
     */
    private $selectionMapper;

    /**
     * @var RoleMapper
     */
    private $roleMapper;

    /**
     * @var ErrorMapper
     */
    private $errorMapper;

    /** @var RosettaStone */
    private $rosettaStone;

    /**
     * @param Mapper                      $filterMapper
     * @param Page\Mapper                 $pageMapper
     * @param Page\Option\Mapper          $optionsMapper
     * @param Page\Option\Defaults\Mapper $defaultsMapper
     * @param Selection\Mapper            $selectionMapper
     * @param RoleMapper                  $roleMapper
     * @param ErrorMapper                 $errorMapper
     * @param RosettaStone                $rosettaStone
     */
    public function __construct(
        Mapper $filterMapper,
        Page\Mapper $pageMapper,
        Page\Option\Mapper $optionsMapper,
        Page\Option\Defaults\Mapper $defaultsMapper,
        Selection\Mapper $selectionMapper,
        RoleMapper $roleMapper,
        ErrorMapper $errorMapper,
        RosettaStone $rosettaStone
    ) {
        $this->filterMapper    = $filterMapper;
        $this->pageMapper      = $pageMapper;
        $this->optionsMapper   = $optionsMapper;
        $this->defaultsMapper  = $defaultsMapper;
        $this->selectionMapper = $selectionMapper;
        $this->roleMapper      = $roleMapper;
        $this->errorMapper     = $errorMapper;
        $this->rosettaStone    = $rosettaStone;
    }

    /**
     * Display a summary of filter configurations for Admins
     *
     * @throws TwigError|RouteNotFound
     * @since 17.0
     * @api   GET /adminv2/filters
     */
    public function getIndex(): void
    {
        $filtersPerPage = $this->findAvailableFiltersPerPage();

        $this->response = $this->responseFromTemplate('@filters/index.twig', [
            'filtersPerPage'     => $filtersPerPage,
            'pageLabels'         => $this->buildFilteredPageLabels(),
            'ENTITY_ASSESSMENTS' => Assessment\Mapper::class,
            'ENTITY_BADGES'      => Badge\Mapper::class,
            'pageCurrent'        => $this->routeGenerator->generate('filters.getIndex'),
            'allBreadcrumbs'     => (new Subnav($this->getConfigLoader(), $this->getUser()))->generateBreadcrumbs($this->routeGenerator->generate('filters.getIndex')),
        ], 'Filterable pages');
    }

    /**
     * Display a page where admins can alter default filter selections
     *
     * @param IFactoryFactory $factoryFactory The factory responsible for the entity filtered by this page
     * @param string          $page
     * @throws RouteNotFound
     * @throws TwigError
     * @api   GET /adminv2/filters/{pageKey}
     * @since 17.0
     */
    public function getPageSettings(IFactoryFactory $factoryFactory, string $page): void
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
                            $rolesForFilter[$roleType] = $this->roleMapper->findByColumn('group_type', $roleType);
                        }
                        break 2;
                    }
                }
            }
        }

        $filterCategory = $this->getFilterCategory($page);

        $this->response = $this->responseFromTemplate('@filters/settings.twig', [
            'page'               => $page,
            'pageCurrent'        => $this->routeGenerator->generate("filters.getPageSettings$filterCategory", ['page' => $page]),
            'allBreadcrumbs'     => (new Subnav($this->getConfigLoader(), $this->getUser()))->generateBreadcrumbs($this->routeGenerator->generate("filters.getPageSettings$filterCategory", ['page' => $page])),
            'definitions'        => $definitions,
            'pageFilters'        => $pageFilters,
            'roleTypes'          => $roleTypes,
            'rolesForFilter'     => $rolesForFilter,
            'pageLabels'         => $this->buildFilteredPageLabels(),
            'filterCategory'     => $filterCategory,
        ], ucfirst($page) . ' filters');
    }

    /**
     * Load up all settings for this page, overlay new settings, and save
     *
     * @param IFactoryFactory $factoryFactory The factory responsible for the entity filtered by this page
     * @param string          $page
     * @throws UnexpectedValueException
     * @throws PDOException
     * @throws InvalidColumnException
     * @throws InvalidArgumentException If a filter definition class not found, or filter factory not registered
     * @throws DefinitionException If join $name isn't on this mapper
     * @throws MapperException If an alias is incorrect
     * @throws UnderflowException
     */
    public function postSavePageSettings(IFactoryFactory $factoryFactory, string $page): void
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
        $postEnabled = $this->requestPost->get('filter_enabled');
        /** @var string[] $postUserConfigurable */
        $postUserConfigurable = $this->requestPost->get('filter_user_configurable');
        /** @var string[][] $postOptions */
        $postOptions = $this->requestPost->get('filter_options');
        /** @var string[][][] $postDefaults */
        $postDefaults = $this->requestPost->get('filter_defaults');

        /**
         * @var IDefinition $class
         * @var \Schoolbox\Filter\Page\Model $pageFilter
         */
        foreach ($pageFilters as $class => $pageFilter) {
            $filterId    = $pageFilter->getFilter()->getId();
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
                $optionsBuilder->notIn('key', $pageFilter->getOptions()->getPropertyValues('key'));
            }
            $this->optionsMapper->deleteByPredicates($optionsBuilder);
        }

        // Delete any defaults for roleTypes that don't have access to the page
        $defaultsBuilder = $this->defaultsMapper->getPredicateBuilder();
        $defaultsBuilder
            ->in('filter_option_id', $optionsToSave->getPropertyValues('id'))
            ->notIn('role_type', Page\Model::getPageRoleTypes()[$page]);
        $this->defaultsMapper->deleteByPredicates($defaultsBuilder);

        $this->response = $this->responseAsJson(['status' => 200]);
    }

    /**
     * Push default selections to users in the given roles.
     *
     * @param string $page
     * @throws Exception
     * @api   POST /adminv2/filters/{page}/pushDefaultSelections
     * @since 20.1
     */
    public function postPushDefaultSelections(string $page): void
    {
        $roleIds = $this->requestPost->get('role', []);
        if (!is_array($roleIds) || empty($roleIds)) {
            throw $this->errorMapper->generateException(ErrorMapper::ERROR_INFORMATION_MISSING);
        }
        $this->selectionMapper->deleteSelectionsForRoleIds($roleIds, $page);
        $this->response = $this->responseAsJson(['status' => 200]);
    }

    // Functions for routes accessible by Pastoral Moderators --------------------------------------------------------//

    /**
     * Display a page where admins can alter default filter selections
     *
     * @param IFactoryFactory $factoryFactory The factory responsible for the entity filtered by this page
     * @param string          $page
     * @throws RouteNotFound
     * @throws TwigError
     */
    public function getPageSettingsPastoral(IFactoryFactory $factoryFactory, string $page): void
    {
        $this->getPageSettings($factoryFactory, $page);
    }

    /**
     * Load up all settings for this page, overlay new settings, and save
     *
     * @param IFactoryFactory $factoryFactory The factory responsible for the entity filtered by this page
     * @param string          $page
     * @throws UnexpectedValueException
     * @throws PDOException
     * @throws InvalidColumnException
     * @throws InvalidArgumentException If a filter definition class not found, or filter factory not registered
     * @throws DefinitionException If join $name isn't on this mapper
     * @throws MapperException If an alias is incorrect
     * @throws UnderflowException
     */
    public function postSavePageSettingsPastoral(IFactoryFactory $factoryFactory, string $page): void
    {
        $this->postSavePageSettings($factoryFactory, $page);
    }

    /**
     * Push default selections to users in the given roles.
     *
     * @param string $page
     * @throws Exception
     */
    public function postPushDefaultSelectionsPastoral(string $page): void
    {
        $this->postPushDefaultSelections($page);
    }

    // Functions for routes accessible by LMS Moderators -------------------------------------------------------------//

    /**
     * Display a page where admins can alter default filter selections
     *
     * @param IFactoryFactory $factoryFactory The factory responsible for the entity filtered by this page
     * @param string          $page
     * @throws RouteNotFound
     * @throws TwigError
     */
    public function getPageSettingsLms(IFactoryFactory $factoryFactory, string $page): void
    {
        $this->getPageSettings($factoryFactory, $page);
    }

    /**
     * Load up all settings for this page, overlay new settings, and save
     *
     * @param IFactoryFactory $factoryFactory The factory responsible for the entity filtered by this page
     * @param string          $page
     * @throws UnexpectedValueException
     * @throws PDOException
     * @throws InvalidColumnException
     * @throws InvalidArgumentException If a filter definition class not found, or filter factory not registered
     * @throws DefinitionException If join $name isn't on this mapper
     * @throws MapperException If an alias is incorrect
     * @throws UnderflowException
     */
    public function postSavePageSettingsLms(IFactoryFactory $factoryFactory, string $page): void
    {
        $this->postSavePageSettings($factoryFactory, $page);
    }

    /**
     * Push default selections to users in the given roles.
     *
     * @param string $page
     * @throws Exception
     */
    public function postPushDefaultSelectionsLms(string $page): void
    {
        $this->postPushDefaultSelections($page);
    }

    // Private functions ---------------------------------------------------------------------------------------------//

    /**
     * @return GenericCollection[]|Model[][] $visibleFilters Indexed by page, then filterDefinition.name
     */
    private function findAvailableFiltersPerPage(): array
    {
        /** @var GenericCollection|Model[] $visibleFilters */
        $visibleFilters = $this->filterMapper->findAll()->filter(function (Model $instance) {
            $definitionClass = $instance->getClass();
            return count($definitionClass::getPages($this->getConfigLoader())) > 0;
        });

        // Group filters by which pages they appear on
        $availabilityChecker = new AvailabilityChecker($this->getConfigLoader());
        $filtersPerPage      = [];
        foreach ($visibleFilters as $instance) {
            $class = $instance->getClass();
            foreach ($class::getPages($this->getConfigLoader()) as $page) {
                if ($availabilityChecker->isAvailable($page)) {
                    $filtersPerPage[$page][$class::getName()] = $instance;
                }
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
        $labelBuilder = new LabelBuilder();
        return [
            Page\Model::PAGE_PENDING_ASSESSMENTS  => $labelBuilder->buildLabel(Page\Model::PAGE_PENDING_ASSESSMENTS),
            Page\Model::PAGE_GRADES               => $labelBuilder->buildLabel(Page\Model::PAGE_GRADES),
            Page\Model::PAGE_CLASS_MARK_BOOK      => $labelBuilder->buildLabel(Page\Model::PAGE_CLASS_MARK_BOOK),
            Page\Model::PAGE_COURSE_MARK_BOOK     => $labelBuilder->buildLabel(Page\Model::PAGE_COURSE_MARK_BOOK),
            Page\Model::PAGE_RUBRIC_MARK_BOOK     => $labelBuilder->buildLabel(Page\Model::PAGE_RUBRIC_MARK_BOOK),
            Page\Model::PAGE_LEARNING_ANALYTICS   => $labelBuilder->buildLabel(Page\Model::PAGE_LEARNING_ANALYTICS),
            Page\Model::PAGE_UPCOMING_ASSESSMENTS => $labelBuilder->buildLabel(Page\Model::PAGE_UPCOMING_ASSESSMENTS),
            Page\Model::PAGE_ASSESSMENT_CALENDAR  => $labelBuilder->buildLabel(Page\Model::PAGE_ASSESSMENT_CALENDAR),
            Page\Model::PAGE_BADGE_LIST           => $labelBuilder->buildLabel(Page\Model::PAGE_BADGE_LIST),
            Page\Model::PAGE_AWARD_LIST           => $labelBuilder->buildLabel(Page\Model::PAGE_AWARD_LIST),
            Page\Model::PAGE_CLASS_PASTORAL       => $labelBuilder->buildLabel(Page\Model::PAGE_CLASS_PASTORAL),
        ];
    }

    /**
     * @param string $page
     * @return string
     */
    private function getFilterCategory(string $page): string
    {
        return match ($page) {
            'classPastoral' => 'Pastoral',
            'assessmentCal',
            'classMarkbook',
            'courseMarkbook',
            'dueWorkCal',
            'grades',
            'pending',
            'rubricMarkbook' => 'Lms',
            default => '',
        };
    }
}
