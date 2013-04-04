<?php
/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   2013 Alaress Pty Ltd
 * @link        http://www.alaress.com.au
 * @version     SVN: $Id$
 *
 * Originally cloned from bugalugs
 */
$this->setFile(TEMPLATE_content, 'eportfolio/index.html');

$groupID = $_SESSION['gid'];
$userHandler = new User();
$user = false;

/* Retrieve the user ID from the URI, must be an integer and at least 1 character long
 */
if (preg_match('/^\d+$/', $this->arg[0])) {
    $user = $userHandler->getByID($this->arg[0]);
}

/* if we still have no user, advise that these are not the droids you're looking for
 */
if (!isset($user['uid'])) {
    error(18);
}

$userId = $user['uid'];
$pageId = $this->arg[1];

/* confirm that the user has permission to view this page
 */
//$g = new Group();
//
//if (($permission = $g->getPermission($categoryID)) < READ_ACCESS) {
//    error(14);
//}

/* Setup the eportfolio handler
 */
$eportfolioHandler = new Eportfolio($userId, $pageId);

if ($eportfolioHandler->getTemplate() === false) {
	die('no template');
}

/* Assemble list of components
 */
$componentFactory = new Component_Eportfolio_Factory(new User, $this);
$componentCollection = $componentFactory->buildComponentList($pageId);

/* Determine access permissions
 * TODO: Currently hardcoded
 */
$permission = 99;

/* Page colours
 */
$colours = $eportfolioHandler->getColorList($pageId);

/* Set up blocks and stuff
 */
$this->setTitle($category['name']);
$this->setBlock('custom_head', 'modify_layout', 'modify_layout_block');
$this->setBlock('custom_head', 'allowed_files', 'allowed_files_block');
$this->setBlock(TEMPLATE_content, 'edit_page', 'edit_page_block');
$this->setBlock(TEMPLATE_content, 'customise_page', 'customise_page_block');
$this->setBlock('custom_head', 'colour_list', 'colour_list_block');

/* Set meta data
 */
$this->setVar(array(
	'HEADING' => Scrub::text($user['firstname'] . ' ' . $user['lastname']),
	'ID' => $user['uid'],
	'USER_TOKEN' => $_SESSION['key'],
	'PAGE_TITLE' => $eportfolioHandler->getPageTitle(),
	'PAGE_ID' => $eportfolioHandler->getPageId(),
));

/* Customise page settings if you are the page owner or superuser
 */
if ($permission >= WRITE_ACCESS) {
    $this->setBlock('customise_page', 'eportfolio_component_tab', 'eportfolio_component_tab_block');
    $this->setBlock('customise_page', 'eportfolio_component_page', 'eportfolio_component_page_block');
    $this->setBlock('eportfolio_component_page', 'eportfolio_component', 'eportfolio_component_block');
    $this->parse('edit_page_block', 'edit_page');
    $this->parse('modify_layout_block', 'modify_layout');
    $this->parse('customise_page_block', 'customise_page');

    $definitionCollection = $componentFactory->getDefinitionList('eportfolio_template');

    foreach ($definitionCollection as $definition) {
        $definition->instanceCount = 0;

		$definition->definition->componentCategory = $definition->identifier;
		$definition->definition->componentCategoryName = $eportfolioHandler->getTemplateNameById($definition->identifier);

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

            $this->setVar(array(
				'EPORTFOLIO_COMPONENT_ID' => Scrub::text($component->id),
				'EPORTFOLIO_COMPONENT_NAME' => Scrub::text($component->definition->name),
				'EPORTFOLIO_COMPONENT_COUNT' => $component->instanceCount,
				'EPORTFOLIO_COMPONENT_DESCRIPTION' => Scrub::text($component->definition->description),
				'EPORTFOLIO_COMPONENT_CLASS' => $class,
				'EPORTFOLIO_COMPONENT_CATEGORY_ID' => $previous->identifier,
			));

            if ($component->definition->componentCategory != $previous->definition->componentCategory && $i > 1) {
                $this->parse('eportfolio_component_page_block', 'eportfolio_component_page', true);
                $this->setVar('eportfolio_component_block', '');
                $this->parse('eportfolio_component_block', 'eportfolio_component', true);

                // last rotation
                if ($total == $i) {
                    $this->setVar('EPORTFOLIO_COMPONENT_CATEGORY_ID', $component->definition->componentCategory);
                    $this->parse('eportfolio_component_page_block', 'eportfolio_component_page', true);
                }
            }
			else {
                // last rotation
                if ($total == $i) {
                    $this->parse('eportfolio_component_block', 'eportfolio_component', true);
                    $this->setVar('EPORTFOLIO_COMPONENT_CATEGORY_ID', $component->definition->componentCategory);
                    $this->parse('eportfolio_component_page_block', 'eportfolio_component_page', true);
                }
				else {
                    $this->parse('eportfolio_component_block', 'eportfolio_component', true);
                }
            }

            $previous = $component;

            $tabList[$component->definition->componentCategory]['name'] = $component->definition->componentCategoryName;
			$tabList[$component->definition->componentCategory]['count']++;
        }
    }
    else {
        $this->setVar(array(
            'eportfolio_component_block' => '',
            'eportfolio_component_page_block' => 'No components are available for you to add.',
            'eportfolio_component_tab_block' => '',
        ));
    }

    if (isset($tabList) && count($tabList)) {
        foreach ($tabList as $id => $tab) {
            $this->setVar(array(
				'COMPONENT_CATEGORY_ID' => $id,
				'COMPONENT_CATEGORY_NAME' => $tab['name'],
				'COMPONENT_CATEGORY_COUNT' => $tab['count']
			));

            $this->parse('eportfolio_component_tab_block', 'eportfolio_component_tab', true);
        }
    }

    // Colors (original)
    $this->setVar('COLOUR_ARRAY', 'original');
    $this->setVar($colours);
    $this->parse('colour_list_block', 'colour_list', true);

    // Colors (picker)
    $this->setVar('COLOUR_ARRAY', 'pickers');
    $this->setVar($colours);
    $this->parse('colour_list_block', 'colour_list', true);

    // Defaults
    $defaultColour = array(
        'head_font' => Cfg::get('eportfolio_colour_head_font'),
        'head_bg' => Cfg::get('eportfolio_colour_head_bg'),
        'font' => Cfg::get('eportfolio_colour_font'),
        'bg_primary' => Cfg::get('eportfolio_colour_bg_primary'),
        'bg_secondary' => Cfg::get('eportfolio_colour_bg_secondary'),
        'link' => Cfg::get('eportfolio_colour_link'),
        'hover' => Cfg::get('eportfolio_colour_hover')
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
	/* What does this even do?
	 */
//    $template = $eportfolioHandler->getTemplate();
//    for ($x = 1; $x <= 4; $x++) {
//        $this->setVar("CURRENT_TEMPLATE_$x", $template == $x ? 'current' : '');
//    }
}
else {
    $this->setVar(array(
        'edit_page_block' => '',
        'modify_layout_block' => '',
        'customise_page_block' => '',
        'colour_list_block' => '',
    ));
}

