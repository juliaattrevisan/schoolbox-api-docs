<?php

/* This script controls homepage layout functions.  It is used for setting up templates and customising homepage */

class Eportfolio_Container {

	/**
	 * Custom constructor
	 *
	 * @param  $uid
	 */
	public function __construct($uid) {
		$this->db = new DeprecatedDatabase(SQL_db);
		$this->uid = $uid;
	}


	/**
	 * Check if an eportfolio is enabled
	 *
	 * @param string $userID
	 * @return
	 */
    public function isEnabled($userID = '') {
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
     * Function to create inital record of homepage when settings are first altered
     */
    public function initialiseHomepage($userID) {

        $eportfolio_config['uid'] =$userID;

        $defaultColour = array('head_font'=>Cfg::get('homepage_colour_head_font'),
                                'head_bg'=>Cfg::get('homepage_colour_head_bg'),
                                'font'=>Cfg::get('homepage_colour_font'),
                                'bg_primary'=>Cfg::get('homepage_colour_bg_primary'),
                                'bg_secondary'=>Cfg::get('homepage_colour_bg_secondary'),
                                'link'=>Cfg::get('homepage_colour_link'),
                                'hover'=>Cfg::get('homepage_colour_hover')
                               );
        $columns = array('top','left','right');
        foreach($columns as $col){
            foreach($defaultColour as $field=>$colour) {
                $eportfolio_config[$col.'_'.$field] = $colour;
            }
        }

        $this->db->insert('eportfolio_config',$eportfolio_config);

    }

    /**
     * Checks if config for eportfolio exists
     */
    private function eportfolioConfigExists($userID) {
        $query = "SELECT uid FROM eportfolio_config WHERE uid='$userID'";
        if($this->db->getOne($query))
            return true;
        else
            return false;
    }


	/**
	 * Gets all the images associated with an eportfolio
	 *
	 * @param  $userID
	 * @param int $limit
	 * @return array|bool
	 */
	public function getImages($userID, $limit = 0) {
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
	 * @param  $userID
	 * @return void
	 */
	public function logPageView($userID) {
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
	 * @return
	 */
	public function getTemplate() {
        $query = "SELECT template FROM eportfolio_config WHERE uid = '{$this->uid}'";

        return $this->db->getOne($query);
	}

	/**
	 * Get a list of components associated with this eportfolio.
	 *
	 * @return array|bool
	 */
	public function getComponentList() {
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
	 * @return array|bool
	 */
	public function getComponents() {
		$query = "SELECT eportfolioComponentLocationID, position, `column`, component, eportfolio_comp.config dfconfig, eportfolio_comp_location.config, name, method, filename
				  FROM eportfolio_comp_location, eportfolio_comp
				  WHERE component = id AND uid = '{$this->uid}'
				  ORDER BY position";

		return $this->db->getAll($query);
	}

	/**
	 * Get a component
	 *
	 * @param  $uidID
	 * @param  $componentID
	 * @return array|bool
	 */
    public function getComponent($uidID, $componentID) {
		$query = "SELECT eportfolioComponentLocationID, position, `column`, component, eportfolio_comp.config dfconfig,
                        eportfolio_comp_location.config, name, method, filename
				  FROM eportfolio_comp_location JOIN eportfolio_comp ON component = id
				  WHERE uid = '" . $uidID . "' AND component = '".$componentID."'
				  ORDER BY `column`, `position` LIMIT 1";

		return $this->db->getRow($query);
	}

	/**
	 * Colour theme for this user's ePortfolio.
	 *
	 * @return array|bool
	 */
	public function getColorList() {
		$query = "SELECT left_head_font, left_head_bg, left_font, left_bg_primary, left_bg_secondary, left_link, left_hover,
                  right_head_font, right_head_bg, right_font, right_bg_primary, right_bg_secondary, right_link, right_hover,
                  top_head_font, top_head_bg, top_font, top_bg_primary, top_bg_secondary, top_link, top_hover
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
	 * Adds a component
	 *
	 * @param  $component
	 * @return void
	 */
	public function addComponent($component) {
		$query = "SELECT config FROM eportfolio_comp WHERE id = '$component'";
		$componentSetup = $this->db->getRow($query);

		 /* We are inserting component at the top of the left column so we move all the existing components down */
		$query = "UPDATE eportfolio_comp_location SET position = position+1 WHERE uid = ".$this->uid." AND `column` = 1";
		$this->db->query($query);

		$this->saveComponentPosition($component, 1, 1, $componentSetup['config']);
	}

	/**
	 * Delete component positions
	 *
	 * @return void
	 */
	public function deleteComponentPositions() {
		$this->db->delete('eportfolio_comp_location', 'uid', $this->uid);
	}

	/**
	 * Delete component
	 *
	 * @param  $component
	 * @return void
	 */
    function deleteComponent($component) {
        $query = "DELETE FROM eportfolio_comp_location WHERE uid = '$this->uid' AND eportfolioComponentLocationID = '$component'";
        $this->db->query($query);
    }

	/**
	 * Updates location of components.
	 *
	 * @param  $component
	 * @param  $column
	 * @param  $position
	 * @return void
	 */
	public function updateComponentPosition($component, $column, $position) {
		$data = array(
			'eportfolioComponentLocationID' => $component,
			'uid' => $this->uid,
			'column' => $column,
			'position' => $position
		);

		$this->db->update('eportfolio_comp_location', 'eportfolioComponentLocationID', $data);
	}

	/**
	 * Records component configuration
	 *
	 * @param  $component
	 * @param  $config
	 * @return void
	 */
	public function updateComponentConfig($component, $config) {
		$query = array(
			'eportfolioComponentLocationID' => $component,
			'config' => $config
		);

		$this->db->update('eportfolio_comp_location', array('eportfolioComponentLocationID'), $query);
	}


	/**
	 * For updating available components
	 *
	 * @param  $submitListComp
	 * @return void
	 */
	public function updateComponents($submitListComp){
		// Get components
		$query = "SELECT `uid`, `position`, `column`, `component`, `config`
			FROM eportfolio_comp_location
			WHERE uid = '{$this->uid}'
			ORDER BY `position`";

		$currentListComp = $this->db->getAll($query);

		$submitListComp = is_array($submitListComp) ? $submitListComp : array();

		if (is_array($currentListComp)) {
			// Remove deleted ones
			foreach($currentListComp as $key => $comp) {
				if(!in_array($comp['component'], $submitListComp)) {
					unset($currentListComp[$key]);
				}
				else {
					unset($submitListComp[array_search($comp['component'], $submitListComp)]);
				}
			}
		}

		// Add new ones to end finding their
		$newListComp = $currentListComp;

		if (is_array($submitListComp)) {
			foreach($submitListComp as $key => $comp) {
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
			foreach($newListComp as $key => $newComp) {
				$newListComp[$key]['position'] = $count[$newComp['column'] - 1];
				$count[$newComp['column'] - 1]++;
			}
		}

		$newListComp = is_array($newListComp) ? $newListComp : array();
		$newListComp = array_values($newListComp);

		// Update
		$this->deleteComponentPositions();

		if (is_array($newListComp)) {
			foreach($newListComp as $newComp) {
				$this->saveComponentPosition($newComp['component'], $newComp['column'], $newComp['position'], $newComp['config']);
			}
		}
	}

	/**
	 * Saves component position
	 *
	 * @param  $component
	 * @param  $column
	 * @param  $position
	 * @param  $config
	 * @return void
	 */
	public function saveComponentPosition($component, $column, $position, $config) {
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
	 * @param  $listColor
	 * @return
	 */
	public function saveDisplayProperties($listColor) {

        if(!$this->eportfolioConfigExists($this->uid)) {
            $this->initialiseHomepage($this->uid);
        }

        $listColor['uid'] = $this->uid;
        $this->db->update('eportfolio_config', 'uid', $listColor);

	}

	/**
	 * Can the current user edit the userID's portfolio contents? This should
	 * probably go into the User library
	 *
	 * @param  $userID
	 * @return bool
	 */
    public function canEdit($userID) {
        $groupHandler = new Group();

        // can always edit your own
        if($_SESSION['id'] == $userID) {
            return true;
        }

        // different cases based on the owner of the folio
        if($groupHandler->isStaff($userID)) {
            // staff: can be edited by you or supers
            if ($groupHandler->isSuperuser()) {
                return true;
            }
        } elseif($groupHandler->isStudent($userID)) {
            // student: can be edited by you, your parents, teachers or supers
            if ($groupHandler->isSuperUser() ||
                $groupHandler->isStaff() ||
                $groupHandler->isParentOfChild($userID)) {
                return true;
            }
        }

        return false;
    }

	/**
	 * Determines whether the current user can view/edit a eportfolio's private notes
	 *
	 * @param  $userID
	 * @return bool
	 */
    public function canEditPrivateNotes($userID) {
        $groupHandler = new Group();

        // different cases based on the owner of the folio
        if($groupHandler->isStaff($userID)) {
            // staff: can be edited by you or supers
            if ($groupHandler->isSuperuser() || ($_SESSION['id'] == $userID)) {
                return true;
            }
        }
        elseif($groupHandler->isStudent($userID)) {
            // student: can be edited by your parents, teachers or supers...but not you
            if ($groupHandler->isSuperUser() ||
                $groupHandler->isStaff()) {
                return true;
            }
        }
        return false;
    }

    public function canView($userID) {
        $groupHandler = new Group();

        // supers, staff, your parents and you can view your folio
        if ($groupHandler->isSuperUser() ||
            $groupHandler->isStaff() ||
            $groupHandler->isParentOfChild($userID)) {
            return true;
        }

        return false;
    }

	/**
	 * Gets the column widths for a particular eportfolio
	 *
	 * @return array|bool
	 */
	public function getColumnWidths() {
		$query = "SELECT left_column_width, right_column_width
				  FROM eportfolio_config
				  WHERE uid = '{$this->uid}'";

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

        if(!$this->eportfolioConfigExists($this->uid)) {
            $this->initialiseHomepage($this->uid);
        }

        $columns = array('left_column_width'=> $left,
                          'right_column_width' => $right,
                          'uid' => $this->uid);
        $this->db->update('eportfolio_config', 'uid', $columns);

	}
}
