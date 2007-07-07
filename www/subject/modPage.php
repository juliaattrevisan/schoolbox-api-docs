<?php

class modPage
{
	function modPage($cid=0)
	{
		$this->db = new dbManager;
		$this->db->dbConnect();

		$this->cid = $cid;
	}

	function setCID($cid)
	{
		$this->cid = $cid;
	}

		function savePageTemplate($template)
		{
			$query = "SELECT cid FROM homepage_config WHERE cid = '".$this->cid."'";
			$this->db->dbDoQuery($query);
			if($row = $this->db->dbGetNextArray())
			{
				$query = "UPDATE homepage_config SET template = '$template' WHERE cid = '".$this->cid."'";
			}
			else
			{
				$query = "INSERT INTO homepage_config (cid, template) VALUES ('$this->cid', '$template')";
			}
			$this->db->dbDoQuery($query);
		}

		function deletePageTemplate()
		{
			$query = "DELETE FROM homepage_config WHERE cid = '".$this->cid."'";
			$this->db->dbDoQuery($query);
		}
		
		function updateComponentPosition($component, $column, $position)
		{
			$query = array(
				"component" => $component,
				"cid" => $this->cid,
				"column" => $column,
				"position" => $position
			);
			$this->db->dbUpdateRow('homepage_comp_location', array('component', 'cid'), $query);
		}
		
		// For updating available components
		function updateComponents($submitListComp){
			// Get components
			$query = "SELECT `cid`, `position`, `column`, `component`
				FROM homepage_comp_location
				WHERE cid = '{$this->cid}'
				ORDER BY `position`";
			$currentListComp = $this->db->dbGetAllRows($query);

			// Remove deleted ones
			foreach($currentListComp as $key => $comp)
			{
				if(!in_array($comp['component'], $submitListComp))
				{
					unset($currentListComp[$key]);
				}
				else
					unset($submitListComp[array_search($comp['component'], $submitListComp)]);
			}
			
			// Add new ones to end finding their 
			$newListComp = $currentListComp;
			foreach($submitListComp as $key => $comp)
			{
				$query = "SELECT size FROM homepage_comp WHERE id = $comp";
				$size = $this->db->dbGetSingleRow($query);
				$newComp = array(
					"cid" => $this->cid,
					"position" => "",
					"column" => $size['size'],
					"component" => $comp,
					"size" => 0
				);
				$newListComp[] = $newComp;
			}
			
			// Reposition both columns and reorder array
			$count = array(1, 1);
			foreach($newListComp as $key => $newComp)
			{
				$newListComp[$key]['position'] = $count[$newComp['column'] - 1];
				$count[$newComp['column'] - 1]++;
			}
			$newListComp = array_values($newListComp);
			
			// Update
			$this->deleteComponentPositions();
			foreach($newListComp as $newComp)
				$this->saveComponentPosition($newComp['component'], $newComp['column'], $newComp['position'], $newComp['size']);
		}

		function deleteComponentPositions()
		{
			$this->db->dbDeleteRow('homepage_comp_location', 'cid', $this->cid);
		}

		function saveComponentPosition($component, $column, $position, $size)
		{
			$query = array(
				"cid" => $this->cid,
				"component" => $component,
				"column" => $column,
				"position" => $position,
				"size" => $size
			);
			$this->db->dbInsertRow('homepage_comp_location', $query);
		}

		function saveDisplayProperties($listColor)
		{
			$query = "UPDATE homepage_config SET ";
			foreach($listColor as $key => $color)
			{		
				$query .= "$key = '$color', ";
			}
			$query = substr($query, 0, -2);
			$query .= " WHERE cid = '".$this->cid."'";
			$this->db->dbDoQuery($query);
		}

		
		// Default subject page
		function createSubjectPage()
		{
			$this->savePageTemplate(1);
			$this->deleteComponentPositions();

			// Default components
			$this->saveComponentPosition(8, 1, 1, 0);
			$this->saveComponentPosition(19, 1, 2, 5);
			$this->saveComponentPosition(2, 1, 3, 5);
			$this->saveComponentPosition(12, 2, 1, 7);
			$this->saveComponentPosition(11, 2, 2, 7);
			$this->saveComponentPosition(9, 2, 3, 0);
			$this->saveComponentPosition(10, 2, 4, 0);

			// Default colors
			$this->saveDisplayProperties(unserialize(HOMEPAGE_colors));
		}

		function createSubjectForumPage()
		{
			$this->savePageTemplate(1);
			$this->deleteComponentPositions();

			// Default components
			$this->saveComponentPosition(8, 1, 1, 0);
			$this->saveComponentPosition(19, 1, 2, 5);
			$this->saveComponentPosition(2, 1, 3, 5);
			$this->saveComponentPosition(12, 2, 1, 7);
			$this->saveComponentPosition(11, 2, 2, 7);
			$this->saveComponentPosition(5, 2, 3, 5);
			$this->saveComponentPosition(9, 2, 4, 0);
			$this->saveComponentPosition(10, 2, 5, 0);

			// Default colors
			$this->saveDisplayProperties(unserialize(HOMEPAGE_colors));
		}

		function createGroupPage()
		{
			$this->savePageTemplate(1);
			$this->deleteComponentPositions();

			// Default components
			$this->saveComponentPosition(8, 1, 1, 0);
			$this->saveComponentPosition(3, 1, 2, 5);
			$this->saveComponentPosition(2, 1, 3, 5);
			$this->saveComponentPosition(4, 2, 1, 7);
			$this->saveComponentPosition(10, 2, 2, 7);

			// Default colors
			$this->saveDisplayProperties(unserialize(HOMEPAGE_colors));
		}

		function createGroupForumPage()
		{
			$this->savePageTemplate(1);
			$this->deleteComponentPositions();

			// Default components
			$this->saveComponentPosition(8, 1, 1, 0);
			$this->saveComponentPosition(3, 1, 2, 5);
			$this->saveComponentPosition(2, 1, 3, 5);
			$this->saveComponentPosition(5, 2, 1, 7);
			$this->saveComponentPosition(4, 2, 2, 7);
			$this->saveComponentPosition(10, 2, 3, 7);
			
			// Default colors
			$this->saveDisplayProperties(unserialize(HOMEPAGE_colors));
		}

		function createFacultyPage()
		{
			$this->savePageTemplate(1);
			$this->deleteComponentPositions();

			// Default components
			$this->saveComponentPosition(15, 1, 1, 0);
			$this->saveComponentPosition(8, 1, 2, 10);
			$this->saveComponentPosition(4, 2, 1, 7);
			$this->saveComponentPosition(7, 2, 2, 7);
			$this->saveComponentPosition(6, 2, 3, 7);
			$this->saveComponentPosition(10, 2, 4, 7);

			// Default colors
			$this->saveDisplayProperties(unserialize(HOMEPAGE_colors));
		}
}

?>