/* Apply default colours to everything
 */
$defaultColour = array(
	'head_font' => Cfg::get('homepage_colour_head_font'),
	'head_bg' => Cfg::get('homepage_colour_head_bg'),
	'font' => Cfg::get('homepage_colour_font'),
	'bg_primary' => Cfg::get('homepage_colour_bg_primary'),
	'bg_secondary' => Cfg::get('homepage_colour_bg_secondary'),
	'link' => Cfg::get('homepage_colour_link'),
	'hover' => Cfg::get('homepage_colour_hover')
);

$this->setVar('COLOUR_ARRAY', 'defaults');

$columns = array('top', 'left', 'right');

foreach ($columns as $col) {
    foreach ($defaultColour as $field => $colour) {
        $this->setVar($col . '_' . $field, $colour);
    }
}

$this->parse('colour_list_block', 'colour_list', true);

/* Javascript colour values for the original
 */
$this->setVar('COLOUR_ARRAY', 'original');
$this->setVar($colours);
$this->parse('colour_list_block', 'colour_list', true);

/* Javascript colour values for the picker
 */
$this->setVar('COLOUR_ARRAY', 'pickers');
$this->setVar($colours);
$this->parse('colour_list_block', 'colour_list', true);

/* Figure out column widths
 */
$allWidths = $eportfolioHandler->getColumnWidths();

$this->setVar(array(
    'TEMPLATE_LEFT_COLUMN_WIDTH' => $allWidths['left_column_width'],
    'TEMPLATE_RIGHT_COLUMN_WIDTH' => $allWidths['right_column_width'],
));

/* CSS classes for mime types
 */
$allowedFiles = new AllowedFile;
$allowedList = $allowedFiles->getAll();

if (is_array($allowedList)) {

    // append link icon to results
    $allowedList[]['iconFile'] = "link.png";
    $last = '';

    foreach ($allowedList as $allowed) {
        if ($last != $allowed["iconFile"]) {
            $this->setVar(array(
				"NAME" => $allowedFiles->getClass($allowed["iconFile"]),
				"ICON" => $allowed["iconFile"],
			));
            $this->parse("allowed_files_block", "allowed_files", true);

            $last = $allowed["iconFile"];
        }
    }
}
else {
    $this->setVar('allowed_files_block', '');
}

/* PARSE COMPONENTRY */
$occupiedPositionList = array();

foreach ($componentCollection as $component) {
    $templateTarget = "component_position_{$component->position}";
    $templateHandle = "component_{$component->definitionId}";

    $component->setup($user, $component->instance->getConfigArray());

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
