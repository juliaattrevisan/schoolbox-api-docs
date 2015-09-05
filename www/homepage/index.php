<?php
/**
 *
 * @author      Alex Tworowsky <alex.tworowsky@alaress.com.au>
 * @copyright   2011 Alaress Pty Ltd
 * @link        http://www.alaress.com.au
 * @version     SVN: $Id$
 *
 */
$categoryMapper = new \Schoolbox\Resource\CategoryMapper();

$groupID = $_SESSION['gid'];
$prefix = 'code';

/* Retrieve the category ID from the URI, must be an integer and at least 1 character long
 * e.g. /homepage/43
 */
if (preg_match('/^[1-9]+[0-9]*$/', $this->arg[0])) {
    try {
        $category = $categoryMapper->findById($this->arg[0]);
    } catch (\Exception $e) {
        $this->pageNotFound();
    }

    /* If the category wasn't found, try searching for a category code with this
	 * numerical value i.e. treat the int as if it were a category code string
	 */
    try {
        if (!$category) {
            $category = $categoryMapper->findByCode($this->arg[0]);
        }
    } catch (\Exception $e) {
        $this->pageNotFound();
    }
} /* If a prefixed argument is given, load category code directly
 * e.g. /homepage/$prefix/EN344
 */
elseif (($this->arg[0] == $prefix) && (count($this->arg) > 1)) {
    $items = $this->arg;
    array_shift($items);

    $code = implode('/', $items);

    try {
        // TODO investigate whether this needs to be double-decoded, or decoded at all
        $category = $categoryMapper->findByCode(urldecode(urldecode($code)));
    } catch (\Exception $e) {
        $this->pageNotFound();
    }
} /* When just an alphabetical category code provided
 * e.g. /homepage/EN344
 */
elseif (!is_numeric($this->arg[0])) {
    $items = $this->arg;
    $code = implode('/', $items);

    $this->localRedir("/homepage/{$prefix}/$code");
}

/* if we still have no category, advise that these are not the droids you're looking for
 *
 * Check that it's not softdeleted
 */
if (!($category instanceof \Schoolbox\Resource\Folder\Model) || $category->isDeleted()) {
    error(18);
}

// confirm that the user has permission to view this page
$g = new Group();
if (($actualPermission = $g->getPermission($category->getId())) < READ_ACCESS) {
    error(14);
}

// If I'm a write level user, I can downgrade myself to read access
if ($actualPermission > READ_ACCESS && $_GET['readonly']) {
    $virtualPermission = READ_ACCESS;
} else {
    $virtualPermission = $actualPermission;
}

// setup the homepage handler
$homepageHandler = new Homepage($category->getId());

// this category doesn't have a template, therefore doesn't have a homepage: redirect to resource browser.
if (!$homepageHandler->getTemplate()) {
    $this->localRedir('/resources/'.$category->getId());
}

// moving forward! log the pageview
$mapper = new Schoolbox\Resource\CategoryLogMapper();
$mapper->incrementHitcount($_SESSION['user'], $category->getId());
$categoryMapper->logPageView($category);

/* BUILD PAGE */
$this->setFile(TEMPLATE_content, 'homepage/index.html');
$this->setTitle($category->getName());
$this->setBlock('custom_head', 'modify_layout', 'modify_layout_block');
$this->setBlock('custom_head', 'allowed_files', 'allowed_files_block');
$this->setBlock(TEMPLATE_content, 'edit_page', 'edit_page_block');
$this->setBlock(TEMPLATE_content, 'page_controls', 'page_controls_block');
$this->setBlock('page_controls', 'manage_class', 'manage_class_block');
$this->setBlock(TEMPLATE_content, 'readonly_switch_on', 'readonly_switch_on_block');
$this->setBlock(TEMPLATE_content, 'readonly_switch_off', 'readonly_switch_off_block');

$this->setBlock(TEMPLATE_content, 'customise_page', 'customise_page_block');
$this->setBlock('custom_head', 'colour_list', 'colour_list_block');

