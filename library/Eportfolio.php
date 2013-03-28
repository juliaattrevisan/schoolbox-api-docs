<?php
/**
 * Eportfolio
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

class Eportfolio {

	/* Used to remove homepage components and reset template
	 */
	const REMOVE_EPORTFOLIO_TEMPLATE_ID = -1;
	const KEEP_EXISTING_EPORTFOLIO = 0;

	/**
	 * An instance of dbManager
	 *
	 * @var DeprecatedDatabase|null
	 */
	private $db = null;

	/**
	 * The owner/creator of the current eportfolio
	 *
	 * @var int
	 */
	private $userId = -1;

	/**
	 * This is the prefix used for all the cloned sql queries
	 *
	 * @var string
	 */
	private $context = 'eportfolio';

	/**
	 * Constructor
	 *
	 * @param $userId
	 */
	public function __construct($userId) {
		$this->setDb(new DeprecatedDatabase(SQL_db));
		$this->setUserId($userId);
	}

	/**
	 * Returns the current template
	 *
	 * @return mixed
	 */
	public function getTemplate() {
		$query = "
			SELECT template
			FROM {$this->getContext()}_config
			WHERE uid = '{$this->getUserId()}'
		";

		return $this->getDb()->getOne($query);
	}

	/**
	 * @deprecated
	 * @param null $type
	 * @param null $userId
	 *
	 * @return array|bool
	 */
	public function getComponentList($type = null, $userId = null) {
		if (!is_numeric($userId)) {
			$userId = $this->getUserId();
		}

		$query = "
			SELECT
			t.{$this->getContext()}ComponentCategoryID AS categoryID,
			t.name as categoryName,
			id, c.name, component, description, allowMultiple,
				(SELECT COUNT(*)
				FROM {$this->getContext()}_comp_location ecl
				WHERE ecl.component = c.id AND ecl.cid = {$userId}) as componentCount
				FROM {$this->getContext()}_comp c
				JOIN {$this->getContext()}ComponentCategory t ON t.{$this->getContext()}ComponentCategoryID = {$this->getContext()}CategoryID
				LEFT JOIN {$this->getContext()}_comp_location l ON l.component = c.id AND uid = '{$userId}'
				WHERE (invisible = 'false' OR l.component IS NOT NULL)
		";

		if (is_numeric($type)) {
			$query .= " AND component = $type";
		}

		$query .= " GROUP BY id ORDER BY {$this->getContext()}CategoryID, c.name";

		return $this->db->getAll($query);
	}

	/**
	 * @deprecated
	 * @param null $userId
	 * @return array|bool
	 */
	public function getSimpleComponentList ($userId = null) {
		if ( !is_numeric($userId) ) {
			$userId = $this->getUserId();
		}

		$query = "
            SELECT `{$this->getContext()}ComponentLocationID`, `uid`, `position`, `column`, `component`, `config`
            FROM {$this->getContext()}_comp_location
            WHERE uid = '{$userId}'
        ";

		$componentList = $this->db->getAll($query);

		return $componentList;
	}

	/**
	 * @deprecated
	 * @param $componentList
	 * @param null $userId
	 */
	public function setComponentList ($componentList, $userId = null) {
		if ( !is_numeric($userId) ) {
			$userId = $this->getUserId();
		}

		$this->deleteComponentPositions($userId);

		if ( is_array($componentList) ) {
			foreach ( $componentList as $component ) {
				$this->db->insert($this->getContext() . '_comp_location', $component);
			}
		}
	}

	/**
	 * Gets the column widths for a particular homepage
	 *
	 * @return array|bool
	 */
	public function getColumnWidths() {
		$query = "
			SELECT left_column_width, right_column_width
			FROM {$this->getContext()}_config
			WHERE uid = '{$this->getUserId()}'
		";

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
		$columnWidths = array(
			'uid' => $this->getUserId(),
			'left_column_width' => $left,
			'right_column_width' => $right,
		);

		$this->db->update($this->getContext() . '_config', 'uid', $columnWidths);
	}


	/**
	 * Get a list of colours for a particular page
	 *
	 * @param $pageId
	 * @return array|bool
	 */
	public function getColorList($pageId) {
		$query = "
			SELECT
				left_head_font, left_head_bg, left_font, left_bg_primary, left_bg_secondary, left_link, left_hover,
				right_head_font, right_head_bg, right_font, right_bg_primary, right_bg_secondary, right_link, right_hover,
				top_head_font, top_head_bg, top_font, top_bg_primary, top_bg_secondary, top_link, top_hover
			FROM {$this->getContext()}_config
			WHERE uid = '{$this->getUserId()}'
			AND page_id = '{$pageId}'
		";

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

	/**
	 * Create/Update a page template
	 *
	 * @param $template
	 */
	public function savePageTemplate($template) {
		$query = "
			SELECT uid
			FROM {$this->getContext()}_config
			WHERE uid = '{$this->getUserId()}'
		";

		$this->db->query($query);

		if ($row = $this->db->getNextArray()) {
			$query = "UPDATE {$this->getContext()}_config SET template = '$template' WHERE uid = '{$this->getUserId()}'";
		}
		else {
			$query = "INSERT INTO {$this->getContext()}_config (uid, template) VALUES ('{$this->getUserID()}', '$template')";
		}

		$this->db->query($query);
	}

//	/**
//	 * Delete an eportfolio
//	 */
//	public function deleteEportfolio() {
//		require_once dirname(__FILE__) . '/group.php';
//		$groupHandler = new Group;
//		$groupHandler->disbandGroup($this->getUserId());
//
//		$this->emptyHomepage();
//
//		$this->db->delete($this->getContext() . '_config', 'uid', $this->getUserId());
//		$this->deleteComponentPositions();
//	}


//	/**
//	 * Removes componentry from eportfolio and resets to the template that was used to create it.
//	 * Also soft-deletes eportfolio content where applicable
//	 *
//	 * @return void
//	 */
//	public function emptyHomepage() {
//		// TODO for 13.0.0: refactor this to call iterate across Component_Homepage_*Controllers instead, and call deleteData() if available
//		// soft delete the data using handlers
//		$allComponents = array(
//			'Assess_Main', 'Blog', 'Calendar', 'CategoryNews', 'Chat', 'Dropbox', 'File', 'Forum',
//			'Poll', 'Submission', 'Survey_Main', 'TextBox', 'Wiki'
//		);
//
//		if (is_array($allComponents) && count($allComponents)) {
//			foreach ($allComponents as $currentComponent) {
//				$handler = new $currentComponent();
//				$handler->homepageClean($this->cid);
//			}
//		}
//
//		// reset the homepage
//		$categoryHandler = new Category();
//		$category = $categoryHandler->getByID($this->cid);
//		$this->resetHomepageToTemplate($category['template']);
//
//	}


	/**
	 * Resets a eportfolio to a template, or destroys the eportfolio if it is to be removed.
	 * Returns the ID of the template used, or 0 if none.
	 *
	 * @param $templateID
	 * @return int
	 */
	public function resetEportfolioToTemplate($templateID) {
		if ($templateID != self::KEEP_EXISTING_EPORTFOLIO) {

			// if 'remove homepage', delete config and components, then set the templateID to zero
			if ($templateID == self::REMOVE_EPORTFOLIO_TEMPLATE_ID) {
				$templateID = $this->removeAllComponentsFromEportfolio($this->getUserId());
			}

			// now continue as normal
			if ($templateID > 0) {
				$this->createEportfolioFromTemplate($templateID);
			}
			else {
				// we don't want an invalid template ID going into the database
				$templateID = 0;
			}

			// deal with the template
			$this->db->update($this->getContext() . '_config', 'uid', array(
				'uid' => $this->getUserId(),
				'template' => $templateID
			));
		}

		return $templateID;
	}

	/**
	 * This simply copies a bunch of rows, changing 'eportfolio_template' to 'eportfolio'...quite simple, really.
	 *
	 * @param $templateID
	 */
	public function createEportfolioFromTemplate($templateID) {
		// locate all the component instances associated with this homepage (if they exist), and clear them out
		$cim = Component_InstanceMapper::getInstance();
		$collection = $cim->findByColumnList(array('module' => $this->getContext(), 'identifier' => $this->getUserId()));
		$cim->delete($collection);

		$userId = mysql_real_escape_string($this->getUserId(), $this->db->dbf);
		$templateID = mysql_real_escape_string($templateID, $this->db->dbf);

		$sql = "
            SELECT id AS template, left_column_width, right_column_width
            FROM {$this->getContext()}Template
            WHERE id = '{$templateID}'
        ";

		$x[] = array($sql);

		// retrieve template data
		$tpl = $this->db->getRow($sql);

		// insert homepage instance into homepage_config
		$eportfolioConfig = array(
			'uid' => $userId,
			'template' => $templateID,
			'left_column_width' => $tpl['left_column_width'],
			'right_column_width' => $tpl['right_column_width'],
		);

		foreach(array('top','left','right') as $position) {
			$eportfolioConfig = array_merge($eportfolioConfig, array(
				$position . '_head_font' => Cfg::get('homepage_colour_head_font'),
				$position . '_head_bg' => Cfg::get('homepage_colour_head_bg'),
				$position . '_font' => Cfg::get('homepage_colour_font'),
				$position . '_bg_primary' => Cfg::get('homepage_colour_bg_primary'),
				$position . '_bg_secondary' => Cfg::get('homepage_colour_bg_secondary'),
				$position . '_link' => Cfg::get('homepage_colour_link'),
				$position . '_hover' => Cfg::get('homepage_colour_hover')
			));
		}

		// remove (if exists) and replace the homepage config with the new template details
		$this->db->delete($this->getContext() .'_config', 'uid', $userId);
		$this->db->insert($this->getContext() .'_config', $eportfolioConfig);

		// retrieve, rearrange and reinsert the componentry
		$cim = Component_InstanceMapper::getInstance();
		$ccm = Component_ConfigMapper::getInstance();
		$instances = $cim->findByContext($this->getContext() . '_template', $templateID);

		if(count($instances)) {
			foreach($instances as $inst) {
				// preserve the config
				$configs = $inst->config;

				unset($inst->id);
				unset($inst->config);

				$inst->module = $this->getContext();
				$inst->identifier = $userId;

				$ids = $cim->save($inst);
				$id = $ids[0];

				if(count($configs)) {
					foreach($configs as $c) {
						if($c->id) {
							$conf = new Component_Config();
							$conf->instanceId = $id;
							$conf->value = $c->value;
							$conf->configDefinitionId = $c->id;
							$ccm->save($conf);
						}
					}
				}
			}
		}
	}


	/**
	 * Can be used as a workaround for an absence of DB default field values,
	 * but also allows for dynamic value duplication
	 *
	 * @param $table
	 * @param array $map
	 * @param string $sqlCondition
	 * @param string $indexField
	 * @return array
	 */
	private function duplicateRowsOnTable($table, array $map, $sqlCondition = '', $indexField = 'id') {
		$queries = array();
		$sql = "SELECT * FROM $table $sqlCondition";
		$queries[] = $sql;

		$result = $this->db->query($sql);
		while ($r = mysql_fetch_assoc($result)) {
			// hack at the data, then flick it back in
			unset($r[$indexField]);

			if (count($map)) {
				foreach ($map as $k => $v) {
					$r[$k] = $v;
				}
			}
			$keys = implode('`, `', array_keys($r));
			$values = implode("', '", array_values($r));

			$q = "INSERT INTO `$table` (`$keys`) VALUES ('$values')";
			$queries[] = $q;

			$this->db->query($q);
		}

		return $queries;
	}


	/**
	 * Delete all componentry from an Eportfolio
	 *
	 * @return int
	 */
	function removeAllComponentsFromEportfolio() {
		$userId = mysql_real_escape_string($this->getUserId(), $this->db->dbf);

		// locate all the component instances associated with this homepage, and clear them out
		$cim = Component_InstanceMapper::getInstance();
		$collection = $cim->findByColumnList(array('module' => $this->getContext(), 'identifier' => $userId));
		$cim->delete($collection);

		return 0;
	}

	/**
	 * @deprecated
	 * @param $component
	 * @return int
	 */
	function addComponent($component) {
		$query = "
			SELECT config
			FROM {$this->getContext()}_comp
			WHERE id = '$component'
		";

		$componentSetup = $this->db->getRow($query);

		/* We are inserting component at the top of the left column so we move all the existing components down
		 */
		$query = "UPDATE {$this->getContext()}_comp_location SET position = position+1 WHERE uid = '{$this->getUserId()}' AND `column` = 1";

		$this->db->query($query);

		return $this->saveComponentPosition($component, 1, 1, $componentSetup['config']);
	}

	/**
	 * @deprecated
	 * @param null $categoryId
	 */
	public function deleteComponentPositions($userId = null) {
		if ( !is_numeric($userId) ) {
			$userId = $this->getUserId();
		}

		$this->db->delete($this->getContext() . '_comp_location', 'cid', $userId);
	}

	/**
	 * @deprecated
	 * @param $component
	 */
	public function deleteComponent($component) {
		$query = "
			DELETE FROM {$this->getContext()}_comp_location
			WHERE uid = '{$this->getUserId()}'
			AND {$this->getContext()}ComponentLocationID = '$component'
		";

		$this->db->query($query);
	}

	/**
	 * @deprecated
	 * @param $component
	 * @param $column
	 * @param $position
	 */
	public function updateComponentPosition($component, $column, $position) {
		$key = $this->getContext() . 'ComponentLocationID';

		$query = array(
			$key => $component,
			"column" => $column,
			"position" => $position
		);

		$result = $this->db->update($this->getContext() . '_comp_location', $this->getContext() . 'ComponentLocationID', $query);
	}

	/**
	 * @deprecated
	 * @param $component
	 * @param $config
	 */
	public function updateComponentConfig($component, $config) {
		$key = $this->getContext() . 'ComponentLocationID';

		$query = array(
			$key => $component,
			"config" => $config
		);

		$this->db->update($this->getContext() . '_comp_location', $this->getContext() . 'ComponentLocationID', $query);
	}

	/**
	 * For updating available components
	 *
	 * @deprecated
	 * @param $submitListComp
	 * @param null $userId
	 */
	public function updateComponents($submitListComp, $userId = null){
		if ( !is_numeric($userId) ) {
			$userId = $this->getUserId();
		}

		// Get components
		$query = "
			SELECT `uid`, `position`, `column`, `component`, `config`
			FROM {$this->getContext()}_comp_location
			WHERE uid = '{$userId}'
			ORDER BY `position`
		";

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
				$query = "
					SELECT size, config
					FROM {$this->getContext()}_comp
					WHERE id = $comp
				";

				$result = $this->db->getRow($query);

				$newComp = array(
					"uid" => $userId,
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
	 * @deprecated
	 * @param $component
	 * @param $column
	 * @param $position
	 * @param $config
	 * @return int
	 */
	public function saveComponentPosition($component, $column, $position, $config) {
		$query = array(
			"uid" => $this->getUserId(),
			"component" => $component,
			"column" => $column,
			"position" => $position,
			"config" => $config
		);

		$this->db->insert($this->getContext() . '_comp_location', $query);

		return $this->db->insertID();
	}

	/**
	 * @deprecated
	 * @param $eportfolioComponentLocationID
	 * @param $config
	 * @return bool
	 */
	public function setComponentConfig($eportfolioComponentLocationID, $config) {
		$key = $this->getContext() . 'ComponentLocationID';

		$result =  $this->db->update($this->getContext() . '_comp_location', $this->getContext() . 'ComponentLocationID', array(
			$key => $eportfolioComponentLocationID,
			'config' => serialize($config)
		));

		return $result;
	}

	/**
	 * @deprecated
	 * @param $eportfolioComponentLocationID
	 * @return mixed
	 */
	public function getComponentConfig($eportfolioComponentLocationID) {
		$query = "
			SELECT config
			FROM {$this->getContext()}_comp_location
			WHERE {$this->getContext()}ComponentLocationID = {$eportfolioComponentLocationID}
		";

		$config = unserialize($this->db->getOne($query));

		return $config;
	}

	/**
	 * @param $listColor
	 * @return resource
	 */
	public function saveDisplayProperties($listColor) {
		$query = "UPDATE {$this->getContext()}_config SET ";

		foreach($listColor as $key => $color) {
			$query .= "`".mysql_real_escape_string($key)."` = '".mysql_real_escape_string($color)."', ";
		}

		$query = substr($query, 0, -2);
		$query .= " WHERE uid = '{$this->getUserId()}'";

		return $this->db->query($query);
	}

	/**
	 * @param null $userId
	 * @return array|bool
	 */
	public function getConfig($userId = null) {
		if ( !is_numeric($userId) ) {
			$userId = $this->getUserId();
		}

		$query = "
            SELECT `uid`, `template`, `left_column_width`, `right_column_width`, `left_head_font`, `left_head_bg`,
                `left_font`, `left_bg_primary`, `left_bg_secondary`, `left_link`, `left_hover`, `right_head_font`,
                `right_head_bg`, `right_font`, `right_bg_primary`, `right_bg_secondary`, `right_link`, `right_hover`,
                `top_head_font`, `top_head_bg`, `top_font`, `top_bg_primary`, `top_bg_secondary`, `top_link`, `top_hover`
            FROM {$this->getContext()}_config
            WHERE `uid` = '{$userId}'
        ";

		$config = $this->db->getRow($query);

		return $config;
	}

	public function setConfig ($config, $userId = null) {
		if ( !is_numeric($userId) ) {
			$userId = $this->getUserId();
		}

		if ($cleanConfig = $this->getConfig($userId) ) {
			$this->db->update($this->getContext() . '_config', 'uid', $config);
		}
		else {
			$this->db->insert($this->getContext() . '_config', $config);
		}
	}

	public function deleteConfig ($userId = null) {
		if ( !is_numeric($userId) ) {
			$userId = $this->getUserId();
		}

		$this->db->delete($this->getContext() . '_config', 'uid', $userId);
	}

	public function migrateEportfolioConfiguration ($sourceUserd, $destinationUserId) {
		/* Copy config from source to destination */
		if ( $config = $this->getConfig($sourceUserd) ) {
			$config['uid'] = $destinationUserId;

			$this->setConfig($config, $destinationUserId);
		}

		/* Copy components from source to destination */
		$componentList = $this->getSimpleComponentList($sourceUserd);

		if ( is_array($componentList) ) {
			foreach ( $componentList as &$component ) {
				unset($component[$this->getContext() . 'ComponentLocationID'], $component['config']);

				$component['uid'] = $destinationUserId;
			}
		}

		$this->deleteComponentPositions($destinationUserId);

		$this->setComponentList($componentList, $destinationUserId);
	}

	/* Getters/Setters
	 */

	/**
	 * @param \DeprecatedDatabase|null $db
	 */
	private function setDb($db) {
		$this->db = $db;
	}

	/**
	 * @return \DeprecatedDatabase|null
	 */
	private function getDb() {
		return $this->db;
	}

	/**
	 * @param int $userId
	 */
	private function setUserId($userId) {
		$this->userId = $userId;
	}

	/**
	 * @return int
	 */
	private function getUserId() {
		return $this->userId;
	}

	/**
	 * @return string
	 */
	private function getContext() {
		return $this->context;
	}
}