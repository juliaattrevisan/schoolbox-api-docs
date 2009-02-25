<?php
//file output
$this->setBlock($componentHandle, "folders", "folders_block");

$folderList = $categoryHandler->getCategoryList($categoryID, $config);
if (is_array($folderList)) {
	$flag = 0;
	foreach ($folderList as $folder) {
		$this->setVar('ON_OFF', $i++%2? '' : 'dark');
		if($groupHandler->getPermission($folder['id']) > DENY_ACCESS)
		{
					//set name
					$this->setVar("FOLDER_NAME", "<a href=\"/resources/".$folder['id']."\">".scrubTEXT($folder['name'])."</a>");
			
					if($folder['template'])
						$this->setVar("HOMEPAGE_LINK", "<a href=\"/homepage/".$folder['id']."\">homepage</a>");
					else
						$this->setVar("HOMEPAGE_LINK", "");
			
					$this->parse("folders_block", "folders", true);
					$flag = 1;
		}
	}
	if(!$flag) { $this->setVar("folders_block", "");}
} else
	$this->setVar("folders_block", "");

//setup block for submission button
$this->setBlock($componentHandle, "submit_folder", "submit_folder_block");
if ($permission < WRITE_ACCESS)		
	$this->setVar("submit_folder_block", "");
else
	$this->parse("submit_folder_block", "submit_folder");	
	
?>