/* BUILD COMPONENTRY */
$componentFactory = new Component_Homepage_Factory(new \Schoolbox\User(), $this);
$componentFactory->setMaximumPermission($virtualPermission);
$componentCollection = $componentFactory->buildComponentList($category->getId());

// Decide which of parent/student view butonyes to show, if either
$this->setVar('readonly_switch_on_block', '');
$this->setVar('readonly_switch_off_block', '');
if ($actualPermission >= WRITE_ACCESS) {
    if ($_GET['readonly']) {
        $this->parse('readonly_switch_off_block', 'readonly_switch_off');
    } else {
        $this->parse('readonly_switch_on_block', 'readonly_switch_on');
    }
}

// User has elevated priviliges: can write to page
if ($virtualPermission >= WRITE_ACCESS) {
    $this->setBlock('customise_page', 'homepage_component_tab', 'homepage_component_tab_block');
    $this->setBlock('customise_page', 'homepage_component_page', 'homepage_component_page_block');
    $this->setBlock('customise_page', 'homepage_color_selector', 'homepage_color_selector_block');
    $this->setBlock('customise_page', 'homepage_column_selector', 'homepage_column_selector_block');
    $this->setBlock('homepage_component_page', 'homepage_component', 'homepage_component_block');
    $this->parse('edit_page_block', 'edit_page');
    $this->parse('modify_layout_block', 'modify_layout');
    $this->parse('customise_page_block', 'customise_page');


    if (Cfg::get('homepage_enable_colour')) {
        $this->parse('homepage_color_selector_block', 'homepage_color_selector');
        $this->parse('homepage_column_selector_block', 'homepage_column_selector');
    } else {
        $this->setVar('homepage_color_selector_block', '');
        $this->setVar('homepage_column_selector_block', '');
    }

    $definitionCollection = $componentFactory->getDefinitionList('component_category');

    foreach ($definitionCollection as $definition) {
        /** @var Component_Definition $definition */
        foreach ($componentCollection as $inst) {
            /** @var Component_ControllerAbstract $inst */
            if ($inst->getInstance()->getDefinitionId() == $definition->getId()) {
                $definition->incrementInstanceCount();
            }
        }
    }

    if ($total = $definitionCollection->count()) {
        $i = 0;
        $previous = new Component_Definition();
        foreach ($definitionCollection as $component) {
            /** @var Component_Definition $component */
            ++$i;
            $class = '';
            if ($component->getMultiple() == 'false') {
                $class = 'one-only ';
                if ($component->getMultiple() == 'false' && $component->getInstanceCount() > 0) {
                    $class .= 'disabled ';
                }
            }

            $class = trim($class);

            $output = array(
                'HOMEPAGE_COMPONENT_ID' => Scrub::text($component->getId()),
                'HOMEPAGE_COMPONENT_NAME' => Scrub::text($component->getName()),
                'HOMEPAGE_COMPONENT_COUNT' => $component->getInstanceCount(),
                'HOMEPAGE_COMPONENT_DESCRIPTION' => Scrub::text($component->getDescription()),
                'HOMEPAGE_COMPONENT_CLASS' => str_replace('\\', '_', $component->getClass()),
                'HOMEPAGE_COMPONENT_MULTIPLE_STATE' => $class,
                'HOMEPAGE_COMPONENT_CATEGORY_ID' => $previous->getComponentCategory(),
            );

            $this->setVar($output);

            if ($component->getComponentCategory() != $previous->getComponentCategory() && $i > 1) {
                $this->parse('homepage_component_page_block', 'homepage_component_page', true);
                $this->setVar('homepage_component_block', '');
                $this->parse('homepage_component_block', 'homepage_component', true);

                // last rotation
                if ($total == $i) {
                    $this->setVar('HOMEPAGE_COMPONENT_CATEGORY_ID', $component->getComponentCategory());
                    $this->parse('homepage_component_page_block', 'homepage_component_page', true);
                }
            } else {
                // last rotation
                if ($total == $i) {
                    $this->parse('homepage_component_block', 'homepage_component', true);
                    $this->setVar('HOMEPAGE_COMPONENT_CATEGORY_ID', $component->getComponentCategory());
                    $this->parse('homepage_component_page_block', 'homepage_component_page', true);
                } else {
                    $this->parse('homepage_component_block', 'homepage_component', true);
                }
            }

            $previous = $component;

            //
            // This was set to $component->componentCategoryName - a non-existent value
            $tabList[$component->getComponentCategory()]['name'] = $component->getComponentCategoryName();
            $tabList[$component->getComponentCategory()]['count']++;

        }
    } else {
        $this->setVar(array(
            'homepage_component_block' => '',
            'homepage_component_page_block' => 'No components are available for you to add.',
            'homepage_component_tab_block' => '',
        ));
    }

    if (isset($tabList) && count($tabList)) {
        foreach ($tabList as $id => $tab) {
            $output = array(
                'COMPONENT_CATEGORY_ID' => $id,
                'COMPONENT_CATEGORY_NAME' => Scrub::text($tab['name']),
                'COMPONENT_CATEGORY_COUNT' => $tab['count']
            );

            $this->setVar($output);
            $this->parse('homepage_component_tab_block', 'homepage_component_tab', true);
        }
    }


    // Colors (original)
    $listColor = $homepageHandler->getColorList();

    $this->setVar('COLOUR_ARRAY', 'original');
    $this->setVar($listColor);
    $this->parse('colour_list_block', 'colour_list', true);

    // Colors (picker)
    $this->setVar('COLOUR_ARRAY', 'pickers');
    $this->setVar($listColor);
    $this->parse('colour_list_block', 'colour_list', true);

    // Defaults
    $defaultColour = array(
        'head_font' => Cfg::get('homepage_colour_head_font'),
        'head_bg' => Cfg::get('homepage_colour_head_bg'),
        'font' => Cfg::get('homepage_colour_font'),
        'bg_primary' => Cfg::get('homepage_colour_bg_primary'),
        'bg_secondary' => Cfg::get('homepage_colour_bg_secondary'),
        'link' => Cfg::get('homepage_colour_link'),
        'hover' => Cfg::get('homepage_colour_hover')
    );

    $columns = array('top', 'left', 'right');
    $this->setVar('COLOUR_ARRAY', 'defaults');

    foreach ($columns as $col) {
        foreach ($defaultColour as $field => $colour) {
            $this->setVar($col . '_' . $field, $colour);
        }
    }

    $this->parse('colour_list_block', 'colour_list', true);

    // Template control
    $template = $homepageHandler->getTemplate();
    for ($x = 1; $x <= 4; $x++) {
        $this->setVar("CURRENT_TEMPLATE_$x", $template == $x ? 'current' : '');
    }
} else {
    $this->setVar(array(
        'edit_page_block' => '',
        'modify_layout_block' => '',
        'customise_page_block' => '',
        'colour_list_block' => '',
    ));
}

