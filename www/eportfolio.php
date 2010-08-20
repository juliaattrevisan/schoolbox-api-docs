<?php
/************************************************************
*                                                           *
*         Copyright (c) 2002-2003 Lektec Consulting         *
*                                                           *
************************************************************/

define('MAX_COLUMN_COUNT', 2);

require_once 'include/stdfuncs.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/user.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/eportfolio.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/group.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/allowed-file.php';

$userHandler = new User();
$eportfolioHandler = new ePortfolio($this->arg[0]);
$allowedFiles = new AllowedFile();

/* Check that the user exists
 */
$user = $userHandler->getById($this->arg[0]);

$userID =& $user['uid'];

if (!$userID) {
	error(31);
}

/* Check permissions - only superuser, staff, the eportfolio owner and their
 * parents can view it.
 */
if ($eportfolioHandler->canEdit($userID)) {

    /* Render templates
     */
    $pageName = $user['firstname'] . ' ' . $user['lastname'] . '\'s ePortfolio';
    $template = $eportfolioHandler->getTemplate($this->arg[0]);

    $this->setFile(TEMPLATE_content, 'eportfolio/template.html');
    $this->setTitle($pageName);
    $this->setTab('resources');

    $this->setBlock('custom_head', 'modify_layout', 'modify_layout_block');
    $this->setBlock('custom_head', 'allowed_files', 'allowed_files_block');
    $this->setBlock(TEMPLATE_content, 'edit_page', 'edit_page_block');

    /* Apply theme colours
     */
    $this->setVar($eportfolioHandler->getColorList());

    /* Replace other tokens
     */
    $this->setVar(array(
        'HEADING' => $pageName,
        'ID' => $userID,
        'TEMPLATE' => 'template'.$template['template'],
    ));

    /* Has elevated privileges, can write to page
     */
    if ($_SESSION['id'] == $this->arg[0]) {
        $this->parse('edit_page_block', 'edit_page');
        $this->parse('modify_layout_block', 'modify_layout');
    }
    else {
        $this->setVar('edit_page_block', '');
        $this->setVar('modify_layout_block', '');
    }

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

            $this->setVar('COMPONENT_ID', $component['component']);

            $default = unserialize($component['dfconfig']);
            $config = unserialize($component['config']);

            if (is_file($_SERVER['DOCUMENT_ROOT'] . '/eportfolio/' . $component['method'] . '.php')) {
                include($_SERVER['DOCUMENT_ROOT'] . '/eportfolio/' . $component['method'] . '.php');
            }

            if (is_array($default)) {
                foreach($default as $name => $value) {
                    switch ($name) {
                        case "size":
                            $this->setBlock("config", "size", "size_block".$componentCount);

                            for ($i=0; $i<=10; $i++) {
                                $this->setVar('COMPONENT_CONFIG_SIZE_NAME', $i == 0 ? 'All' : $i);
                                $this->setVar('COMPONENT_CONFIG_SIZE_VALUE', $i);
                                $this->setVar('COMPONENT_CONFIG_SIZE_SELECTED', $i == $config[$name] ? ' selected="selected"' : '');
                                $this->parse("size_block".$componentCount, "size", true);
                            }

                            break;

                        case "image":
                            $this->setBlock("config", "image", "image_block".$componentCount);

                            $imageList = $eportfolioHandler->getImages($userID);

                            if(is_array($imageList)){
                                foreach ($imageList as $image) {

                                    $this->setVar('COMPONENT_CONFIG_IMAGE', $image['description']);
                                    $this->setVar('COMPONENT_CONFIG_IMAGE_SELECTED', $image['description'] == $config[$name] ? ' selected="selected"' : '');
                                    $this->parse("image_block".$componentCount, "image", true);
                                }
                            }
                            break;

                        case "sort":
                            $this->setBlock("config", "sort", "sort_block".$componentCount);

                            foreach ($sortOptions as $sort) {
                                $this->setVar('COMPONENT_CONFIG_SORT_NAME', $sort);
                                $this->setVar('COMPONENT_CONFIG_SORT_VALUE', $sort);
                                $this->setVar('COMPONENT_CONFIG_SORT_SELECTED', $sort == $config[$name] ? ' selected="selected"' : '');
                                $this->parse("sort_block".$componentCount, "sort", true);
                            }

                            break;

                        case "sortdir":
                            $this->setBlock("config", "sortdir", "sortdir_block".$componentCount);
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
                    }
                }
                $this->parse('config_block', 'config');
            }
            else {
                $this->setVar('config_block', '');
            }

            if ($componentHandle) {
                $this->parse('COLUMN' . $component['column'], $componentHandle, true);
                $columnFlag[$component['column']] = true;
            }
        }
    }

    /* Set any columns with nothing in them to null
     */
    for ($i=1; $i <= MAX_COLUMN_COUNT; $i++){
        if (!$columnFlag[$i]) {
            $this->setVar('COLUMN' . $i, '');
        }
    }

    /* Log page view
     */
    $eportfolioHandler->logPageView($userID);
}
else {
    error(19);
}

