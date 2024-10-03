<?php

/**
 *
 * @author      Alex Tworowsky <alex.tworowsky@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 *
 */

use Alaress\Color\ColorFactory;
use Aura\Router\Exception\RouteNotFound;
use External\Canvas\Importer\CanvasDb;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\Learning\Assessment\Redirect;
use Schoolbox\Learning\Course\DataModel\Specification\Model as CourseSpecification;
use Schoolbox\Learning\Course\DataModel\Specification\Unit\Model as UnitSpecification;
use Schoolbox\Permission\Access\Access;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Resource\Folder;
use Schoolbox\Resource\Folder\Mapper;
use Schoolbox\Resource\Folder\Model;
use Sentry\State\HubInterface;

$folderMapper = Mapper::getInstance();
$folder       = null;
$prefix       = 'code';

/** @var SitePage $this */

/* Retrieve the category ID from the URI, must be an integer and at least 1 character long
 * e.g. /homepage/43
 */
if (preg_match('/^[0-9]+$/', $this->arg[0])) {
    try {
        $folder = $folderMapper->findById($this->arg[0]);
    } catch (\Schoolbox\Mapper\Exception $e) {
        $this->pageNotFound();
    }

    /* If the category wasn't found, try searching for a category code with this
     * numerical value i.e. treat the int as if it were a category code string
     */
    try {
        if (!$folder) {
            $folder = $folderMapper->findByCode($this->arg[0]);
        }
    } catch (\Schoolbox\Mapper\Exception $e) {
        $this->pageNotFound();
    }
} elseif (($this->arg[0] === $prefix) && (count($this->arg) > 1)) {
    /* If a prefixed argument is given, load category code directly
     * e.g. /homepage/$prefix/EN344
     */
    $items = $this->arg;
    array_shift($items);
    $code = implode('/', $items);

    try {
        // TODO investigate whether this needs to be double-decoded, or decoded at all
        $folder = $folderMapper->findByCode(urldecode(urldecode($code)));
    } catch (Exception $e) {
        $this->pageNotFound();
    }
} elseif (!is_numeric($this->arg[0])) {
    /* When just an alphabetical category code provided
     * e.g. /homepage/EN344
     */
    $code = implode('/', $this->arg);

    $this->localRedir("/homepage/$prefix/$code");
}

/** @var HubInterface $sentry */
$sentry = $this->getDi()->get('sentry');

/**
 * Canvas custom redirect
 *
 * Ensure Canvas plan is enabled
 * ensure canvas api creds (url, client id and url) are setup NOTE: we only need CD2 now
 * and the folder you are looking at has the valid canvas checks:
 * This folder has a code
 */
$canvasUrl = $this->getDi()->get('config')->get(ConfigMapper::_CANVAS_URL);

if (
    !empty($canvasUrl) &&
    !empty($this->getDi()->get('config')->get(ConfigMapper::_CANVAS_DATA2_CLIENT_ID)) &&
    !empty($this->getDi()->get('config')->get(ConfigMapper::_CANVAS_DATA2_SECRET)) &&
    '' !== $folder->getCodeString()
) {
    $redirect    = (new \External\Canvas\Redirect\RedirectFactory(
        (string)$this->getDi()->get('config')->get(ConfigMapper::NAME_SITE_SCHOOL_CODE),
        (string)$canvasUrl,
        new CanvasDb($this->getDi()->get('config')),
        $folderMapper
    ))->produce();

    try {
        $redirectUrl = $redirect?->generateRedirect($folder);
    } catch (\Alaress\Database\Exception $e) {
        // Don't know where to go: just go to Canvas
        // Log first I guess
        $sentry?->captureException($e);
        $redirectUrl = $canvasUrl;
    }

    if (null !== $redirectUrl) {
        $this->localRedir($redirectUrl);
    }
    /* If you haven't redirected by now, just continue onwards...
     * as if nothing happened
     */
}

/* if we still have no category, advise that these are not the droids you're looking for
 *
 * Check that it's not soft deleted
 */
if (!($folder instanceof Model) || $folder->isDeleted()) {
    error(ErrorMapper::ERROR_SUBJECT_DELETED);
}

if (!$folder->getId()) {
    $this->localRedir('/resources/');
}

/** @var \Schoolbox\User $sessionUser */
$sessionUser      = $this->getDi()->get('session')->get('user');
$actualPermission = Access::getInstance()->getFolderAccess($sessionUser, $folder->getId());

// confirm that the user has permission to view this page
if ($actualPermission < UserAccess::ACCESS_READ) {
    error(ErrorMapper::ERROR_PERM_PAGE_VIEW);
}