//Setup control buttons
$manageFolderController = new Component_Homepage_ManageFolderController(
    new Component_Instance(),
    $_SESSION['user'],
    $this
);
$manageFolderController->setMaximumPermission($virtualPermission);
$manageFolderController->setup($category, array());
$manageFolderController->execute();
$manageFolderController->renderTemplate('page_controls');

//Setup class list buttons
$manageClassController = new Component_Homepage_ManageClassController(
    new Component_Instance(),
    $_SESSION['user'],
    $this
);
$manageClassController->setMaximumPermission($virtualPermission);
$manageClassController->setup($category, array());
$manageClassController->execute();
if ($manageClassController->renderTemplate('manage_class')) {
    $this->parse('manage_class_block', 'manage_class');
} else {
    $this->setVar('manage_class_block', '');
}

$this->parse('page_controls_block', 'page_controls');

$codeString = '';
if ($_SESSION['user']->getRole()->isStaff()) {
    if ($category->getCodes()->count() > 4) {
        //Get first 4 codes, clone cause its a mutable collection
        $subCodes = clone $category->getCodes();
        $subCodes = $subCodes->slice(0, 4);
        foreach ($subCodes as $subCode) {
            $subCodeString[] = $subCode->getCode();
        }
        $codeString = Scrub::text(implode(',', $subCodeString).'....');
    } else {
        $codeString = Scrub::text($category->getCodeString());
    }
}

