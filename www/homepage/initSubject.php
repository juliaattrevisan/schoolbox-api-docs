<?php
		//output mangement/userlist link to owner of subject
		$this->setBlock($componentHandle, "class_list", "class_list_block");
	
		if(SYNERGY_enable == false || CATEGORY_perms < 2 || $category['code'] == '' )
			$this->setVar("class_list_block", "");
		else
			$this->parse("class_list_block", "class_list");	
			

?>