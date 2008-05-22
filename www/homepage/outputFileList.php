<?php
//file output
$this->setBlock($componentHandle, "files", "files_block");

require_once '../library/file.php';
$fileHandler = new File;

$fileList = $fileHandler->getAllInCategory($categoryID, $config);

if (is_array($fileList)) {
	foreach ($fileList as $file) {

		//set name
		if($file['realname'] != "") {
		
			if($file['inline'] == 'true')
				$url = "<a href=\"sendframe.php?id=".$file['id']."\" target=\"_blank\">".scrubTEXT($file['description'])."</a>";
			else
				$url = "<a href=\"send.php?id=".$file['id']."\">".scrubTEXT($file['description'])."</a>";

			$this->setVar("FILE_NAME", $url);
		}
		else
			$this->setVar("FILE_NAME", scrubTEXT($file['description']));
		
		$this->setVar("FILE_COMMENT", nl2br(scrubTEXT($file['comment'])));

		$this->parse("files_block", "files", true);

	}
} else
	$this->setVar("files_block", '');

//setup block for submission button
$this->setBlock($componentHandle, "submit_file", "submit_file_block");
if(CATEGORY_perms < 2)		
	$this->setVar("submit_file_block", "");
else
	$this->parse("submit_file_block", "submit_file");	

?>