$readonly = !empty($_GET['readonly']);

// If write level user, downgrade to read access.
$virtualPermission = (
    ($actualPermission > UserAccess::ACCESS_READ && $readonly) ? UserAccess::ACCESS_READ : $actualPermission
);

// Set up the homepage handler.
$homepageHandler = new Homepage($folder->getId());

// this category doesn't have a template, therefore doesn't have a homepage: redirect to resource browser.
if (!$homepageHandler->getTemplate()) {
    $this->localRedir('/resources/' . $folder->getId());
}

// Color mangling
$colorFactory = new ColorFactory();

// Load this homepage's color list, falling back to defaults as required
$listColor = [];
foreach ($homepageHandler->getColorList() as $key => $color) {
    try {
        $listColor[$key] = (string)$colorFactory->build($color ?? '');
    } catch (InvalidArgumentException $e) {
        $listColor[$key] = (string)$colorFactory->build('000000');
    }
}

// Moving forward! Log the page view.
$mapper = new Schoolbox\Resource\Folder\Log\Mapper();
$mapper->incrementHitcount($sessionUser, $folder);
$folderMapper->logPageView($folder, $sessionUser->getRole());

/* BUILD PAGE */
$this->setFile(TEMPLATE_content, 'homepage/index.html');
$this->setTitle($folder->getName());
// Hierarchy of blocks set in calls to setBlock() below
// ----------------------------------------------------
// 'custom_head'
//  * 'colour_list'
//  * 'modify_layout'
// TEMPLATE_content
//  * 'customise_page'
//  * 'edit_page'
//  * 'page_controls'
//     * 'browse_resources'
//     * 'course_actions'
//        * 'analytics_section_heading'
//        * 'class_analytics_link'
//        * 'schoolbox_analytics_link'
//     * 'manage_class'
//  * 'readonly_switch_off'
//  * 'readonly_switch_on'
$this->setBlock('custom_head', 'colour_list', 'colour_list_block');
$this->setBlock('custom_head', 'modify_layout', 'modify_layout_block');
$this->setBlock(TEMPLATE_content, 'customise_page', 'customise_page_block');
$this->setBlock(TEMPLATE_content, 'edit_page', 'edit_page_block');
$this->setBlock(TEMPLATE_content, 'page_controls', 'page_controls_block');
$this->setBlock('page_controls', 'browse_resources', 'browse_resources_block');
$this->setBlock('page_controls', 'course_actions', 'course_actions_block');
$this->setBlock('course_actions', 'analytics_section_heading', 'analytics_section_heading_block');
$this->setBlock('course_actions', 'class_analytics_link', 'class_analytics_link_block');
$this->setBlock('course_actions', 'schoolbox_analytics_link', 'schoolbox_analytics_link_block');
$this->setBlock('page_controls', 'manage_class', 'manage_class_block');
$this->setBlock(TEMPLATE_content, 'readonly_switch_off', 'readonly_switch_off_block');
$this->setBlock(TEMPLATE_content, 'readonly_switch_on', 'readonly_switch_on_block');

/* BUILD COMPONENTRY */
$componentFactory = new Component_Homepage_Factory($sessionUser, $this);
if ($readonly) {
    $componentFactory->setMaximumPermission($virtualPermission);
}
$componentCollection = $componentFactory->buildComponentList($folder->getId());

// Decide which of parent/student view buttons to show, if either.
$this->setVar('readonly_switch_on_block');
$this->setVar('readonly_switch_off_block');
if ($actualPermission >= UserAccess::ACCESS_WRITE) {
    if ($readonly) {
        $this->parse('readonly_switch_off_block', 'readonly_switch_off');
    } else {
        $this->parse('readonly_switch_on_block', 'readonly_switch_on');
    }
}

