<?php

/* This script controls homepage layout functions.  It is used for setting up templates and customising homepage */

require_once($_SERVER['DOCUMENT_ROOT'] .'/conf/conf.php');
require_once($_SERVER['DOCUMENT_ROOT'] .'/../include/db.php');

class Homepage {

	function Homepage($cid) {
		$this->db = new dbManager(SQL_db);
		$this->cid = $cid;
	}

	function getTemplate()
	{
			//returns the current template
			$query = "SELECT template FROM homepage_config WHERE cid = '".$this->cid."'";
			return $this->db->getOne($query);
	}
	
	function getComponentList($size)
	{
		// 1 = large, 2 = small, 0 = all
		if($size > 0 ) {
			$query = "SELECT id, name, component, description FROM homepage_comp c 
						LEFT JOIN homepage_comp_location l ON l.component = c.id AND cid = {$this->cid} 
						WHERE c.size = $size AND invisible = 'false' ORDER BY name";
		}
		else {
			$query = "SELECT id, name, component, description FROM homepage_comp c
					  LEFT JOIN homepage_comp_location l ON l.component = c.id AND cid = {$this->cid} 
					  WHERE invisible = 'false' ORDER BY name";
		}
		
		return $this->db->getAll($query); 
	}
	
	function getColorList()
	{
		$query = "SELECT large_head_fontcolour, large_head_bgcolour, large_text_fontcolour, large_text_bgcolour, 
						small_head_fontcolour, small_head_bgcolour, small_text_fontcolour, small_text_bgcolour 
						FROM homepage_config WHERE cid = '{$this->cid}'";
		return $this->db->dbGetSingleRow($query); 
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

	function deleteHomepage()
	{		
		$this->db->delete('homepage_config', 'cid', $this->cid);
		$this->deleteComponentPositions();
		
		require_once dirname(__FILE__) . '/group.php';
		$groupHandler = new Group;
		$groupHandler->disbandGroup($this->cid);
		
		/* Delete components, TODO move this into calling component delete functions */
		$this->db->delete('forum', 'cid', $this->cid);
		
		require_once dirname(__FILE__) . '/categoryNews.php';
		$newsHandler = new CategoryNews;
		$newsHandler->homepageClean($this->cid);		
		
		require_once dirname(__FILE__) . '/wiki.php';
		$wikiHandler = new Wiki;
		$wikiHandler->homepageClean($this->cid);
		
		require_once dirname(__FILE__) . '/poll.php';
		$pollHandler = new Poll;
		$pollHandler->homepageClean($this->cid);
		
		require_once dirname(__FILE__) . '/dropbox.php';
		$dboxHandler = new Dropbox;
		$dboxHandler->homepageClean($this->cid);
		
		require_once dirname(__FILE__) . '/submission-box.php';
		$submissionBoxHandler = new SubmissionBox;
		$submissionBoxHandler->homepageClean($this->cid);
		
		
	}
	
	function deleteComponentPositions()
	{
		$this->db->delete('homepage_comp_location', 'cid', $this->cid);
	}
	
		
	function updateComponentPosition($component, $column, $position)
	{
		$query = array(
			"component" => $component,
			"cid" => $this->cid,
			"column" => $column,
			"position" => $position
		);
		$result = $this->db->update('homepage_comp_location', array('component', 'cid'), $query);
	}
		
	function updateComponentConfig($component, $config)
	{
		$query = array(
			"component" => $component,
			"cid" => $this->cid,
			"config" => $config
		);
		$this->db->update('homepage_comp_location', array('component', 'cid'), $query);
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
	
	
	
	function saveComponentPosition($component, $column, $position, $config)
	{
		$query = array(
			"cid" => $this->cid,
			"component" => $component,
			"column" => $column,
			"position" => $position,
			"config" => $config
		);
		$this->db->insert('homepage_comp_location', $query);
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
		return $this->db->query($query);
		
	}
}



?>
