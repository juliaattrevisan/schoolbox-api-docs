<?php

/* This script controls homepage layout functions.  It is used for setting up templates and customising homepage */

require_once(dirname(__FILE__)  .'/../www/conf/conf.php');
require_once(dirname(__FILE__)  .'/../include/db.php');

class ePortfolio {

	function ePortfolio($uid) {
		$this->db = new dbManager(SQL_db);
		$this->uid = $uid;
	}

    function isEnabled($userID = '') {
        if (!is_numeric($userID)) {
            $uid = $_SESSION['id'];
        }
        else {
            $uid = $userID;
        }

        $query = "SELECT eportfolio_enabled
                  FROM user
                  WHERE id = '$uid'";

        return $this->db->getOne($query);
    }

    /**
     * Gets all the images associated with an eportfolio
     * 
     * @param <type> $userID
     * @param <type> $limit
     * @return <type>
     */
	function getImages($userID, $limit = 0) {
		$query = "SELECT id, myFile.description
				  FROM myFile
				  LEFT JOIN allowedFileTypes ON mime_type = mime
				  WHERE owner = '{$userID}'
				  AND deleted = '0' AND INSTR(mime_type, 'image/')
				  ORDER BY submitted";

		if ($limit > 0) {
			$query .= " LIMIT 1, $limit";
        }

		return $this->db->getAll($query);
	}

    /**
     * Logs views of an ePortfolio.
     *
     * @param <type> $userID
     */
	function logPageView($userID) {
		$query = "SELECT datetime FROM eportfolio_log WHERE uid = '". $userID. "' AND user = '".$_SESSION['id']."'";
		$this->db->query($query);

		if ($row = $this->db->getNextArray()) {
			$query = "UPDATE eportfolio_log SET datetime = NOW(), hitcount = hitcount + 1 WHERE uid = '" .$userID ."'
			AND user = '".$_SESSION['id']."'";
		}
		else {
			$query = "INSERT INTO eportfolio_log (datetime, user, uid, hitcount) VALUES (NOW() ,
			'".$_SESSION['id']."', '" . $userID . "', '0')";
		}

