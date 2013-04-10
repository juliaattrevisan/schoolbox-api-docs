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
	 * The current user id
	 *
	 * @var int
	 */
	private $userId = -1;

	/**
	 * PageId of the current page.
	 *
	 * @var int
	 */
	private $pageId = -1;

	/**
	 * An instance of Eportfolio_Page
	 *
	 * @var array
	 */
	private $pageInstance = null;

	/**
	 * This is the prefix used for all the cloned sql queries
	 *
	 * @var string
	 */
	private $context = 'eportfolio';

	/**
	 * All eportfolio_template records
	 *
	 * @var array
	 */
	private $allTemplates = array();

	/**
	 * Constructor
	 *
	 * @param $userId
	 * @param $pageId
	 */
	public function __construct($userId, $pageId) {
		$this->setDb(new DeprecatedDatabase(SQL_db));
		$this->setUserId($userId);
		$this->setPageId($pageId);

		/* Preload settings for user-built pages
		 *
		 * Otherwise they are hardcoded pages and we don't need
		 * this extra information
		 */
		if (is_numeric($pageId)) {
			$pageInstance = Eportfolio_PageMapper::getInstance()->findById($pageId);

			$this->setPageInstance($pageInstance);
			$this->setAllTemplates($this->fetchAllTemplates());
		}
	}

	/**
	 * Delegate method
	 *
	 * Get the page title
	 *
	 * @return bool
	 */
	public function getSettingsPageTitle() {
		return $this->getPageInstance()->getPageTitle();
	}

	/**
	 * Delegate method
	 *
	 * Get the page id
	 *
	 * @return bool
	 */
	public function getSettingsPageId() {
		return $this->getPageInstance()->getPageId();
	}

	/**
	 * Delegate method
	 *
	 * Returns the current template id
	 *
	 * @return mixed
	 */
	public function getSettingsTemplate() {
		return $this->getPageInstance()->getTemplate();
	}

	/**
	 * Delegate method
	 *
	 * Returns the owner id of a given page
	 *
	 * @return bool
	 */
	public function getSettingsPageOwner() {
		return $this->getPageInstance()->getUid();
	}

	/**
	 * Update a page title
	 *
	 * @param $value
	 */
	public function updatePageTitle($value) {
		$this->getPageInstance()->setPageTitle($value);

		Eportfolio_PageMapper::getInstance()->save($this->getPageInstance());
	}

	/**
	 * Get all eportfolio_template records
	 *
	 * @return resource
	 */
	public function fetchAllTemplates() {
		$allTemplates = array();

		$query = "
			SELECT id, name
			FROM {$this->getContext()}Template
			ORDER BY id
		";

		$result = $this->getDb()->getAll($query);

		/* Do some transpositionary reflex
		 */
		if (is_array($result) && count($result)) {
			foreach($result as $currentTemplate) {
				$allTemplates[$currentTemplate['id']] = $currentTemplate['name'];
			}
		}

		return $allTemplates;
	}

	/**
	 * Get a template name by its id, from the entire set
	 *
	 * @param $id
	 */
	public function getTemplateNameById($id) {
		return $this->allTemplates[$id];
	}

	/**
	 * Gets the column widths for a particular homepage
	 *
	 * @return array
	 */
	public function getColumnWidths() {
		return array(
			'left_column_width' => $this->getPageInstance()->getLeftColumnWidth(),
			'right_column_width' => $this->getPageInstance()->getRightColumnWidth(),
		);
	}

	/**
	 * Update column widths
	 *
	 * @param  $left
	 * @param  $right
	 * @return void
	 */
	public function updateColumnWidths($left, $right) {
		$this->getPageInstance()->setLeftColumnWidth($left);
		$this->getPageInstance()->setRightColumnWidth($right);

		Eportfolio_PageMapper::getInstance()->save($this->getPageInstance());
	}

	/**
	 * Get a list of colours for a particular page
	 *
	 * @param $pageId
	 * @return array|bool
	 */
	public function getColorList($pageId) {
		$colourList = array(
			'left_head_font' => $this->getPageInstance()->getLeftHeadFont(),
			'left_head_bg' => $this->getPageInstance()->getLeftHeadBg(),
			'left_font' => $this->getPageInstance()->getLeftFont(),
			'left_bg_primary' => $this->getPageInstance()->getLeftBgPrimary(),
			'left_bg_secondary' => $this->getPageInstance()->getLeftBgSecondary(),
			'left_link' => $this->getPageInstance()->getLeftLink(),
			'left_hover' => $this->getPageInstance()->getLeftHover(),

			'right_head_font' => $this->getPageInstance()->getRightHeadFont(),
			'right_head_bg' => $this->getPageInstance()->getRightHeadBg(),
			'right_font' => $this->getPageInstance()->getRightFont(),
			'right_bg_primary' => $this->getPageInstance()->getRightBgPrimary(),
			'right_bg_secondary' => $this->getPageInstance()->getRightBgSecondary(),
			'right_link' => $this->getPageInstance()->getRightLink(),
			'right_hover' => $this->getPageInstance()->getRightHover(),

			'top_head_font' => $this->getPageInstance()->getTopHeadFont(),
			'top_head_bg' => $this->getPageInstance()->getTopHeadBg(),
			'top_font' => $this->getPageInstance()->getTopFont(),
			'top_bg_primary' => $this->getPageInstance()->getTopBgPrimary(),
			'top_bg_secondary' => $this->getPageInstance()->getTopBgSecondary(),
			'top_link' => $this->getPageInstance()->getTopLink(),
			'top_hover' => $this->getPageInstance()->getTopHover(),
		);

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
	 * Create a new ePortfolio page with title and template
	 *
	 * "This simply copies a bunch of rows, changing 'eportfolio_template' to 'eportfolio'...quite simple, really."
	 *
	 * @param $pageTitle
	 * @param $templateID
	 */
	public function createEportfolioFromTemplate($pageTitle, $templateID) {
		$userId = mysql_real_escape_string($this->getUserId(), $this->db->dbf);
		$templateID = mysql_real_escape_string($templateID, $this->db->dbf);

		/* Retrieve template data
		 */
		$query = "
            SELECT id AS template, left_column_width, right_column_width
            FROM {$this->getContext()}Template
            WHERE id = '{$templateID}'
        ";

		$tpl = $this->db->getRow($query);

		/* Compile an eportfolioConfig record
		 */
		$eportfolioConfig = array(
			'uid' => $userId,
			'page_title' => $pageTitle,
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

		/* Insert it
		 */
		$this->db->insert($this->getContext() .'_config', $eportfolioConfig);

		/* Remember the page that was just created, because we'll apply
		 * permissions to it after it gets created
		 */
		$this->setPageId($this->db->insertID());

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
				$inst->identifier = $this->getPageId();
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
	 * Delete an eportfolio
	 *
	 * Contents storted in other tables are retained
	 */
	public function delete() {
		$allComponents = Component_InstanceMapper::getInstance()->findByColumnList(array(
			'module' => 'eportfolio',
			'identifier' => $this->getPageId(),
		));

		/* Remove all the components
		 */
		$allInstanceIds = array();

		if ($allComponents->count()) {
			for ($allComponents->rewind(); $allComponents->valid(); $allComponents->next()) {
				$currentComponent = $allComponents->current();

				/* Remember this id so we can get their configs again later
				 */
				$allInstanceIds[] = $currentComponent->id;

				Component_InstanceMapper::getInstance()->delete($currentComponent);
			}

			/* Remove all the components' configs
			 */
			if (count($allInstanceIds)) {
				$allConfigs = Component_ConfigMapper::getInstance()->findIn('component_instance_id', $allInstanceIds);

				if ($allConfigs->count()) {
					for ($allConfigs->rewind(); $allConfigs->valid(); $allConfigs->next()) {
						$currentConfig = $allConfigs->current();

						Component_ConfigMapper::getInstance()->delete($currentConfig);
					}
				}
			}
		}

		/* Remove the page's permissions
		 */
		$allPermissions = Eportfolio_PermissionMapper::getInstance()->findByColumnList(array(
			'page_id' => $this->getPageId()
		));

		if ($allPermissions->count()) {
			for ($allPermissions->rewind(); $allPermissions->valid(); $allPermissions->next()) {
				$currentPermission = $allPermissions->current();

				Eportfolio_PermissionMapper::getInstance()->delete($currentPermission);
			}
		}

		/* Remove the page itself
		 */
		$pageInstance = Eportfolio_PageMapper::getInstance()->findById($this->getPageId());
		Eportfolio_PageMapper::getInstance()->delete($pageInstance);

		/* Redirect back to the profile
		 */
		SitePage::localRedir('/eportfolio/' . $this->getUserId() . '/profile');
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
	 * Save colours for individual pages
	 *
	 * @param $listColor
	 * @return resource
	 */
	public function saveDisplayProperties($listColor) {
		$query = "
			UPDATE {$this->getContext()}_config
			SET
		";

		$stack = array();

		foreach($listColor as $key => $color) {
			$field = mysql_real_escape_string($key);
			$value = mysql_real_escape_string($color);

			$stack[] = "`{$field}` = '{$value}'";
		}

		$query .= implode(',', $stack);

		$query .= "
			WHERE uid = '{$this->getUserId()}'
			AND page_id = {$this->getPageId()}
		";

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
	 * Set user id
	 *
	 * @param int $userId
	 */
	private function setUserId($userId) {
		$this->userId = $userId;
	}

	/**
	 * Get user id
	 *
	 * @return int
	 */
	public function getUserId() {
		return $this->userId;
	}

	/**
	 * Get context
	 *
	 * @return string
	 */
	private function getContext() {
		return $this->context;
	}

	/**
	 * Set page id
	 *
	 * @param int $pageId
	 */
	private function setPageId($pageId) {
		$this->pageId = $pageId;
	}

	/**
	 * Get page id
	 *
	 * @return int
	 */
	public function getPageId() {
		return $this->pageId;
	}

	/**
	 * Set page instance
	 *
	 * @param array $pageInstance
	 */
	private function setPageInstance($pageInstance) {
		$this->pageInstance = $pageInstance;
	}

	/**
	 * Get page instance
	 *
	 * @return array
	 */
	private function getPageInstance() {
		return $this->pageInstance;
	}

	/**
	 * @param array $allTemplates
	 */
	private function setAllTemplates($allTemplates) {
		$this->allTemplates = $allTemplates;
	}

	/**
	 * @return array
	 */
	private function getAllTemplates() {
		return $this->allTemplates;
	}
}
