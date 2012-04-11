<?php
//file output
$this->setBlock($componentHandle, 'folders', 'folders_block');
$this->setBlock('folders', 'homepage_folder', 'homepage_folder_block');
$this->setBlock('folders', 'modify_folder', 'modify_folder_block');

// if config is set to import files, make it so
if(strpos($config['import-folders'], '/homepage/') === 0) {
    // get homepage id
    $catID = substr($config['import-folders'], 10);
} else {
    $catID = $categoryID;
}

$folderList = $categoryHandler->getCategoryList($catID, $config);

$this->setVar('folders_block', '');

if (is_array($folderList)) {
	$flag = 0;
	foreach ($folderList as $folder) {
		if($groupHandler->getPermission($folder['id']) > DENY_ACCESS)
		{
			//set name
			$output = array('FOLDER_ID'		=> $folder['id'],
							'FOLDER_NAME'	=> Scrub::text($folder['name']),
							'FOLDER_ICON'	=> $folder['template']?'house':'folder_blue_full',
							'ON_OFF'		=> ++$j%2? '' : 'dark',
							);
			$this->setVar($output);

			if($permission >= WRITE_ACCESS)
				$this->parse('modify_folder_block', 'modify_folder');
			else
				$this->setVar('modify_folder_block','');

			$this->parse('folders_block', 'folders', true);
			$flag = 1;

		}
	}
	if(!$flag) { $this->setVar('folders_block', '');}
} else
	$this->setVar('folders_block', '');

/* Set up component name
 */
if($catID == $categoryID) {
    $src = array('name' => 'Local');
} else {
    $catHandler = new Category();
    $src = $catHandler->getByID($catID);
}

$this->setvar(array(
	'COMPONENT_NAME'            => strlen(trim($config['componentname'])) ? $config['componentname'] : 'Folders',
    'COMPONENT_CONFIG_SRC_ID'   => $config['import-folders'],
    'COMPONENT_CONFIG_SRC_NAME' => $src['name'],
    'COMPONENT_HANDLE'          => strtolower($componentHandle)
));

//setup block for submission button
$this->setBlock($componentHandle, "submit_folder", "submit_folder_block");
if ($permission < WRITE_ACCESS)
	$this->setVar("submit_folder_block", "");
else
	$this->parse("submit_folder_block", "submit_folder");

?>
