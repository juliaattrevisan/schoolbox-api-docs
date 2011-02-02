<?php

require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/category.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/group.php';

$categoryHandler = new Category;
$groupHandler = new Group(HOMEPAGE_SERVICE);

$categoryID = $_POST['cid'];
$componentID = $_POST['add'];

// check perms
if (($permission = $groupHandler->getPermission($categoryID)) < WRITE_ACCESS)
	exit;

$component = $categoryHandler->getComponent($categoryID, $componentID);


$componentHandle = $component['name'] . $component['homepageComponentLocationID'];

/* This array provides default sorting capabilities for each component
* in the homepage. They can be overridden in each of the output?.php
* homepage component files, to use their own sorting fields.
*/
$sortOptions = array('Name', 'Date');

$componentHandle=TEMPLATE_content;

$this->setFile($componentHandle, 'homepage/components/'.$component['filename']);
$this->setBlock($componentHandle, "config", "config_block");

$this->setVar('COMPONENT_HOMEPAGE_ID', $component['homepageComponentLocationID']);
$this->setVar('COMPONENT_ID', $component['component']);
$this->setVar('ID', $categoryID);

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

?>
