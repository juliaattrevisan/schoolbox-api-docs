<?php
/************************************************************
 *														   *
 *		 Copyright (c) 2002-2003 Lektec Consulting		 *
 *														   *
 ************************************************************/

define('MAX_COLUMN_COUNT', 3);

require_once 'include/stdfuncs.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/user.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/group.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/allowed-file.php';

$userHandler = new User();
$groupHandler = new Group();
$eportfolioHandler = new Eportfolio_Container($this->arg[0]);
$allowedFiles = new AllowedFile();

/* Check that the user exists
 */
$user = $userHandler->getById($this->arg[0]);

$userID = $user['uid'];

if (!$userID) {
	error(31);
}

if (!$eportfolioHandler->isEnabled($userID)) {
	error(100);
}

/* Check permissions - only superuser, staff, the eportfolio owner and their
 * parents can view it.
 */

// Firstly, can you edit it?
if ($eportfolioHandler->canEdit($userID)) {

	/* Render templates
	 */
	$personName = $user['firstname'] . ' ' . $user['lastname'];
	$pageName = $personName . '\'s ePortfolio';
	$template = $eportfolioHandler->getTemplate($this->arg[0]);

	$this->setFile(TEMPLATE_content, 'eportfolio/template.html');
	$this->setTitle($pageName);
	$this->setTab('eportfolio');

	$this->setBlock('custom_head', 'modify_layout', 'modify_layout_block');
	$this->setBlock('custom_head', 'allowed_files', 'allowed_files_block');
	$this->setBlock(TEMPLATE_content, 'edit_page', 'edit_page_block');
	$this->setBlock(TEMPLATE_content, 'customise_page', 'customise_page_block');

	$this->setBlock('customise_page', 'homepage_component_tab', 'homepage_component_tab_block');
	$this->setBlock('customise_page', 'homepage_component_page', 'homepage_component_page_block');
	$this->setBlock('homepage_component_page', 'homepage_component', 'homepage_component_block');

	$this->parse('edit_page_block', 'edit_page');
	$this->parse('modify_layout_block', 'modify_layout');
	$this->parse('customise_page_block', 'customise_page');

	/* Output components and component tabs */
	$componentList = $eportfolioHandler->getComponentList();

	if (is_array($componentList)) {
		$total = count($componentList);
		$i = 0;

		foreach ($componentList as $component) {
			$i++;
			$class = '';

			if ($component['allowMultiple'] == 'false' && $component['componentCount'] > 0) {
				$class = 'disabled ';
			}
			if ($component['allowMultiple'] == 'false') {
				$class .= 'one-only';
			}
			else {
				$class = '';
			}

			$this->setVar(array(
				'HOMEPAGE_COMPONENT_ID' => Scrub::text($component['id']),
				'HOMEPAGE_COMPONENT_NAME' => Scrub::text($component['name']),
				'HOMEPAGE_COMPONENT_COUNT' => $component['componentCount'],
				'HOMEPAGE_COMPONENT_DESCRIPTION' => Scrub::text($component['description']),
				'HOMEPAGE_COMPONENT_CLASS' => $class,
				'HOMEPAGE_COMPONENT_CATEGORY_ID' => $previous['categoryID']
			));

			if ($component['categoryName'] != $previous['categoryName'] && $i > 1) {
				$this->parse('homepage_component_page_block', 'homepage_component_page', true);
				$this->setVar('homepage_component_block', '');
				$this->parse('homepage_component_block', 'homepage_component', true);

				/* last rotation */
				if ($total == $i) {
					$this->setVar('HOMEPAGE_COMPONENT_CATEGORY_ID', $component['categoryID']);
					$this->parse('homepage_component_page_block', 'homepage_component_page', true);
				}

			}
			else if ($total == $i) {
				/* last rotation */
				$this->parse('homepage_component_block', 'homepage_component', true);
				$this->setVar('HOMEPAGE_COMPONENT_CATEGORY_ID', $component['categoryID']);
				$this->parse('homepage_component_page_block', 'homepage_component_page', true);
			}
			else {
				$this->parse('homepage_component_block', 'homepage_component', true);
			}

			$previous = $component;

			$tabList[$component['categoryID']]['name'] = $component['categoryName'];
			$tabList[$component['categoryID']]['count']++;
		}
	}

	foreach ($tabList as $id => $tab) {
		$this->setVar(array(
			'COMPONENT_CATEGORY_ID' => $id,
			'COMPONENT_CATEGORY_NAME' => $tab['name'],
			'COMPONENT_CATEGORY_COUNT' => $tab['count']
		));

		$this->parse('homepage_component_tab_block', 'homepage_component_tab', true);
	}

	$this->setBlock('custom_head', 'colour_list', 'colour_list_block');

	/* Widths
	 */
	$allWidths = $eportfolioHandler->getColumnWidths();

	$this->setVar(array(
		'TEMPLATE_LEFT_COLUMN_WIDTH' => $allWidths['left_column_width'],
		'TEMPLATE_RIGHT_COLUMN_WIDTH' => $allWidths['right_column_width'],
	));

	/* Colors
	 */
	$listColor = $eportfolioHandler->getColorList();
	$this->setVar('COLOUR_ARRAY', 'original');
	$this->setVar($listColor);
	$this->parse('colour_list_block', 'colour_list', true);

	/* Colors
	 */
	$this->setVar('COLOUR_ARRAY', 'pickers');
	$this->setVar($listColor);
	$this->parse('colour_list_block', 'colour_list', true);

	/* Defaults
	 */
	$defaultColour = array('head_font'=>Cfg::get('homepage_colour_head_font'),
                            'head_bg'=>Cfg::get('homepage_colour_head_bg'),
                            'font'=>Cfg::get('homepage_colour_font'),
                            'bg_primary'=>Cfg::get('homepage_colour_bg_primary'),
                            'bg_secondary'=>Cfg::get('homepage_colour_bg_secondary'),
                            'link'=>Cfg::get('homepage_colour_link'),
                            'hover'=>Cfg::get('homepage_colour_hover')
                           );
    $columns = array('top','left','right');
	$this->setVar('COLOUR_ARRAY', 'defaults');
    foreach($columns as $col){
	    foreach($defaultColour as $field=>$colour) {
            $this->setVar($col.'_'.$field, $colour);
        }
    }
	$this->parse('colour_list_block', 'colour_list', true);

	/* Template control
	 */
	$template = $eportfolioHandler->getTemplate();

	for ($x = 1; $x <= 4; $x++) {
		$this->setVar(array(
			"CURRENT_TEMPLATE_$x" => $template == $x ? 'current' : '',
		));
	}

	/* Apply theme colours
	 */
	$this->setVar($eportfolioHandler->getColorList());

	/* Replace other tokens
	 */
	$this->setVar(array(
		'HEADING' => $pageName,
		'PERSON_NAME' => $personName,
		'ID' => $userID,
		'TEMPLATE' => 'template' . $template['template'],
		'SYNERGY_ID' => $user['synergy_id'],
	));

	/* Has elevated privileges, can write to page
	 */
	if ($_SESSION['id'] == $this->arg[0] || $groupHandler->isSuperuser($_SESSION['id'])) {
		$this->parse('edit_page_block', 'edit_page');
		$this->parse('modify_layout_block', 'modify_layout');
	}
	else {
		$this->setVar('edit_page_block', '');
		$this->setVar('modify_layout_block', '');
	}

	/* Always include private notes for this eportfolio, which can only be seen
	 * by parents, staff and superusers.
	 */
	include($_SERVER['DOCUMENT_ROOT'] . '/eportfolio/outputPrivateNotes.php');

	/* Output components
	 */
	$componentList = $eportfolioHandler->getComponents();

	if (is_array($componentList)) {
		$componentCount = 0;

		foreach ($componentList as $component) {
			$componentCount++;
			$componentHandle = $component['name'] . $componentCount;
			$j = 0;

			/* This array provides default sorting capabilities for each component
			 * in the homepage. They can be overridden in each of the output?.php
			 * homepage component files, to use their own sorting fields.
			 */
			$sortOptions = array('Name', 'Date');

			/* Note some components' extensions are .htm, where others are .html so
			 * if you are seemingly missing a template, check the extension.
			 */
			$this->setFile($componentHandle, 'eportfolio/components/' . $component['filename']);
			$this->setBlock($componentHandle, 'config', 'config_block');

			$this->setVar('COMPONENT_HOMEPAGE_ID', $component['eportfolioComponentLocationID']);
			$this->setVar('COMPONENT_ID', $component['component']);

			$default = unserialize($component['dfconfig']);
			$config = unserialize($component['config']);

			if (is_file($_SERVER['DOCUMENT_ROOT'] . '/eportfolio/' . $component['method'] . '.php')) {
				include($_SERVER['DOCUMENT_ROOT'] . '/eportfolio/' . $component['method'] . '.php');
			}

			if (is_array($default)) {
				foreach ($default as $name => $value) {
					switch ($name) {
						case "size":
							$this->setBlock("config", "size", "size_block" . $componentCount);

							for ($i = 0; $i <= 10; $i++) {
								$this->setVar('COMPONENT_CONFIG_SIZE_NAME', $i == 0 ? 'All' : $i);
								$this->setVar('COMPONENT_CONFIG_SIZE_VALUE', $i);
								$this->setVar('COMPONENT_CONFIG_SIZE_SELECTED', $i == $config[$name] ? ' selected="selected"' : '');
								$this->parse("size_block" . $componentCount, "size", true);
							}

							break;

						case "image":
							$this->setBlock("config", "image", "image_block" . $componentCount);

							$imageList = $eportfolioHandler->getImages($userID);

							if (is_array($imageList)) {
								foreach ($imageList as $image) {
									$this->setVar('COMPONENT_CONFIG_IMAGE', $image['description']);
									$this->setVar('COMPONENT_CONFIG_IMAGE_SELECTED', $image['description'] == $config[$name] ? ' selected="selected"' : '');
									$this->parse("image_block" . $componentCount, "image", true);
								}
							}
							break;

						case "sort":
							$this->setBlock("config", "sort", "sort_block" . $componentCount);

							foreach ($sortOptions as $sort) {
								$this->setVar('COMPONENT_CONFIG_SORT_NAME', $sort);
								$this->setVar('COMPONENT_CONFIG_SORT_VALUE', $sort);
								$this->setVar('COMPONENT_CONFIG_SORT_SELECTED', $sort == $config[$name] ? ' selected="selected"' : '');
								$this->parse("sort_block" . $componentCount, "sort", true);
							}

							break;

						case "sortdir":
							$this->setBlock("config", "sortdir", "sortdir_block" . $componentCount);
							$sortOptions = array('Asc', 'Desc');

							foreach ($sortOptions as $sort) {
								$this->setVar('COMPONENT_CONFIG_SORTDIR_NAME', $sort);
								$this->setVar('COMPONENT_CONFIG_SORTDIR_VALUE', $sort);
								$this->setVar('COMPONENT_CONFIG_SORTDIR_SELECTED', $sort == $config[$name] ? ' selected="selected"' : '');
								$this->parse('sortdir_block' . $componentCount, 'sortdir', true);
							}

							break;

						default:
							$this->setVar('COMPONENT_CONFIG_' . strtoupper($name), $config[$name]);
							break;
					}
				}
			}

			$this->parse('config_block', 'config');

			if ($componentHandle) {
				$this->parse('COLUMN' . $component['column'], $componentHandle, true);
				$columnFlag[$component['column']] = true;
			}
		}
	}

	/* Set any columns with nothing in them to null
	 */
	for ($i = 1; $i <= MAX_COLUMN_COUNT; $i++) {
		if (!$columnFlag[$i]) {
			$this->setVar('COLUMN' . $i, '');
		}
	}

	/* Log page view
		 */
	$eportfolioHandler->logPageView($userID);
}

