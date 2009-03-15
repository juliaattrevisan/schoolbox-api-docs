<?php
//file output
$this->setBlock($componentHandle, 'folders', 'folders_block');
$this->setBlock('folders', 'homepage_folder', 'homepage_folder_block');
$this->setBlock('folders', 'modify_folder', 'modify_folder_block');


$folderList = $categoryHandler->getCategoryList($categoryID, $config);
if (is_array($folderList)) {
	$flag = 0;
	foreach ($folderList as $folder) {
		if($groupHandler->getPermission($folder['id']) > DENY_ACCESS)
		{
			//set name
			$output = array('FOLDER_ID'		=> $folder['id'],
							'FOLDER_NAME'	=> scrubTEXT($folder['name']),
							'ON_OFF'		=> ++$j%2? '' : 'dark',
							);
			$this->setVar($output);	
			
			if($folder['template'])
				$this->parse('homepage_folder_block', 'homepage_folder');
			else
				$this->setVar('homepage_folder_block', '');
			
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

//setup block for submission button
$this->setBlock($componentHandle, "submit_folder", "submit_folder_block");
if ($permission < WRITE_ACCESS)		
	$this->setVar("submit_folder_block", "");
else
	$this->parse("submit_folder_block", "submit_folder");	
	
?>