return;







//$categoryHandler = new Category;
//$groupHandler = new Group(HOMEPAGE_SERVICE);
//$allowedFiles = new AllowedFile;
//
//if(is_numeric($this->arg[0])) {
//	$category = $categoryHandler->getByID($this->arg[0]);
//}
//if(!is_array($category)) {
//	$code = implode('/', $this->arg);
//	$category = $categoryHandler->getByCode(urldecode(urldecode($code)));
//	$category = $categoryHandler->getByID($category['id']);
//}
//
//
//$categoryID =& $category['id'];
//if (!$categoryID) {
//	error(18);
//}

//$homepageHandler = new Homepage($category['id']);

//$categoryHandler->logPageView($categoryID);

//define('MAX_COLUMN_COUNT',2);

// check perms
//if (($permission = $groupHandler->getPermission($categoryID)) < READ_ACCESS)
//	error(14);


//if(!isset($category['template']))
//	localRedir('/resources/'.$categoryID);

//if($category['code']) {
//	$enroled = $groupHandler->involvedInSubject($category['code']);
//	$code = $category['code'];
//}
//else
//	$enroled = $groupHandler->involvedInGroup($_SESSION['id'], $categoryID);
//
//
//$this->setFile(TEMPLATE_content, 'homepage/template.html');
//$this->setTitle($category['name']);
//$this->setTab('resources');
//
//$this->setBlock('custom_head', 'modify_layout', 'modify_layout_block');
//$this->setBlock('custom_head', 'allowed_files', 'allowed_files_block');
//$this->setBlock(TEMPLATE_content, "edit_page", "edit_page_block");


//$colours = $homepageHandler->getColorList();
//$this->setVar($colours);

//$codeCount = count(explode(',',$category['code']));
//
//$output = array('HEADING'				=> scrubTEXT($category['name']) . (isset($code) && $codeCount < 2 ? ' ('.$category['code'].')' : '' ),
//				'CODE'					=> urlencode(urlencode($category['code'])),
//				'ID'					=> $categoryID,
//				'TEMPLATE'				=> 'template'.$category['template'],
//				);

//$this->setVar($output);

//if($permission >= WRITE_ACCESS) { //has elevated priviliges, can write to page
//	$this->parse("edit_page_block", "edit_page");
//	$this->parse('modify_layout_block', 'modify_layout');
//}
//else {
//	$this->setVar("edit_page_block", "");
//	$this->setVar('modify_layout_block', '');
//}

