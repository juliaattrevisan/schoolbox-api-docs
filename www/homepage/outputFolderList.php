<?php
//file output
$this->setBlock($componentHandle, 'folders', 'folders_block');
$this->setBlock('folders', 'homepage_folder', 'homepage_folder_block');
$this->setBlock('folders', 'modify_folder', 'modify_folder_block');

$i = strpos($config['import-folders'], 'homepage/');

// if config is set to import files, make it so
if(($i = strpos($config['import-folders'], 'homepage/')) === 0) {
    // get homepage id
    $auxCategory = substr($config['import-folders'], 9);
    $folderList = $categoryHandler->getCategoryList(array($categoryID, $auxCategory), $config);
} else
    $folderList = $categoryHandler->getCategoryList($categoryID, $config);

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
$this->setvar(array(
	'COMPONENT_NAME' => strlen(trim($config['componentname'])) ? $config['componentname'] : 'Folders',
    'COMPONENT_CONFIG_IMPORT_FOLDERS' => $config['import-folders'],
));

//setup block for submission button
$this->setBlock($componentHandle, "submit_folder", "submit_folder_block");
if ($permission < WRITE_ACCESS)
	$this->setVar("submit_folder_block", "");
else
	$this->parse("submit_folder_block", "submit_folder");

?>