// If you can't edit it, can you view it?
elseif ($eportfolioHandler->canView($userID)) {

    /* Render templates
      */
    $personName = $user['firstname'] . ' ' . $user['lastname'];
    $pageName = $personName . '\'s ePortfolio';
    $template = $eportfolioHandler->getTemplate($this->arg[0]);

    $this->setFile(TEMPLATE_content, 'eportfolio/template.html');
    $this->setTitle($pageName);
    $this->setTab('eportfolio');

    $this->setBlock('custom_head', 'modify_layout', 'modify_layout_block');
    $this->setBlock('custom_head', 'allowed_files', 'allowed_files_block');
    $this->setBlock(TEMPLATE_content, 'edit_page', 'edit_page_block');
    $this->setBlock(TEMPLATE_content, 'customise_page', 'customise_page_block');

    $this->setBlock('customise_page', 'homepage_component_tab', 'homepage_component_tab_block');
    $this->setBlock('customise_page', 'homepage_component_page', 'homepage_component_page_block');
    $this->setBlock('homepage_component_page', 'homepage_component', 'homepage_component_block');

    $this->setVar('edit_page_block', '');
    $this->setVar('modify_layout_block', '');
    $this->setVar('customise_page_block', '');

    /* Output components and component tabs */
    $componentList = $eportfolioHandler->getComponentList();

    if (is_array($componentList)) {
        $total = count($componentList);
        $i = 0;

        foreach ($componentList as $component) {
            $i++;
            $class = '';

            if ($component['allowMultiple'] == 'false' && $component['componentCount'] > 0) {
                $class = 'disabled ';
            }
            if ($component['allowMultiple'] == 'false') {
                $class .= 'one-only';
            }
            else {
                $class = '';
            }

            $this->setVar(array(
                'HOMEPAGE_COMPONENT_ID' => Scrub::text($component['id']),
                'HOMEPAGE_COMPONENT_NAME' => Scrub::text($component['name']),
                'HOMEPAGE_COMPONENT_COUNT' => $component['componentCount'],
                'HOMEPAGE_COMPONENT_DESCRIPTION' => Scrub::text($component['description']),
                'HOMEPAGE_COMPONENT_CLASS' => $class,
                'HOMEPAGE_COMPONENT_CATEGORY_ID' => $previous['categoryID']
            ));

            if ($component['categoryName'] != $previous['categoryName'] && $i > 1) {
                $this->parse('homepage_component_page_block', 'homepage_component_page', true);
                $this->setVar('homepage_component_block', '');
                $this->parse('homepage_component_block', 'homepage_component', true);

                /* last rotation */
                if ($total == $i) {
                    $this->setVar('HOMEPAGE_COMPONENT_CATEGORY_ID', $component['categoryID']);
                    $this->parse('homepage_component_page_block', 'homepage_component_page', true);
                }

            }
            else if ($total == $i) {
                /* last rotation */
                $this->parse('homepage_component_block', 'homepage_component', true);
                $this->setVar('HOMEPAGE_COMPONENT_CATEGORY_ID', $component['categoryID']);
                $this->parse('homepage_component_page_block', 'homepage_component_page', true);
            }
            else {
                $this->parse('homepage_component_block', 'homepage_component', true);
            }

            $previous = $component;

            $tabList[$component['categoryID']]['name'] = $component['categoryName'];
            $tabList[$component['categoryID']]['count']++;
        }
    }

    foreach ($tabList as $id => $tab) {
        $this->setVar(array(
            'COMPONENT_CATEGORY_ID' => $id,
            'COMPONENT_CATEGORY_NAME' => $tab['name'],
            'COMPONENT_CATEGORY_COUNT' => $tab['count']
        ));

        $this->parse('homepage_component_tab_block', 'homepage_component_tab', true);
    }

    $this->setBlock('custom_head', 'colour_list', 'colour_list_block');

    /* Widths
      */
    $allWidths = $eportfolioHandler->getColumnWidths();

    $this->setVar(array(
        'TEMPLATE_LEFT_COLUMN_WIDTH' => $allWidths['left_column_width'],
        'TEMPLATE_RIGHT_COLUMN_WIDTH' => $allWidths['right_column_width'],
    ));

    /* Colors
      */
    $listColor = $eportfolioHandler->getColorList();
    $this->setVar('COLOUR_ARRAY', 'original');
    $this->setVar($listColor);
    $this->parse('colour_list_block', 'colour_list', true);

    /* Colors
      */
    $this->setVar('COLOUR_ARRAY', 'pickers');
    $this->setVar($listColor);
    $this->parse('colour_list_block', 'colour_list', true);

    /* Defaults
      */
    $defaultColour = array('head_font'=>Cfg::get('homepage_colour_head_font'),
        'head_bg'=>Cfg::get('homepage_colour_head_bg'),
        'font'=>Cfg::get('homepage_colour_font'),
        'bg_primary'=>Cfg::get('homepage_colour_bg_primary'),
        'bg_secondary'=>Cfg::get('homepage_colour_bg_secondary'),
        'link'=>Cfg::get('homepage_colour_link'),
        'hover'=>Cfg::get('homepage_colour_hover')
    );
    $columns = array('top','left','right');
    $this->setVar('COLOUR_ARRAY', 'defaults');
    foreach($columns as $col){
        foreach($defaultColour as $field=>$colour) {
            $this->setVar($col.'_'.$field, $colour);
        }
    }
    $this->parse('colour_list_block', 'colour_list', true);

    /* Template control
      */
    $template = $eportfolioHandler->getTemplate();

    for ($x = 1; $x <= 4; $x++) {
        $this->setVar(array(
            "CURRENT_TEMPLATE_$x" => $template == $x ? 'current' : '',
        ));
    }

    /* Apply theme colours
      */
    $this->setVar($eportfolioHandler->getColorList());

    /* Replace other tokens
      */
    $this->setVar(array(
        'HEADING' => $pageName,
        'PERSON_NAME' => $personName,
        'ID' => $userID,
        'TEMPLATE' => 'template' . $template['template'],
        'SYNERGY_ID' => $user['synergy_id'],
    ));

    /* Has elevated privileges, can write to page
      */
    if ($_SESSION['id'] == $this->arg[0] || $groupHandler->isSuperuser($_SESSION['id'])) {
        $this->parse('edit_page_block', 'edit_page');
        $this->parse('modify_layout_block', 'modify_layout');
    }
    else {
        $this->setVar('edit_page_block', '');
        $this->setVar('modify_layout_block', '');
    }

    /* Always include private notes for this eportfolio, which can only be seen
      * by parents, staff and superusers.
      */
    include($_SERVER['DOCUMENT_ROOT'] . '/eportfolio/outputPrivateNotes.php');

    /* Output components
      */
    $componentList = $eportfolioHandler->getComponents();

    if (is_array($componentList)) {
        $componentCount = 0;

        foreach ($componentList as $component) {
            $componentCount++;
            $componentHandle = $component['name'] . $componentCount;
            $j = 0;

            /* This array provides default sorting capabilities for each component
                * in the homepage. They can be overridden in each of the output?.php
                * homepage component files, to use their own sorting fields.
                */
            $sortOptions = array('Name', 'Date');

            /* Note some components' extensions are .htm, where others are .html so
                * if you are seemingly missing a template, check the extension.
                */
            $this->setFile($componentHandle, 'eportfolio/components/' . $component['filename']);
            $this->setBlock($componentHandle, 'config', 'config_block');

            $this->setVar('COMPONENT_HOMEPAGE_ID', $component['eportfolioComponentLocationID']);
            $this->setVar('COMPONENT_ID', $component['component']);

            $default = unserialize($component['dfconfig']);
            $config = unserialize($component['config']);

            $this->setBlock($componentHandle, 'add_file', 'add_file_block');
            $this->setVar('add_file_block', '');
            $this->setBlock($componentHandle, 'modify_text', 'modify_text_block');
            $this->setVar('modify_text_block', '');

            if (is_file($_SERVER['DOCUMENT_ROOT'] . '/eportfolio/' . $component['method'] . '.php')) {
                include($_SERVER['DOCUMENT_ROOT'] . '/eportfolio/' . $component['method'] . '.php');
            }

            if (is_array($default)) {
                foreach ($default as $name => $value) {
                    switch ($name) {
                        case "size":
                            $this->setBlock("config", "size", "size_block" . $componentCount);

                            for ($i = 0; $i <= 10; $i++) {
                                $this->setVar('COMPONENT_CONFIG_SIZE_NAME', $i == 0 ? 'All' : $i);
                                $this->setVar('COMPONENT_CONFIG_SIZE_VALUE', $i);
                                $this->setVar('COMPONENT_CONFIG_SIZE_SELECTED', $i == $config[$name] ? ' selected="selected"' : '');
                                $this->parse("size_block" . $componentCount, "size", true);
                            }

                            break;

                        case "image":
                            $this->setBlock("config", "image", "image_block" . $componentCount);

                            $imageList = $eportfolioHandler->getImages($userID);

                            if (is_array($imageList)) {
                                foreach ($imageList as $image) {
                                    $this->setVar('COMPONENT_CONFIG_IMAGE', $image['description']);
                                    $this->setVar('COMPONENT_CONFIG_IMAGE_SELECTED', $image['description'] == $config[$name] ? ' selected="selected"' : '');
                                    $this->parse("image_block" . $componentCount, "image", true);
                                }
                            }
                            break;

                        case "sort":
                            $this->setBlock("config", "sort", "sort_block" . $componentCount);

                            foreach ($sortOptions as $sort) {
                                $this->setVar('COMPONENT_CONFIG_SORT_NAME', $sort);
                                $this->setVar('COMPONENT_CONFIG_SORT_VALUE', $sort);
                                $this->setVar('COMPONENT_CONFIG_SORT_SELECTED', $sort == $config[$name] ? ' selected="selected"' : '');
                                $this->parse("sort_block" . $componentCount, "sort", true);
                            }

                            break;

                        case "sortdir":
                            $this->setBlock("config", "sortdir", "sortdir_block" . $componentCount);
                            $sortOptions = array('Asc', 'Desc');

                            foreach ($sortOptions as $sort) {
                                $this->setVar('COMPONENT_CONFIG_SORTDIR_NAME', $sort);
                                $this->setVar('COMPONENT_CONFIG_SORTDIR_VALUE', $sort);
                                $this->setVar('COMPONENT_CONFIG_SORTDIR_SELECTED', $sort == $config[$name] ? ' selected="selected"' : '');
                                $this->parse('sortdir_block' . $componentCount, 'sortdir', true);
                            }

                            break;

                        default:
                            $this->setVar('COMPONENT_CONFIG_' . strtoupper($name), $config[$name]);
                            break;
                    }
                }
            }

            $this->setVar('config_block', '');

            if ($componentHandle) {
                $this->parse('COLUMN' . $component['column'], $componentHandle, true);
                $columnFlag[$component['column']] = true;
            }
        }
    }

    /* Set any columns with nothing in them to null
      */
    for ($i = 1; $i <= MAX_COLUMN_COUNT; $i++) {
        if (!$columnFlag[$i]) {
            $this->setVar('COLUMN' . $i, '');
        }
    }

    /* Log page view
          */
    $eportfolioHandler->logPageView($userID);

}
// If you can't even see it, then you rudely get an error page instead.
else {
	error(19);
}

// output mime classes
$allowedList = $allowedFiles->getAll();

if (is_array($allowedList)) {

	// append link icon to resutls
	$allowedList[]['iconFile'] = "html_alt1.png";
	$last = '';

	foreach ($allowedList as $allowed) {
		if ($last != $allowed["iconFile"]) {
			$this->setVar(array(
				'NAME' => $allowedFiles->getClass($allowed["iconFile"]),
				'ICON' => $allowed["iconFile"],
			));

			$this->parse("allowed_files_block", "allowed_files", true);

			$last = $allowed["iconFile"];
		}
	}
}
else {
	$this->setVar('allowed_files_block', '');
}
?>