$allWidths = $homepageHandler->getColumnWidths();
$this->setVar(array(
    'HEADING' => Scrub::text($category->getName()) . ($codeString ? ' (' . $codeString . ')' : ''),
    'CODE' => urlencode(urlencode($category->getCodeString())),
    'ID' => $category->getId(),
    'USER_TOKEN' => $_SESSION['key'],
));

// Colors (original)
$listColor = $homepageHandler->getColorList();

$this->setVar('COLOUR_ARRAY', 'original');
$this->setVar($listColor);
$this->parse('colour_list_block', 'colour_list', true);


// Colors (picker)
$this->setVar('COLOUR_ARRAY', 'pickers');
$this->setVar($listColor);
$this->parse('colour_list_block', 'colour_list', true);


// Defaults
$defaultColour = array(
    'head_font' => Cfg::get('homepage_colour_head_font'),
    'head_bg' => Cfg::get('homepage_colour_head_bg'),
    'font' => Cfg::get('homepage_colour_font'),
    'bg_primary' => Cfg::get('homepage_colour_bg_primary'),
    'bg_secondary' => Cfg::get('homepage_colour_bg_secondary'),
    'link' => Cfg::get('homepage_colour_link'),
    'hover' => Cfg::get('homepage_colour_hover')
);

$columns = array('top', 'left', 'right');

$this->setVar('COLOUR_ARRAY', 'defaults');

foreach ($columns as $col) {
    foreach ($defaultColour as $field => $colour) {
        $this->setVar($col . '_' . $field, $colour);
    }
}
$this->parse('colour_list_block', 'colour_list', true);


// Widths
$allWidths = $homepageHandler->getColumnWidths();

$leftWidth = round($allWidths['left_column_width'] * 12 / 100);

$this->setVar(array(
    'TEMPLATE_LEFT_COLUMN_WIDTH'  => $leftWidth,
    'TEMPLATE_RIGHT_COLUMN_WIDTH' => 12 - $leftWidth,
));

$colours = $homepageHandler->getColorList();
$this->setVar($colours);

// output MIMEs
$allowedFiles = new AllowedFile;
$allowedList = $allowedFiles->getAll();

if (is_array($allowedList)) {
// append link icon to results
    $allowedList[]['iconFile'] = "link.png";
    $last = '';

    foreach ($allowedList as $allowed) {
        if ($last != $allowed["iconFile"]) {
            $output = array(
                "NAME" => $allowedFiles->getClass($allowed["iconFile"]),
                "ICON" => $allowed["iconFile"],
            );

            $this->setVar($output);
            $this->parse("allowed_files_block", "allowed_files", true);

            $last = $allowed["iconFile"];
        }
    }
} else {
    $this->setVar('allowed_files_block', '');
}


/* PARSE COMPONENTRY */
$occupiedPositionList = array();
foreach ($componentCollection as $component) {
    /** @var Component_Homepage_ControllerAbstract $component */
    $instance       = $component->getInstance();
    $templateTarget = "component_position_{$instance->getPosition()}";
    $templateHandle = "component_{$instance->getDefinitionId()}";


    $component->setup($category, $instance->getConfigArray());

    if ($component->execute() !== false) {
        if ($component->renderTemplate($templateHandle) !== false) {
            if (!in_array($instance->getPosition(), $occupiedPositionList)) {
                $occupiedPositionList[] = $instance->getPosition();
            }

            $this->parse($templateTarget, $templateHandle, true);
        }
    }
}

$emptyPositionList = array_diff(array('top', 'left', 'right','bottom'), $occupiedPositionList);

foreach ($emptyPositionList as $emptyPosition) {
    $this->setVar("component_position_{$emptyPosition}", '');
}
