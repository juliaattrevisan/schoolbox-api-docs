<?php
/************************************************************
*                                                           *
*         Copyright (c) 2002-2003 Lektec Consulting         *
*                                                           *
************************************************************/

// fix url
require_once 'include/stdfuncs.php';
if (strpos($_SERVER['REQUEST_URI'], ".php?category=") ||
	strpos($_SERVER['REQUEST_URI'], ".php?code=")) {
	$category = isset($_GET['category']) ? $_GET['category'] : $_GET['code'];
	localRedir("/homepage/" . $category);
}

require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/category.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/homepage.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/group.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/allowed-file.php';

$categoryHandler = new Category; 
$groupHandler = new Group(HOMEPAGE_SERVICE);
$allowedFiles = new AllowedFile;

if(preg_match('/^[0-9]+$/',$this->arg[0])) {
	$category = $categoryHandler->getByID($this->arg[0]);
}
if(!is_array($category)) {
	$code = implode('/', $this->arg);
	$category = $categoryHandler->getByCode(urldecode(urldecode($code)));
}


$categoryID = $category['id'];
if (!$categoryID) {
	error(18);
}

$homepageHandler = new Homepage($category['id']);

$categoryHandler->logPageView($categoryID);

define('MAX_COLUMN_COUNT',3);

// check perms
if (($permission = $groupHandler->getPermission($categoryID)) < READ_ACCESS)
	error(14);


if(!isset($category['template']))
	localRedir('/resources/'.$categoryID);

if($category['code']) {
	$enroled = $groupHandler->involvedInSubject($category['code']);
	$code = $category['code'];
}
else
	$enroled = $groupHandler->involvedInGroup($_SESSION['id'], $categoryID);


$this->setFile(TEMPLATE_content, 'homepage/template.html');
$this->setTitle($category['name']);
$this->setTab('resources');

$this->setBlock('custom_head', 'modify_layout', 'modify_layout_block');
$this->setBlock('custom_head', 'allowed_files', 'allowed_files_block');
$this->setBlock(TEMPLATE_content, 'edit_page', 'edit_page_block');
$this->setBlock(TEMPLATE_content, 'customise_page', 'customise_page_block');


$codeCount = count(explode(',',$category['code']));

$output = array('HEADING'				=> scrubTEXT($category['name']) . (isset($code) && $codeCount < 2 ? ' ('.$category['code'].')' : '' ),
				'CODE'					=> urlencode(urlencode($category['code'])),
				'ID'					=> $categoryID,
				'TEMPLATE'				=> 'template'.$category['template'],
				);

$this->setVar($output);

