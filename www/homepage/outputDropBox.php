<?php
		//output drop box
		$this->setBlock($componentHandle, "drop_student", "drop_student_block");
		$this->setBlock($componentHandle, "drop_staff", "drop_staff_block");
		$this->setBlock("drop_staff", "drop_file", "drop_file_block");
	
		if(CATEGORY_perms < 2) {
			$this->parse("drop_student_block", "drop_student");
			$this->setVar("drop_staff_block", "");
		}
		else {
			$this->setVar("drop_student_block", "");
			require_once("subject/dropbox.php");
			$dropbox = new dropbox;
			$fileList = $dropbox->getFileList($categoryID);
			if(is_array($fileList)) {
				foreach($fileList as $file) {
					$output = array('DROPBOX_DESCRIPTION' => $file['description'],
									'DROPBOX_ID'		=> $file['id'],
									'DROP_SUBMITTED_BY' => formatName($file),
									'DROP_SUBMITTED_TIME'=>$file['subdate'],
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