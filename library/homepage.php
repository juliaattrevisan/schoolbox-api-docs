<?php

/* This script controls homepage layout functions.  It is used for setting up templates and customising homepage */

class Homepage {

	/* Used to remove homepage components and reset template
	 */
	const REMOVE_HOMEPAGE_TEMPLATE_ID = -1;

	function Homepage($cid) {
		$this->db = new DeprecatedDatabase(SQL_db);
		$this->cid = $cid;
	}

	function getTemplate()
	{
			//returns the current template
			$query = "SELECT template FROM homepage_config WHERE cid = '".$this->cid."'";
			return $this->db->getOne($query);
	}

	function getComponentList($type = null)
	{
		$query = "SELECT t.homepageComponentCategoryID AS categoryID, t.name as categoryName, id, c.name, component, description, allowMultiple,
                    (SELECT COUNT(*) FROM homepage_comp_location hcl WHERE hcl.component = c.id AND hcl.cid = {$this->cid}) as componentCount
                    FROM homepage_comp c
                    JOIN homepageComponentCategory t ON t.homepageComponentCategoryID = homepageCategoryID
                    LEFT JOIN homepage_comp_location l ON l.component = c.id AND cid = {$this->cid}

                  WHERE ( invisible = 'false' OR l.component IS NOT NULL )";


        if (is_numeric($type)) {
            $query .= " AND component = $type";
        }

        $query .= " GROUP BY id ORDER BY homepageCategoryID, c.name";

		return $this->db->getAll($query);
	}

	/**
	 * Gets the column widths for a particular homepage
	 *
	 * @return array|bool
	 */
	public function getColumnWidths() {
		$query = "SELECT left_column_width, right_column_width
				  FROM homepage_config
				  WHERE cid = '{$this->cid}'";

		return $this->db->getRow($query);
	}

	/**
	 * Update column widths
	 *
	 * @param  $left
	 * @param  $right
	 * @return void
	 */
	public function updateColumnWidths($left, $right) {
		$query = "UPDATE homepage_config
				  SET
				  	left_column_width = '{$left}',
				  	right_column_width = '{$right}'
				  WHERE cid = '{$this->cid}'";

		$this->db->query($query);
	}


	function getColorList()
	{
		$query = "SELECT left_head_font, left_head_bg, left_font, left_bg_primary, left_bg_secondary, left_link, left_hover,
						right_head_font, right_head_bg, right_font, right_bg_primary, right_bg_secondary, right_link, right_hover
						FROM homepage_config WHERE cid = '{$this->cid}'";
		$colourList = $this->db->getRow($query);

		// use defaults for missing colours
		$defaultColours = unserialize(HOMEPAGE_colors);
		if (is_array($colourList)) {
			foreach ($colourList as $key => $value) {
				if (empty($value)) $colourList[$key] = $defaultColours[$key];
			}
		}
		return $colourList;
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

			if ($template == 3) {
				// full column layout, move all components into left column
				$query = "SELECT MAX(position) FROM homepage_comp_location WHERE cid = " . $this->cid . " AND `column` = 1";
				if ($position = $this->db->getOne($query)) {
					$query = "UPDATE homepage_comp_location SET position = (position + ".$position."), `column` = 1 WHERE cid = " . $this->cid . " AND `column` = 2";
					$this->db->query($query);
				}
			}
	}

	function deleteHomepage()
	{
		$this->db->delete('homepage_config', 'cid', $this->cid);
		$this->deleteComponentPositions();

		require_once dirname(__FILE__) . '/group.php';
		$groupHandler = new Group;
		$groupHandler->disbandGroup($this->cid);

		$this->emptyHomepage();
	}

	function emptyHomepage()
	{
		/* Delete components, TODO move this into calling component delete functions */
		require_once dirname(__FILE__) . '/forum.php';
		$forumHandler = new Forum;
		$forumHandler->homepageClean($this->cid);

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

		require_once dirname(__FILE__) . '/submission.php';
		$submissionBoxHandler = new Submission;
		$submissionBoxHandler->homepageClean($this->cid);
	}

    function addComponent($component) {
        $query = "SELECT config FROM homepage_comp WHERE id = '$component'";
	    $componentSetup = $this->db->getRow($query);

         /* We are inserting component at the top of the left column so we move all the existing components down */
        $query = "UPDATE homepage_comp_location SET position = position+1 WHERE cid = ".$this->cid." AND `column` = 1";
        $this->db->query($query);

        return $this->saveComponentPosition($component, 1, 1, $newComp['config']);

    }

	function deleteComponentPositions()
	{
		$this->db->delete('homepage_comp_location', 'cid', $this->cid);
	}

    function deleteComponent($component)
	{
        $query = "DELETE FROM homepage_comp_location WHERE cid = '$this->cid' AND homepageComponentLocationID = '$component'";
        $this->db->query($query);
	}


	function updateComponentPosition($component, $column, $position)
	{
		$query = array(
			"homepageComponentLocationID" => $component,
			"column" => $column,
			"position" => $position
		);

		$result = $this->db->update('homepage_comp_location', 'homepageComponentLocationID', $query);
	}

	function updateComponentConfig($component, $config)
	{
		$query = array(
			"homepageComponentLocationID" => $component,
			"config" => $config
		);
		$this->db->update('homepage_comp_location', 'homepageComponentLocationID', $query);
	}

	// For updating available components
	function updateComponents($submitListComp){
		// Get components
		$query = "SELECT `cid`, `position`, `column`, `component`, `config`
			FROM homepage_comp_location
			WHERE cid = '{$this->cid}'
			ORDER BY `position`";
		$currentListComp = $this->db->getAll($query);
		$submitListComp = is_array($submitListComp) ? $submitListComp : array();
		if (is_array($currentListComp)) {
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
		}

		// Add new ones to end finding their
		$newListComp = $currentListComp;
		if (is_array($submitListComp)) {
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
		}
		// Reposition both columns and reorder array
		$count = array(1, 1);
		if (is_array($newListComp)) {
			foreach($newListComp as $key => $newComp)
			{
				$newListComp[$key]['position'] = $count[$newComp['column'] - 1];
				$count[$newComp['column'] - 1]++;
			}
		}
		$newListComp = is_array($newListComp) ? $newListComp : array();
		$newListComp = array_values($newListComp);

		// Update
		$this->deleteComponentPositions();
		if (is_array($newListComp)) {
			foreach($newListComp as $newComp)
				$this->saveComponentPosition($newComp['component'], $newComp['column'], $newComp['position'], $newComp['config']);
		}
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

        return $this->db->insertID();

	}

    function setComponentConfig($homepageComponentLocationID, $config) {
        $result =  $this->db->update('homepage_comp_location', 'homepageComponentLocationID', array(
            'homepageComponentLocationID' => $homepageComponentLocationID,
            'config' => serialize($config)
        ));

        return $result;
    }

    function getComponentConfig($homepageComponentLocationID) {
        $query = "SELECT config FROM homepage_comp_location WHERE homepageComponentLocationID = $homepageComponentLocationID";

        $config = unserialize($this->db->getOne($query));

        return $config;
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