if($permission >= WRITE_ACCESS) { //has elevated priviliges, can write to page
	$this->setBlock('customise_page', 'homepage_component_tab', 'homepage_component_tab_block');
    $this->setBlock('customise_page', 'homepage_component_page', 'homepage_component_page_block');
    $this->setBlock('homepage_component_page', 'homepage_component', 'homepage_component_block');

    $this->parse('edit_page_block', 'edit_page');
	$this->parse('modify_layout_block', 'modify_layout');
    $this->parse('customise_page_block', 'customise_page');




    /* Output components and component tabs */
    $componentList = $homepageHandler->getComponentList();

    if(is_array($componentList)) {
        $total = count($componentList);
        $i = 0;
        foreach($componentList as $component) {
            $i++;
                $class ='';
            if($component['allowMultiple'] == 'false' && $component['componentCount'] > 0)
                $class = 'disabled ';
            if($component['allowMultiple'] == 'false') {
                $class .= 'one-only';
            }
            else
                $class = '';

            $output = array('HOMEPAGE_COMPONENT_ID'   => scrubTEXT($component['id']),
                            'HOMEPAGE_COMPONENT_NAME'   => scrubTEXT($component['name']),
                            'HOMEPAGE_COMPONENT_COUNT'  => $component['componentCount'],
                            'HOMEPAGE_COMPONENT_DESCRIPTION'    => scrubTEXT($component['description']),
                            'HOMEPAGE_COMPONENT_CLASS'  => $class,
                            'HOMEPAGE_COMPONENT_CATEGORY_ID' => $previous['categoryID']);
            $this->setVar($output);

            if($component['categoryName'] != $previous['categoryName'] && $i > 1) {

                $this->parse('homepage_component_page_block', 'homepage_component_page', true);

                $this->setVar('homepage_component_block', '');
                $this->parse('homepage_component_block', 'homepage_component', true);

            	/* last rotation */
                if ($total == $i) {
                    $this->setVar('HOMEPAGE_COMPONENT_CATEGORY_ID', $component['categoryID']);
                    $this->parse('homepage_component_page_block', 'homepage_component_page', true);
                }

            } elseif ($total == $i) {
                /* last rotation */
                $this->parse('homepage_component_block', 'homepage_component', true);
                $this->setVar('HOMEPAGE_COMPONENT_CATEGORY_ID', $component['categoryID']);
                $this->parse('homepage_component_page_block', 'homepage_component_page', true);
            } else {
                $this->parse('homepage_component_block', 'homepage_component', true);
            }

            $previous = $component;

            $tabList[$component['categoryID']]['name'] = $component['categoryName'];
            $tabList[$component['categoryID']]['count']++;

        }
    }

    foreach($tabList as $id => $tab) {
        $output = array('COMPONENT_CATEGORY_ID' => $id,
                        'COMPONENT_CATEGORY_NAME' => $tab['name'],
                        'COMPONENT_CATEGORY_COUNT' => $tab['count']);
        $this->setVar($output);
        $this->parse('homepage_component_tab_block', 'homepage_component_tab', true);
    }

    $this->setBlock('custom_head', 'colour_list', 'colour_list_block');
        // Colors
	$listColor = $homepageHandler->getColorList();
	$this->setVar('COLOUR_ARRAY', 'original');
	$this->setVar($listColor);
	$this->parse('colour_list_block', 'colour_list', true);

        // Colors
	$this->setVar('COLOUR_ARRAY', 'pickers');
	$this->setVar($listColor);
	$this->parse('colour_list_block', 'colour_list', true);


	// defaults
	$defaultColour = unserialize(HOMEPAGE_colors);
	$this->setVar('COLOUR_ARRAY', 'defaults');
	$this->setVar($defaultColour);
	$this->parse('colour_list_block', 'colour_list', true);

    //Template control
	$template = $homepageHandler->getTemplate();
	for($x = 1; $x <= 4; $x++) {
		$output = array("CURRENT_TEMPLATE_$x"	=> $template == $x ? 'current' : '',
						);
		$this->setVar($output);
	}


}	
else {
	$this->setVar('edit_page_block', '');
	$this->setVar('modify_layout_block', '');
    $this->setVar('customise_page_block', '');
}


$colours = $homepageHandler->getColorList();
$this->setVar($colours);

// output mime classes
$allowedList = $allowedFiles->getAll();
if (is_array($allowedList)) {
	
	// append link icon to resutls
	$allowedList[]['iconFile'] = "link.png";
	$last = '';
	foreach ($allowedList as $allowed) {
		if ($last != $allowed["iconFile"]) {
			$output = array("NAME"	=> $allowedFiles->getClass($allowed["iconFile"]),
							"ICON"	=> $allowed["iconFile"],
							);
			
			$this->setVar($output);
			$this->parse("allowed_files_block", "allowed_files", true);
			
			$last = $allowed["iconFile"];
		}
	}
} else
	$this->setVar('allowed_files_block', '');

