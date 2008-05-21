<?php
                $this->setBlock($componentHandle, "duties", "d_block");
                $sql = "SELECT l.*, d.user, d.date, d.cid FROM luDuties l LEFT JOIN duty d ON l.dutyTypeID = d.dutyTypeID 
			and d.date = '".date('Y-m-d')."' where l.cid = ".$categoryID ;
                $this->db->query($sql);
                $block = false;
                $this->setVar("DATE", 'Duties for '.date('l, jS \of F Y'));
                while($row = $this->db->getNextArray())
                {
                        $this->setVar("DUTY", $row['name']);
                        $this->setVar("ASSIGNEDUSER", $row['user']);
                        $this->parse("d_block", "duties", true);
                        $block = true;
                }
                if($block == false)
                {
                        $this->setVar("DUTY", "No Duties set for today");
                        $this->setVar("ASSIGNEDUSER", '');
                        $this->parse("d_block", "duties", true);
                }
                $this->setBlock($componentHandle, "edit_duties", "edit_duties_block");
                if(CATEGORY_perms < 2)
                        $this->setVar("edit_duties_block", "");
                else
                        $this->parse("edit_duties_block", "edit_duties");
					
?>