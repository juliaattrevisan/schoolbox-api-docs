<?php
		//output mangement/userlist link to owner of subject
   
            $this->setBlock($componentHandle, "class_list", "class_list_block");
            $this->setBlock($componentHandle, "class_mark", "class_mark_block");

            if(SYNERGY_enable == false || $permission < WRITE_ACCESS || $category['code'] == '' )
                $this->setVar("class_list_block", "");
            else
                $this->parse("class_list_block", "class_list");

            if($groupHandler->isStaff() && $groupHandler->involvedInSubject($category['code']))
               $this->parse("class_mark_block", "class_mark");
            else
               $this->setVar("class_mark_block", "");

			

?>