// User has elevated privileges: can write to page
if ($virtualPermission >= UserAccess::ACCESS_WRITE) {
    $this->setBlock('customise_page', 'homepage_component_tab', 'homepage_component_tab_block');
    $this->setBlock('customise_page', 'homepage_component_page', 'homepage_component_page_block');
    $this->setBlock('customise_page', 'homepage_color_selector', 'homepage_color_selector_block');
    $this->setBlock('homepage_component_page', 'homepage_component', 'homepage_component_block');
    $this->parse('edit_page_block', 'edit_page');
    $this->parse('modify_layout_block', 'modify_layout');
    $this->parse('customise_page_block', 'customise_page');
    $this->parse('browse_resources_block', 'browse_resources');

    $hasCourseSpec = $folder->getCourseSpecification() instanceof CourseSpecification && $folder->getCourseSpecification()->getId();
    $hasUnitSpec   = $folder->getUnitSpecification() instanceof UnitSpecification && $folder->getUnitSpecification()->getId();
    if (
        (!$hasCourseSpec && !$hasUnitSpec)
        && Config::get(ConfigMapper::NAME_LMS_ENABLED)
        && ($sessionUser->getRole()->isStaff() || $sessionUser->isSuperuser())
    ) {
        $this->parse('course_actions_block', 'course_actions');
    } else {
        $this->setVar('course_actions_block');
    }

    if (Config::get('homepage_enable_colour')) {
        $this->parse('homepage_color_selector_block', 'homepage_color_selector');
    } else {
        $this->setVar('homepage_color_selector_block');
    }

    $definitionCollection = $componentFactory->getDefinitionList('component_category');

    /** @var Component_Definition $definition */
    foreach ($definitionCollection as $definition) {
        foreach ($componentCollection as $inst) {
            /** @var Component_ControllerAbstract $inst */
            if ($inst->getInstance()->getDefinitionId() === $definition->getId()) {
                $definition->incrementInstanceCount();
            }
        }
    }

    $tabNumber = 0;
    if ($total = $definitionCollection->count()) {
        $previous = new Component_Definition();
        $folderId = ($folder instanceof Schoolbox\Resource\Folder\Model) ? $folder->getId() : null;

        foreach ($definitionCollection as $component) {
            /** @var Component_Definition $component */
            if (!$component->renderInPalette($component->getModule(), $folderId)) {
                continue;
            }

            /* Some components can only spawn once
             * Others can spawn multiple times
             *
             * Apply different css classes to show that limitation where appropriate
             * at the time of loading
             */
            $class = '';
            if ($component->getMultiple() === false) {
                $class = 'one-only ';
                if ($component->getMultiple() === false && $component->getInstanceCount() > 0) {
                    $class .= 'disabled ';
                }
            }

            /* If this is not our first iteration
             * and this component's category is different than what saw previously,
             * then parse the tab we were populating and start the next one
             */
            if ($previous->getComponentCategory() && $component->getComponentCategory() !== $previous->getComponentCategory()) {
                $this->parse('homepage_component_page_block', 'homepage_component_page', true);
                $this->setVar('homepage_component_block');

                $tabNumber++;
            }

            $this->setVar([
                'HOMEPAGE_COMPONENT_ID'             => Scrub::text($component->getId()),
                'HOMEPAGE_COMPONENT_NAME'           => Scrub::text($component->getName()),
                'HOMEPAGE_COMPONENT_COUNT'          => $component->getInstanceCount(),
                'HOMEPAGE_COMPONENT_DESCRIPTION'    => Scrub::text($component->getDescription()),
                'HOMEPAGE_COMPONENT_CLASS'          => $component->getCssClass(),
                'HOMEPAGE_COMPONENT_MULTIPLE_STATE' => trim($class),
                'HOMEPAGE_COMPONENT_CATEGORY_ID'    => $component->getComponentCategory(),
                'HOMEPAGE_COMPONENT_CATEGORY_CLASS' => $tabNumber === 0 ? 'active' : '',
            ]);

            $this->parse('homepage_component_block', 'homepage_component', true);

            /* Remember what we just processed
             */
            $previous = $component;

            /* Initialise category counts if they don't already exist
             */
            if (!isset($tabList[$component->getComponentCategory()]['count'])) {
                $tabList[$component->getComponentCategory()]['count'] = 0;
            }

            // This was set to $component->componentCategoryName - a non-existent value.
            $tabList[$component->getComponentCategory()]['name'] = $component->getComponentCategoryName();
            $tabList[$component->getComponentCategory()]['count']++;
        }

        /* Final page set is always parsed
         */
        $this->parse('homepage_component_page_block', 'homepage_component_page', true);
    } else {
        $this->setVar([
            'homepage_component_block'      => '',
            'homepage_component_page_block' => 'No components are available for you to add.',
            'homepage_component_tab_block'  => '',
        ]);
    }

    if (isset($tabList) && count($tabList)) {
        $tabNumber = 0;
        foreach ($tabList as $id => $tab) {
            $this->setVar([
                'COMPONENT_CATEGORY_ID'    => $id,
                'COMPONENT_CATEGORY_NAME'  => Scrub::text($tab['name']),
                'COMPONENT_CATEGORY_COUNT' => $tab['count'],
                'COMPONENT_CATEGORY_CLASS' => $tabNumber === 0 ? 'active' : '',
            ]);
            $this->parse('homepage_component_tab_block', 'homepage_component_tab', true);
            $tabNumber++;
        }
    }

    // Template control.
    $template = $homepageHandler->getTemplate();
    for ($x = 1; $x <= 4; $x++) {
        $this->setVar("CURRENT_TEMPLATE_$x", $template == $x ? 'current' : '');
    }

    // Hide controls on page load (lock mode).
    $this->setVar([
        'HIDE_CONTROLS_ON_WRITE' => 'style="display:none;"'
    ]);
} else {
    $this->setVar([
        'edit_page_block'        => '',
        'modify_layout_block'    => '',
        'customise_page_block'   => '',
        'colour_list_block'      => '',
        'course_actions_block'   => '',
        'browse_resources_block' => '',
        'HIDE_CONTROLS_ON_WRITE' => '' // Read access users still see their collapse button.
    ]);
}