// output mime classes
$allowedList = $allowedFiles->getAll();
if (is_array($allowedList)) {

	// append link icon to resutls
	$allowedList[]['iconFile'] = "html_alt1.png";
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
//$componentList = $categoryHandler->getComponents($categoryID);
//if (is_array($componentList)) {
//
//	$componentCount = 0;
//	foreach ($componentList as $component) {
//
//		$componentCount++;
//		$componentHandle = $component['name'] . $componentCount;
//		$j =0;
//
//      /* This array provides default sorting capabilities for each component
//       * in the homepage. They can be overridden in each of the output?.php
//       * homepage component files, to use their own sorting fields.
//       */
//		$sortOptions = array('Name', 'Date');
//
//
//		$this->setFile($componentHandle, 'homepage/components/'.$component['filename']);
//		$this->setBlock($componentHandle, "config", "config_block");
//
//		$this->setVar('COMPONENT_ID', $component['component']);
//
//		$default = unserialize($component['dfconfig']);
//		$config = unserialize($component['config']);
//
//		if(is_file($_SERVER['DOCUMENT_ROOT'] . '/homepage/' . $component['method'] . '.php'))
//			include($_SERVER['DOCUMENT_ROOT'] . '/homepage/' . $component['method'] . '.php');
//
//
//		if(is_array($default) && $permission >= WRITE_ACCESS) {
//
//			foreach($default as $name => $value) {
//
//				switch ($name) {
//
//					case "size":
//						$this->setBlock("config", "size", "size_block".$componentCount);
//						for($i=0; $i<=10; $i++) {
//							$this->setVar('COMPONENT_CONFIG_SIZE_NAME', $i == 0 ? 'All' : $i);
//							$this->setVar('COMPONENT_CONFIG_SIZE_VALUE', $i);
//							$this->setVar('COMPONENT_CONFIG_SIZE_SELECTED', $i == $config[$name] ? ' selected="selected"' : '');
//							$this->parse("size_block".$componentCount, "size", true);
//						}
//						break;
//
//					case "image":
//						$this->setBlock("config", "image", "image_block".$componentCount);
//
//						$imageList = $categoryHandler->getImages($categoryID);
//						if(is_array($imageList)){
//							foreach ($imageList as $image) {
//
//								$this->setVar('COMPONENT_CONFIG_IMAGE', $image['description']);
//								$this->setVar('COMPONENT_CONFIG_IMAGE_SELECTED', $image['description'] == $config[$name] ? ' selected="selected"' : '');
//								$this->parse("image_block".$componentCount, "image", true);
//							}
//						}
//						break;
//
//					case "sort":
//						$this->setBlock("config", "sort", "sort_block".$componentCount);
//
//						foreach ($sortOptions as $sort)
//						{
//							$this->setVar('COMPONENT_CONFIG_SORT_NAME', $sort);
//							$this->setVar('COMPONENT_CONFIG_SORT_VALUE', $sort);
//							$this->setVar('COMPONENT_CONFIG_SORT_SELECTED', $sort == $config[$name] ? ' selected="selected"' : '');
//							$this->parse("sort_block".$componentCount, "sort", true);
//						}
//						break;
//
//					case "sortdir":
//						$this->setBlock("config", "sortdir", "sortdir_block".$componentCount);
//						$sortOptions = array('Asc', 'Desc');
//						foreach ($sortOptions as $sort)
//						{
//							$this->setVar('COMPONENT_CONFIG_SORTDIR_NAME', $sort);
//							$this->setVar('COMPONENT_CONFIG_SORTDIR_VALUE', $sort);
//							$this->setVar('COMPONENT_CONFIG_SORTDIR_SELECTED', $sort == $config[$name] ? ' selected="selected"' : '');
//							$this->parse("sortdir_block".$componentCount, "sortdir", true);
//						}
//						break;
//					default:
//						$this->setVar('COMPONENT_CONFIG_' . strtoupper($name), $config[$name]);
//				}
//			}
//			$this->parse("config_block", "config");
//
//		} else
//			$this->setVar("config_block", "");
//
//		if($componentHandle) {
//			$this->parse('COLUMN'.$component['column'], $componentHandle, true);
//			$columnFlag[$component['column']] = true;
//		}
//	}
//}
//
////set any columns with nothing in them to null
//for($i=1; $i <= MAX_COLUMN_COUNT; $i++){
//	if(!$columnFlag[$i])
//		$this->setVar('COLUMN'.$i,'');
//}


?>
