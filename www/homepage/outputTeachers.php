<?php
		// gets teachers involved in subject, outputs links to their addressbook page
		include('include/syn_classlist.php');
		$this->setBlock($componentHandle, "staff_teachers", "staff_teacher_block");
		
		if($code != "" && SYNERGY_enable)
		{	
			$listSyns = getTeacherName($code);
			if(is_array($listSyns)) {
				foreach($listSyns as $synid) {
					$query = "SELECT id, title, firstname, lastname, alt_email, local_email FROM user WHERE synergy_id = '$synid'";
					$teacher=$this->db->getRow($query);
					if(is_array($teacher)) {
						$name = formatName($teacher);
			
						if(SITE_location == 'wenona')
							$this->setVar("MEMBER_NAME", $name);
						else 
							$this->setVar("MEMBER_NAME", "<a href=\"/search/userLookup.php?id={$teacher['id']}\">{$name}</a>");
							
						$this->parse("staff_teacher_block", "staff_teachers", true);	
					}
				}
				//if(is_array($teacher))
					
			}
		}	
?>