// Setup page control buttons.
$manageFolderController = new Component_Homepage_ManageFolderController(new Component_Instance(), $sessionUser, $this);
$manageFolderController->setMaximumPermission($virtualPermission);
$manageFolderController->setup($folder);
$manageFolderController->execute();
$manageFolderController->renderTemplate('page_controls');

// Setup class action buttons (in 3-dot menu).
$manageClassController = new Component_Homepage_ManageClassController(new Component_Instance(), $sessionUser, $this);
$manageClassController->setMaximumPermission($virtualPermission);
$manageClassController->setup($folder);
$manageClassController->execute();
if ($manageClassController->renderTemplate('manage_class')) {
    $this->parse('manage_class_block', 'manage_class');
} else {
    $this->setVar('manage_class_block');
}

// Setup analytics buttons (in 3-dot menu).
renderTemplateForAnalyticsSection($this, $manageClassController, $sessionUser);

$this->parse('page_controls_block', 'page_controls');

$codeString = ($sessionUser->getRole()->isStaff()) ? Scrub::text($folder->getTruncatedCodeString()) : '';

$this->setVar(array(
    'HEADING'            => Scrub::text($folder->getName()) . ($codeString ? ' (' . $codeString . ')' : ''),
    'HEADING_JS'         => json_encode($folder->getName() . ($codeString ? ' (' . $codeString . ')' : '')),
    'CODE'               => urlencode(urlencode($folder->getCodeString())),
    'ID'                 => $folder->getId(),
    'USER_TOKEN'         => $sessionUser->getKey(),
    'ENCODED_RETURN_URL' => Redirect::encode("/homepage/{$folder->getId()}"),

    /* Homepage rss feed
     */
    'RSS' => $this->routeLookup('commsNewsFeed.getFeedForFolder', [
        'user'     => $sessionUser,
        'key'      => $sessionUser->getKey(),
        'folder'   => $folder,
        'folderId' => $folder->getId()
    ]),
));

// Colors (original)
$this->setVar('COLOUR_ARRAY', 'original');
$this->setVar($listColor);
$this->parse('colour_list_block', 'colour_list', true);

// Colors (picker)
$this->setVar('COLOUR_ARRAY', 'pickers');
$this->setVar($listColor);
$this->parse('colour_list_block', 'colour_list', true);

// Defaults
$defaultColour = array(
    'head_font'    => Config::get('homepage_colour_head_font'),
    'head_bg'      => Config::get('homepage_colour_head_bg'),
    'font_primary' => Config::get('homepage_colour_font'),
    'bg_primary'   => Config::get('homepage_colour_bg_primary')
);

$this->setVar('COLOUR_ARRAY', 'defaults');

foreach (['top', 'left', 'right'] as $col) {
    foreach ($defaultColour as $field => $colour) {
        $this->setVar($col . '_' . $field, $colour);
    }
}
$this->parse('colour_list_block', 'colour_list', true);

$allWidths = $homepageHandler->getColumnWidths();
$leftWidth = round($allWidths['left_column_width'] * 12 / 100);

$this->setVar(array(
    'TEMPLATE_LEFT_COLUMN_WIDTH'  => $leftWidth,
    'TEMPLATE_RIGHT_COLUMN_WIDTH' => 12 - $leftWidth,
));

$this->setVar($listColor);