		$this->db->query($query);
	}

    /**
     * Returns the current template.
     *
     * @return <type>
     */
	function getTemplate() {
        $query = "SELECT template FROM eportfolio_config WHERE uid = '{$this->uid}'";

        return $this->db->getOne($query);
	}

    /**
     * Get a list of components associated with this eportfolio.
     *
     * @return <type>
     */
	function getComponentList() {

            		$query = "SELECT t.eportfolioComponentCategoryID AS categoryID, t.name as categoryName, id, c.name, component, description, allowMultiple,
                    (SELECT COUNT(*) FROM eportfolio_comp_location hcl WHERE hcl.component = c.id AND hcl.uid = {$this->uid}) as componentCount
                    FROM eportfolio_comp c
                    JOIN eportfolio_comp_category t ON t.eportfolioComponentCategoryID = eportfolioCategoryID
                    LEFT JOIN eportfolio_comp_location l ON l.component = c.id AND uid = {$this->uid}

                  WHERE ( invisible = 'false' OR l.component IS NOT NULL ) GROUP BY id ORDER BY eportfolioCategoryID, c.name";


		return $this->db->getAll($query);

	}

    /**
     * Is that a Cartesian Product in here?
     *
     * @return <type>
     */
	function getComponents() {
		$query = "SELECT position, `column`, component, eportfolio_comp.config dfconfig, eportfolio_comp_location.config, name, method, filename
				  FROM eportfolio_comp_location, eportfolio_comp
				  WHERE component = id AND uid = '{$this->uid}'
				  ORDER BY position";

		return $this->db->getAll($query);
	}

    /**
     * Colour theme for this user's ePortfolio.
     *
     * @return <type>
     */
	function getColorList() {
		$query = "SELECT left_head_font, left_head_bg, left_font, left_bg_primary, left_bg_secondary, left_link, left_hover,
                  right_head_font, right_head_bg, right_font, right_bg_primary, right_bg_secondary, right_link, right_hover
                  FROM eportfolio_config WHERE uid = '{$this->uid}'";

		$colourList = $this->db->getRow($query);

		/* Use defaults for missing colours
         */
		$defaultColours = unserialize(HOMEPAGE_colors);

		if (is_array($colourList)) {
			foreach ($colourList as $key => $value) {
				if (empty($value)) {
                    $colourList[$key] = $defaultColours[$key];
                }
			}
		}

		return $colourList;
	}

    /**
     * Remembers themes for a template.
     * 
     * @param <type> $template
     */
	function savePageTemplate($template) {
        $query = "SELECT uid FROM eportfolio_config WHERE uid = '".$this->uid."'";
        $this->db->query($query);

        if ($row = $this->db->getNextArray()) {
            $query = "UPDATE eportfolio_config SET template = '$template' WHERE uid = '".$this->uid."'";
        }
        else {
            $query = "INSERT INTO eportfolio_config (uid, template) VALUES ('$this->uid', '$template')";
        }

        $this->db->query($query);

        if ($template == 3) {
            // full column layout, move all components into left column
            $query = "SELECT MAX(position) FROM eportfolio_comp_location WHERE uid = " . $this->uid . " AND `column` = 1";

            if ($position = $this->db->getOne($query)) {
                $query = "UPDATE eportfolio_comp_location SET position = (position + ".$position."), `column` = 1 WHERE uid = " . $this->uid . " AND `column` = 2";
                $this->db->query($query);
            }
        }
	}


        function addComponent($component) {
            $query = "SELECT config FROM eportfolio_comp WHERE id = '$component'";
            $componentSetup = $this->db->getRow($query);

             /* We are inserting component at the top of the left column so we move all the existing components down */
            $query = "UPDATE eporfolio_comp_location SET position = position+1 WHERE uid = ".$this->uid." AND `column` = 1";
            $this->db->query($query);

            $this->saveComponentPosition($component, 1, 1, $newComp['config']);
        }




	function deleteComponentPositions() {
		$this->db->delete('eportfolio_comp_location', 'uid', $this->uid);
	}

    function deleteComponent($component) {
        $query = "DELETE FROM eportfolio_comp_location WHERE cid = '$this->cid' AND homepageComponentLocationID = '$component'";
        $this->db->query($query);
    }


    /**
     * Updates location of components.
     *
     * @param <type> $component
     * @param <type> $column
     * @param <type> $position
     */
	function updateComponentPosition($component, $column, $position) {
		$query = array(
			'component' => $component,
			'uid' => $this->uid,
			'column' => $column,
			'position' => $position
		);

		$result = $this->db->update('eportfolio_comp_location', array('component', 'uid'), $query);
	}

    /**
     * Records component configuration
     * 
     * @param <type> $component
     * @param <type> $config
     */
	function updateComponentConfig($component, $config) {
		$query = array(
			'component' => $component,
			'uid' => $this->uid,
			'config' => $config
		);

		$this->db->update('eportfolio_comp_location', array('component', 'uid'), $query);
	}

	// For updating available components
	function updateComponents($submitListComp){
		// Get components
		$query = "SELECT `uid`, `position`, `column`, `component`, `config`
			FROM eportfolio_comp_location
			WHERE uid = '{$this->uid}'
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
				$query = "SELECT size, config FROM eportfolio_comp WHERE id = $comp";
				$result = $this->db->getRow($query);
				$newComp = array(
					"uid" => $this->uid,
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

	function saveComponentPosition($component, $column, $position, $config) {
		$query = array(
			"uid" => $this->uid,
			"component" => $component,
			"column" => $column,
			"position" => $position,
			"config" => $config
		);

		$this->db->insert('eportfolio_comp_location', $query);
	}

    /**
     * Saves colour settings.
     *
     * @param <type> $listColor
     * @return <type>
     */
	function saveDisplayProperties($listColor) {
		$query = "UPDATE eportfolio_config SET ";

		foreach($listColor as $key => $color) {
			$query .= "$key = '$color', ";
		}

		$query = substr($query, 0, -2);
		$query .= " WHERE uid = '".$this->uid."'";

		return $this->db->query($query);
	}

    /**
     * Can the current user edit the userID's portfolio contents? This should
     * probably go into the User library
     *
     * @param <type> $userID
     * @return <type>
     */
    function canEdit($userID) {
        $groupHandler = new Group();

	    if ($groupHandler->isSuperuser() || $groupHandler->isStaff() || $groupHandler->isParentOfChild($userID) || ($_SESSION['id'] == $userID)) {
            return true;
        }

        return false;
    }

    /**
     * Determines whether the current user can view/edit a eportfolio's private
     * notes.
     *
     * @param <type> $userID
     */
    function canEditPrivateNotes($userID) {
        $groupHandler = new Group();

        if ($groupHandler->isSuperUser() || $groupHandler->isStaff() || $groupHandler->isParentOfChild($userID)) {
            return true;
        }

        return false;
    }
}
?>