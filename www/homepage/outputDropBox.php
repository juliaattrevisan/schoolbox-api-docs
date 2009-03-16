<?php
//output drop box
$this->setBlock($componentHandle, "drop_student", "drop_student_block");
$this->setBlock($componentHandle, "drop_staff", "drop_staff_block");
$this->setBlock('drop_staff', 'drop_file', 'drop_file_block');

if ($permission < WRITE_ACCESS) {
	$this->parse('drop_student_block', 'drop_student');
	$this->setVar('drop_staff_block', '');
}
else {
	
	$this->setVar('drop_student_block', '');
	require_once '../library/dropbox.php';
	$dropbox = new Dropbox;
	$fileList = $dropbox->getFileList($categoryID);
	if(is_array($fileList)) {
		foreach($fileList as $file) {
			$output = array('DROPBOX_MIME_IMAGE'	=> $allowedFiles->getClass($file["iconFile"]),
							'DROPBOX_DESCRIPTION' => scrubTEXT($file['description']),
							'DROPBOX_ID'		=> $file['id'],
							'DROP_SUBMITTED_BY' => "<a href=\"/search/user/{$file['uid']}\">".formatName($file).'</a>',
							'DROP_SUBMITTED_TIME'=>$file['subdate'],
							'ON_OFF' => ++$j%2? '' : 'dark',
							);
			$this->setVar($output);
			$this->parse("drop_file_block", "drop_file", true);
		}
	} 
	else 
		$this->setVar("drop_file_block", "");
	
	$this->parse("drop_staff_block", "drop_staff");
	
}
		
?>