<?php
/**
 *
 * @author      Alex Tworowsky <alex.tworowsky@alaress.com.au>
 * @copyright   2011 Alaress Pty Ltd
 * @link        http://www.alaress.com.au
 * @version     SVN: $Id$
 *
 */
$this->setFile(TEMPLATE_content, 'homepage/index.html');

$groupID = $_SESSION['gid'];
$catHandler = new Category();

// retrieve the category ID from the URI, must be an integer and at least 1 character long
if (preg_match('/^[1-9]+[0-9]*$/', $this->arg[0])) {
    $category = $catHandler->getByID($this->arg[0]);
} else {
    $category = null;
}

// if no category was found by ID, attempt a lookup by code
if (!is_array($category)) {
    $code = implode('/', $this->arg);
    $category = $catHandler->getByCode(urldecode(urldecode($code)));
}

// if we still have no category, advise that these are not the droids you're looking for
if (!isset($category['id'])) {
    error(18);
}

$categoryID = $category['id'];

// confirm that the user has permission to view this page
$g = new Group();
if (($permission = $g->getPermission($categoryID)) < READ_ACCESS) {
    error(14);
}

// setup the homepage handler
$homepageHandler = new Homepage($categoryID);

// this category doesn't have a template, therefore doesn't have a homepage: redirect to resource browser.
if(!$homepageHandler->getTemplate()) {
    $this->localRedir('/resources/'.$categoryID);
}

// moving forward! log the pageview
$catHandler->logPageView($categoryID);


/* BUILD PAGE */
$this->setTitle($category['name']);
$this->setBlock('custom_head', 'modify_layout', 'modify_layout_block');
$this->setBlock('custom_head', 'allowed_files', 'allowed_files_block');
$this->setBlock(TEMPLATE_content, 'edit_page', 'edit_page_block');
$this->setBlock(TEMPLATE_content, 'customise_page', 'customise_page_block');
$this->setBlock('custom_head', 'colour_list', 'colour_list_block');

/* BUILD COMPONENTRY */
$componentFactory = new Component_Homepage_Factory(new User, $this);
$componentCollection = $componentFactory->buildComponentList($categoryID);

// User has elevated priviliges: can write to page
if ($permission >= WRITE_ACCESS) {
    $this->setBlock('customise_page', 'homepage_component_tab', 'homepage_component_tab_block');
    $this->setBlock('customise_page', 'homepage_component_page', 'homepage_component_page_block');
    $this->setBlock('homepage_component_page', 'homepage_component', 'homepage_component_block');
    $this->parse('edit_page_block', 'edit_page');
    $this->parse('modify_layout_block', 'modify_layout');
    $this->parse('customise_page_block', 'customise_page');

    $definitionCollection = $componentFactory->getDefinitionList('component_category');

    foreach ($definitionCollection as $definition) {
        $definition->instanceCount = 0;
        foreach ($componentCollection as $inst) {
            if ($inst->definitionId == $definition->id) {
                ++$definition->instanceCount;
            }
        }
    }

    if ($total = $definitionCollection->count()) {
        $i = 0;
        $previous = null;
        foreach ($definitionCollection as $component) {
            ++$i;
            $class = '';
            if ($component->multiple == 'false') {
                $class = 'one-only ';
                if ($component->multiple == 'false' && $component->instanceCount > 0) {
                    $class .= 'disabled ';
                }
            }

            $class = trim($class);

            $output = array(
                'HOMEPAGE_COMPONENT_ID' => Scrub::text($component->id),
                'HOMEPAGE_COMPONENT_NAME' => Scrub::text($component->name),
                'HOMEPAGE_COMPONENT_COUNT' => $component->instanceCount,
                'HOMEPAGE_COMPONENT_DESCRIPTION' => Scrub::html($component->description),
                'HOMEPAGE_COMPONENT_CLASS' => $class,
                'HOMEPAGE_COMPONENT_CATEGORY_ID' => $previous->componentCategory,
            );

            $this->setVar($output);

            if ($component->componentCategory != $previous->componentCategory && $i > 1) {
                $this->parse('homepage_component_page_block', 'homepage_component_page', true);
                $this->setVar('homepage_component_block', '');
                $this->parse('homepage_component_block', 'homepage_component', true);

                // last rotation
                if ($total == $i) {
                    $this->setVar('HOMEPAGE_COMPONENT_CATEGORY_ID', $component->componentCategory);
                    $this->parse('homepage_component_page_block', 'homepage_component_page', true);
                }
            } else {
                // last rotation
                if ($total == $i) {
                    $this->parse('homepage_component_block', 'homepage_component', true);
                    $this->setVar('HOMEPAGE_COMPONENT_CATEGORY_ID', $component->componentCategory);
                    $this->parse('homepage_component_page_block', 'homepage_component_page', true);
                } else {
                    $this->parse('homepage_component_block', 'homepage_component', true);
                }
            }

            $previous = $component;

            $tabList[$component->componentCategory]['name'] = $component->componentCategoryName;
            $tabList[$component->componentCategory]['count']++;

        }
    }
    else {
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
                'COMPONENT_CATEGORY_NAME' => $tab['name'],
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

//set the page-level parameters in the template
$categoryCodes = $catHandler->getCodeForCategory($categoryID);
$code = $categoryCodes['codes'];
$codeArray = explode(',', $code);

if(count($codeArray) > 4) {
    $codeString = Scrub::text(implode(',',array_slice($codeArray, 0, 4)).'....');
}
else {
    $codeString = Scrub::text($code);
}

$allWidths = $homepageHandler->getColumnWidths();

$this->setVar(array(
    'HEADING' => Scrub::text($category['name']) . (isset($code) ? ' (' . $codeString . ')' : ''),
    'CODE' => urlencode(urlencode($code)),
    'ID' => $categoryID,
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

$this->setVar(array(
    'TEMPLATE_LEFT_COLUMN_WIDTH' => $allWidths['left_column_width'],
    'TEMPLATE_RIGHT_COLUMN_WIDTH' => $allWidths['right_column_width'],
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
    $templateTarget = "component_position_{$component->position}";
    $templateHandle = "component_{$component->definitionId}";

    $component->setup($category, $component->instance->getConfigArray());

    if ($component->execute() !== false) {
        if ($component->renderTemplate($templateHandle) !== false) {
            if (!in_array($component->instance->position, $occupiedPositionList)) {
                $occupiedPositionList[] = $component->instance->position;
            }

            $this->parse($templateTarget, $templateHandle, true);
        }
    }
}

$emptyPositionList = array_diff(array('top', 'left', 'right'), $occupiedPositionList);

foreach ($emptyPositionList as $emptyPosition) {
    $this->setVar("component_position_{$emptyPosition}", '');
}
