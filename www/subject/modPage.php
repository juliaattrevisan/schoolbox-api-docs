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
			$this->db->query($query);
			if($row = $this->db->getNextArray())
			{
				$query = "UPDATE homepage_config SET template = '$template' WHERE cid = '".$this->cid."'";
			}
			else
			{
				$query = "INSERT INTO homepage_config (cid, template) VALUES ('$this->cid', '$template')";
			}
			$this->db->query($query);
		}

		function deletePageTemplate()
		{
			$query = "DELETE FROM homepage_config WHERE cid = '".$this->cid."'";
			$this->db->query($query);
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
		
		function updateComponentConfig($component, $config)
		{
			$query = array(
				"component" => $component,
				"cid" => $this->cid,
				"config" => $config
			);
			$this->db->dbUpdateRow('homepage_comp_location', array('component', 'cid'), $query);
		}
		
		// For updating available components
		function updateComponents($submitListComp){
			// Get components
			$query = "SELECT `cid`, `position`, `column`, `component`, `config`
				FROM homepage_comp_location
				WHERE cid = '{$this->cid}'
				ORDER BY `position`";
			$currentListComp = $this->db->getAll($query);

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
				$query = "SELECT size, config FROM homepage_comp WHERE id = $comp";
				$result = $this->db->getRow($query);
				$newComp = array(
					"cid" => $this->cid,
					"position" => "",
					"column" => $result['size'],
					"component" => $comp,
					"config" => $result['config']
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
				$this->saveComponentPosition($newComp['component'], $newComp['column'], $newComp['position'], $newComp['config']);
		}

		function deleteComponentPositions()
		{
			$this->db->dbDeleteRow('homepage_comp_location', 'cid', $this->cid);
		}

		function saveComponentPosition($component, $column, $position, $config)
		{
			$query = array(
				"cid" => $this->cid,
				"component" => $component,
				"column" => $column,
				"position" => $position,
				"config" => $config
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
			$this->db->query($query);
		}

		
		// Default subject page
		function createSubjectPage()
		{
			$this->savePageTemplate(1);
			$this->deleteComponentPositions();

			// Default components
			$this->saveComponentPosition(8, 1, 1, '');
			$this->saveComponentPosition(3, 1, 2, serialize(array('size' => 5)));
			$this->saveComponentPosition(2, 1, 3, serialize(array('size' => 5)));
			$this->saveComponentPosition(12, 2, 1, serialize(array('size' => 7)));
			$this->saveComponentPosition(11, 2, 2, serialize(array('size' => 7)));
			$this->saveComponentPosition(9, 2, 3, '');
			$this->saveComponentPosition(10, 2, 4, '');

			// Default colors
			$this->saveDisplayProperties(unserialize(HOMEPAGE_colors));
		}

		function createSubjectForumPage()
		{
			$this->savePageTemplate(1);
			$this->deleteComponentPositions();

			// Default components
			$this->saveComponentPosition(8, 1, 1, '');
			$this->saveComponentPosition(3, 1, 2, serialize(array('size' => 5)));
			$this->saveComponentPosition(2, 1, 3, serialize(array('size' => 5)));
			$this->saveComponentPosition(12, 2, 1, serialize(array('size' => 7)));
			$this->saveComponentPosition(11, 2, 2, serialize(array('size' => 7)));
			$this->saveComponentPosition(5, 2, 3, serialize(array('size' => 5)));
			$this->saveComponentPosition(9, 2, 3, '');
			$this->saveComponentPosition(10, 2, 4, '');

			// Default colors
			$this->saveDisplayProperties(unserialize(HOMEPAGE_colors));
		}

		function createGroupPage()
		{
			$this->savePageTemplate(1);
			$this->deleteComponentPositions();

			// Default components
			$this->saveComponentPosition(8, 1, 1, '');
			$this->saveComponentPosition(3, 1, 2, serialize(array('size' => 5)));
			$this->saveComponentPosition(2, 1, 3, serialize(array('size' => 5)));
			$this->saveComponentPosition(4, 2, 1, '');
			$this->saveComponentPosition(10, 2, 2, '');

			// Default colors
			$this->saveDisplayProperties(unserialize(HOMEPAGE_colors));
		}

		function createGroupForumPage()
		{
			$this->savePageTemplate(1);
			$this->deleteComponentPositions();

			// Default components
			$this->saveComponentPosition(8, 1, 1, '');
			$this->saveComponentPosition(3, 1, 2, serialize(array('size' => 5)));
			$this->saveComponentPosition(2, 1, 3, serialize(array('size' => 5)));
			$this->saveComponentPosition(5, 2, 1, serialize(array('size' => 7)));
			$this->saveComponentPosition(4, 2, 2, '');
			$this->saveComponentPosition(10, 2, 3, '');
			
			// Default colors
			$this->saveDisplayProperties(unserialize(HOMEPAGE_colors));
		}

		function createFacultyPage()
		{
			$this->savePageTemplate(1);
			$this->deleteComponentPositions();

			// Default components
			$this->saveComponentPosition(15, 1, 1, serialize(array('image' => 'Preview Image')));
			$this->saveComponentPosition(8, 1, 2, '');
			$this->saveComponentPosition(4, 2, 1, '');
			$this->saveComponentPosition(7, 2, 2, serialize(array('size' => 7)));
			$this->saveComponentPosition(6, 2, 3, serialize(array('size' => 7)));
			$this->saveComponentPosition(10, 2, 4, '');

			// Default colors
			$this->saveDisplayProperties(unserialize(HOMEPAGE_colors));
		}
}

?>