/* PARSE COMPONENTRY */
$occupiedPositionList = array();
foreach ($componentCollection as $component) {
    try {
        /** @var Component_Homepage_ControllerAbstract $component */
        $instance       = $component->getInstance();
        $templateTarget = "component_position_{$instance->getPosition()}";
        $templateHandle = "component_{$instance->getId()}";

        $component->setup($folder);

        if ($component->execute() && $component->renderTemplate($templateHandle) !== false) {
            if (!in_array($instance->getPosition(), $occupiedPositionList)) {
                $occupiedPositionList[] = $instance->getPosition();
            }

            $this->parse($templateTarget, $templateHandle, true);
        }
    } catch (Throwable $e) {
        // if a component explodes for any reason, we only want it to explode the page if we're in debug mode
        if (Config::get('debug_mode')) {
            throw $e;
        }
        $sentry?->captureException($e);
    }
}

$emptyPositionList = array_diff(array('top', 'left', 'right','bottom'), $occupiedPositionList);

foreach ($emptyPositionList as $emptyPosition) {
    $this->setVar("component_position_$emptyPosition");
}

// Functions. //////////////////////////////////////////////////////////////////////////////////////////////////////////

/**
 * The Analytics section in the 3-dot menu was moved from the leftmost column (titled 'Class Actions'), to the
 * rightmost column (titled 'Course Actions').
 * Since this section is no longer in the 'Class Actions' column, it does not belong in the ManageClassController.  So
 * this code was moved from that class to here.
 *
 * Note that this code needs to run even if the current folder is not a class, in order to blank out the PHPLib blocks.
 *
 * @param SitePage                                 $phpLibObj
 * @param Component_Homepage_ManageClassController $manageClassController
 * @param \Schoolbox\User                          $sessionUser
 * @return void
 * @throws ContainerExceptionInterface
 * @throws NotFoundExceptionInterface
 * @throws RouteNotFound
 * @throws \Schoolbox\Mapper\Exception
 */
function renderTemplateForAnalyticsSection(SitePage $phpLibObj, Component_Homepage_ManageClassController $manageClassController, Schoolbox\User $sessionUser): void
{
    // Blank out all PHPLib blocks relating to analytics initially.  They will be re-enabled if necessary below.
    $phpLibObj->setVar('analytics_section_heading_block');
    $phpLibObj->setVar('class_analytics_link_block');
    $phpLibObj->setVar('schoolbox_analytics_link_block');

    $hasAnalytics = false;

    // Class analytics link.
    if ($manageClassController->getContext()->isClass()) {
        $cohortProfileRoute = 'intellischool.getClassAnalytics';
        $params             = [
            'folder'   => $manageClassController->getContext(),
            'folderId' => $manageClassController->getContext()->getId(),
        ];
        if ($manageClassController->getAuthorizer()->isPathAuthorizedForUser($cohortProfileRoute, $params, $sessionUser)) {
            $hasAnalytics = true;
            $phpLibObj->setVar('CLASS_ANALYTICS_LINK', $manageClassController->getRouteGenerator()->generate($cohortProfileRoute, $params));
            $phpLibObj->parse('class_analytics_link_block', 'class_analytics_link');
        }
    }

    // Schoolbox analytics link.
    // Grab magic code to grab course IDs this folder is part of.
    if (Config::get(ConfigMapper::NAME_LMS_ENABLED)) {
        $currentTerm      = $manageClassController->getCurrentTerm();
        $classListFactory = new Folder\ClassListFactory($phpLibObj->getDi()->get('databaseInternal'));
        $courses          = $classListFactory->findFoldersWithDueImportedAssessments(
            [$manageClassController->getContext()->getId()],
            $currentTerm->getStart(),
            $currentTerm->getActualFinish()
        );
        if (0 !== count($courses[$manageClassController->getContext()->getId()])) {
            $schoolboxAnalytics = 'learningAnalytics.folderWithTerm';
            foreach ($courses[$manageClassController->getContext()->getId()] as $courseId => $courseTitle) {
                $params = [
                    'courseSpecId' => $courseId,
                    'folderId'     => $manageClassController->getContext()->getId(),
                    'termCode'     => $currentTerm->getCode(),
                ];
                if ($manageClassController->getAuthorizer()->isPathAuthorizedForUser($schoolboxAnalytics, $params, $sessionUser)) {
                    $hasAnalytics = true;
                    $phpLibObj->setVar('SCHOOLBOX_ANALYTICS_LINK', $manageClassController->getRouteGenerator()->generate($schoolboxAnalytics, $params));
                    $phpLibObj->setVar('SCHOOLBOX_ANALYTICS_LINK_TITLE', $courseTitle);
                    $phpLibObj->parse('schoolbox_analytics_link_block', 'schoolbox_analytics_link', true);
                }
            }
        }
    }

    if ($hasAnalytics) {
        $phpLibObj->parse('analytics_section_heading_block', 'analytics_section_heading');
    }
}
