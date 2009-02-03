<?php
		$this->setBlock($componentHandle, "manage_page", "manage_page_block");
		$this->setBlock($componentHandle, "stats_data", "stats_data_block");

		if($permission >= FULL_ACCESS) //is owner of area
			$this->parse("manage_page_block", "manage_page");	
		else
			$this->setVar("manage_page_block", "");
	
		if($permission >= WRITE_ACCESS) //has write access
			$this->parse("stats_data_block", "stats_data");	
		else
			$this->setVar("stats_data_block", "");

?>