// output components
$componentList = $categoryHandler->getComponents($categoryID);
if (is_array($componentList)) {
	
	foreach ($componentList as $component) {
		
		$componentHandle = $component['method'] . $component['homepageComponentLocationID'];

      /* This array provides default sorting capabilities for each component
       * in the homepage. They can be overridden in each of the output?.php
       * homepage component files, to use their own sorting fields.
       */
		$sortOptions = array('Name', 'Date');

		$this->setFile($componentHandle, 'homepage/components/'.$component['filename']);
		$this->setBlock($componentHandle, "config", "config_block");

		$this->setVar('COMPONENT_HOMEPAGE_ID', $component['homepageComponentLocationID']);
                $this->setVar('COMPONENT_ID', $component['component']);
		
		$default = unserialize($component['dfconfig']);
		$config = unserialize($component['config']);

		if(is_file($_SERVER['DOCUMENT_ROOT'] . '/homepage/' . $component['method'] . '.php'))
			include($_SERVER['DOCUMENT_ROOT'] . '/homepage/' . $component['method'] . '.php');

        if($permission >= WRITE_ACCESS) {
            if(is_array($default)) {

                foreach($default as $name => $value) {

                    switch ($name) {

                        case "size":
                            $this->setBlock("config", "size", "size_block".$component['homepageComponentLocationID']);
                            for($i=0; $i<=10; $i++) {
                                $this->setVar('COMPONENT_CONFIG_SIZE_NAME', $i == 0 ? 'All' : $i);
                                $this->setVar('COMPONENT_CONFIG_SIZE_VALUE', $i);
                                $this->setVar('COMPONENT_CONFIG_SIZE_SELECTED', $i == $config[$name] ? ' selected="selected"' : '');
                                $this->parse("size_block".$component['homepageComponentLocationID'], "size", true);
                            }
                            break;

                        case "image":
                            $this->setBlock("config", "image", "image_block".$component['homepageComponentLocationID']);

                            $imageList = $categoryHandler->getImages($categoryID);
                            if(is_array($imageList)){
                                foreach ($imageList as $image) {

                                    $this->setVar('COMPONENT_CONFIG_IMAGE', $image['description']);
                                    $this->setVar('COMPONENT_CONFIG_IMAGE_SELECTED', $image['description'] == $config[$name] ? ' selected="selected"' : '');
                                    $this->parse("image_block".$component['homepageComponentLocationID'], "image", true);
                                }
                            }
                            break;

                        case "sort":
                            $this->setBlock("config", "sort", "sort_block".$component['homepageComponentLocationID']);

                            foreach ($sortOptions as $sort)
                            {
                                $this->setVar('COMPONENT_CONFIG_SORT_NAME', $sort);
                                $this->setVar('COMPONENT_CONFIG_SORT_VALUE', $sort);
                                $this->setVar('COMPONENT_CONFIG_SORT_SELECTED', $sort == $config[$name] ? ' selected="selected"' : '');
                                $this->parse("sort_block".$component['homepageComponentLocationID'], "sort", true);
                            }
                            break;

                        case "sortdir":
                            $this->setBlock("config", "sortdir", "sortdir_block".$component['homepageComponentLocationID']);
                            $sortOptions = array('Asc', 'Desc');
                            foreach ($sortOptions as $sort)
                            {
                                $this->setVar('COMPONENT_CONFIG_SORTDIR_NAME', $sort);
                                $this->setVar('COMPONENT_CONFIG_SORTDIR_VALUE', $sort);
                                $this->setVar('COMPONENT_CONFIG_SORTDIR_SELECTED', $sort == $config[$name] ? ' selected="selected"' : '');
                                $this->parse("sortdir_block".$component['homepageComponentLocationID'], "sortdir", true);
                            }
                            break;
                        default:
                            $this->setVar('COMPONENT_CONFIG_' . strtoupper($name), $config[$name]);
                    }
                }
                
            }
             $this->parse("config_block", "config");
		} else
			$this->setVar("config_block", "");	

		if($componentHandle) {
			$this->parse('COLUMN'.$component['column'], $componentHandle, true);
			$columnFlag[$component['column']] = true;
		}
	}
}

//set any columns with nothing in them to null
for($i=1; $i <= MAX_COLUMN_COUNT; $i++){
	if(!$columnFlag[$i])
		$this->setVar('COLUMN'.$i,'');
}


?>
