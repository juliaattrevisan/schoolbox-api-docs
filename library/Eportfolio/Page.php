<?php
/**
 * Eportfolio_Page
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

class Eportfolio_Page {

	/**
	 * An instance of sitepage
	 *
	 * @var null
	 */
	protected $page = null;

	/**
	 * An instance of userHandler
	 *
	 * @var null
	 */
	protected $userHandler = null;

	/**
	 * An instance of eportfolioHandler
	 *
	 * @var null
	 */
	protected $eportfolioHandler = null;

	/**
	 * The id of the page being viewed
	 *
	 * @var int
	 */
	protected $pageId = -1;

	/**
	 * The template file to use
	 *
	 * @var string
	 */
	protected $templateFile = 'eportfolio/index.html';

	/**
	 * An instance of Component_FactoryAbstract
	 *
	 * @var null
	 */
	protected $componentFactory = null;

	/**
	 * An instance of Collection
	 *
	 * @var null
	 */
	protected $componentCollection = null;

	/**
	 * A list of colours to use
	 *
	 * @var null
	 */
	protected $colours = null;

	/**
	 * The user record for the owner of the page being viewed
	 *
	 * @var array
	 */
	protected $user = array();

	/**
	 * A list of component tab categories
	 *
	 * @var array
	 */
	protected $tabList = array();

	/**
	 * Constructor
	 *
	 * @param $page
	 * @param $userId
	 * @param $pageId
	 */
	public function __construct($page, $userId, $pageId) {
		$this->setPage($page);
		$this->setPageId($pageId);
		$this->setUserHandler(new User());

		/* Check that the user exists
		 */
		$this->validateUser($userId);

		/* If the user is valid, initiate everything
		 */
		$this->setEportfolioHandler(new Eportfolio($this->getUserId(), $this->getPageId()));

		$this->validatePageTemplate();

		/* Assemble list of components
		 */
		$this->setComponentFactory(new Component_Eportfolio_Factory($this->getUserHandler(), $this->getPage()));
		$this->setComponentCollection($this->getComponentFactory()->buildComponentList($this->getPageId()));

		/* Page colours
 		 */
		$this->setColours($this->getEportfolioHandler()->getColorList($this->getPageId()));

		/* Setup template and stuff
		 */
		$this->setup();
	}

	/**
	 * Validate the user id to check that the user exists
	 * If he does, remember his info
	 *
	 * @param $userId
	 */
	private function validateUser($userId) {
		/* Retrieve the user ID from the URI, must be an integer and at least 1 character long
		 */
		if (preg_match('/^\d+$/', $userId)) {
			$user = $this->getUserHandler()->getByID($userId);
		}

		/* if we still have no user, advise that these are not the droids you're looking for
		 */
		if (!isset($user['uid'])) {
			error(18);
		}

		$this->setUser($user);
	}

	/**
	 * Check that the page being viewed has a template
	 */
	private function validatePageTemplate() {
		if ($this->getEportfolioHandler()->getTemplate() === false) {
			die('no template');
		}
	}

	/**
	 * Set up blocks and stuff
	 */
	protected function setup() {
		$this->getPage()->setFile(TEMPLATE_content, $this->getTemplateFile());

		/* Set up blocks and stuff
		 */
		$this->getPage()->setTitle('page name goes here');
		$this->getPage()->setBlock('custom_head', 'modify_layout', 'modify_layout_block');
		$this->getPage()->setBlock('custom_head', 'allowed_files', 'allowed_files_block');
		$this->getPage()->setBlock(TEMPLATE_content, 'edit_page', 'edit_page_block');
		$this->getPage()->setBlock(TEMPLATE_content, 'customise_page', 'customise_page_block');
		$this->getPage()->setBlock('custom_head', 'colour_list', 'colour_list_block');

		/* Set meta data
		 */
		$this->getPage()->setVar(array(
			'HEADING' => $this->getUserName(),
			'ID' => $this->getUserId(),
			'USER_TOKEN' => $_SESSION['key'],
			'PAGE_TITLE' => $this->getEportfolioHandler()->getPageTitle(),
			'PAGE_ID' => $this->getEportfolioHandler()->getPageId(),
		));

		$this->getPage()->setVar(array(
			'edit_page_block' => '',
			'modify_layout_block' => '',
			'customise_page_block' => '',
			'colour_list_block' => '',
		));

		if ($this->getPermission() > WRITE_ACCESS) {
			$this->getPage()->setBlock('customise_page', 'eportfolio_component_tab', 'eportfolio_component_tab_block');
			$this->getPage()->setBlock('customise_page', 'eportfolio_component_page', 'eportfolio_component_page_block');
			$this->getPage()->setBlock('eportfolio_component_page', 'eportfolio_component', 'eportfolio_component_block');
			$this->getPage()->parse('edit_page_block', 'edit_page');
			$this->getPage()->parse('modify_layout_block', 'modify_layout');
			$this->getPage()->parse('customise_page_block', 'customise_page');
		}
	}

	/**
	 * Render everything
	 */
	public function render() {
		if ($this->getPermission() > WRITE_ACCESS) {
			$this->renderComponents();
			$this->renderTabList();
			$this->renderColours();
		}
		else {
			$this->getPage()->setVar(array(
				'edit_page_block' => '',
				'modify_layout_block' => '',
				'customise_page_block' => '',
				'colour_list_block' => '',
			));
		}

		$this->renderDefaultColours();
		$this->renderColumnWidths();
		$this->renderAllowedFileTypes();
	}

	/**
	 * Render components
	 */
	private function renderComponents() {
		$instanceCollection = $this->getComponentFactory()->getDefinitionList('eportfolio_template');

		foreach ($instanceCollection as $instance) {
			$instance->instanceCount = 0;

			$instance->definition->componentCategory = $instance->identifier;
			$instance->definition->componentCategoryName = $this->getEportfolioHandler()->getTemplateNameById($instance->identifier);

			foreach ($this->getComponentCollection() as $inst) {
				if ($inst->definitionId == $instance->definitionId) {
					++$instance->instanceCount;
				}
			}
		}

		if ($total = $instanceCollection->count()) {
			$i = 0;
			$previous = null;

			foreach ($instanceCollection as $component) {
				++$i;
				$class = '';

				if ($component->definition->multiple == 'false') {
					$class = 'one-only ';

					if ($component->definition->multiple == 'false' && $component->instanceCount > 0) {
						$class .= 'disabled ';
					}
				}

				$class = trim($class);

				$this->getPage()->setVar(array(
					'EPORTFOLIO_COMPONENT_ID' => Scrub::text($component->definitionId),
					'EPORTFOLIO_COMPONENT_NAME' => Scrub::text($component->definition->name),
					'EPORTFOLIO_COMPONENT_COUNT' => $component->instanceCount,
					'EPORTFOLIO_COMPONENT_DESCRIPTION' => Scrub::text($component->definition->description),
					'EPORTFOLIO_COMPONENT_CLASS' => $class,
					'EPORTFOLIO_COMPONENT_CATEGORY_ID' => $previous->identifier,
				));

				if ($component->definition->componentCategory != $previous->definition->componentCategory && $i > 1) {
					$this->getPage()->parse('eportfolio_component_page_block', 'eportfolio_component_page', true);
					$this->getPage()->setVar('eportfolio_component_block', '');
					$this->getPage()->parse('eportfolio_component_block', 'eportfolio_component', true);

					// last rotation
					if ($total == $i) {
						$this->getPage()->setVar('EPORTFOLIO_COMPONENT_CATEGORY_ID', $component->definition->componentCategory);
						$this->getPage()->parse('eportfolio_component_page_block', 'eportfolio_component_page', true);
					}
				}
				else {
					// last rotation
					if ($total == $i) {
						$this->getPage()->parse('eportfolio_component_block', 'eportfolio_component', true);
						$this->getPage()->setVar('EPORTFOLIO_COMPONENT_CATEGORY_ID', $component->definition->componentCategory);
						$this->getPage()->parse('eportfolio_component_page_block', 'eportfolio_component_page', true);
					}
					else {
						$this->getPage()->parse('eportfolio_component_block', 'eportfolio_component', true);
					}
				}

				$previous = $component;

				/* >:/
				 */
				$this->tabList[$component->definition->componentCategory]['name'] = $component->definition->componentCategoryName;
				$this->tabList[$component->definition->componentCategory]['count']++;
			}
		}
		else {
			$this->getPage()->setVar(array(
				'eportfolio_component_block' => '',
				'eportfolio_component_page_block' => 'No components are available for you to add.',
				'eportfolio_component_tab_block' => '',
			));
		}

		$occupiedPositionList = array();

		foreach ($this->getComponentCollection() as $component) {
			$templateTarget = "component_position_{$component->position}";
			$templateHandle = "component_{$component->definitionId}";

			$component->setup($this->getUser(), $component->instance->getConfigArray());

			if ($component->execute() !== false) {
				if ($component->renderTemplate($templateHandle) !== false) {
					if (!in_array($component->instance->position, $occupiedPositionList)) {
						$occupiedPositionList[] = $component->instance->position;
					}

					$this->getPage()->parse($templateTarget, $templateHandle, true);
				}
			}
		}

		$emptyPositionList = array_diff(array('top', 'left', 'right'), $occupiedPositionList);

		foreach ($emptyPositionList as $emptyPosition) {
			$this->getPage()->setVar("component_position_{$emptyPosition}", '');
		}
	}

	/**
	 * Render tabs (categories for components)
	 */
	private function renderTabList() {
		$tabList = $this->getTabList();

		if (isset($tabList) && count($tabList)) {
			foreach ($tabList as $id => $tab) {
				$this->getPage()->setVar(array(
					'COMPONENT_CATEGORY_ID' => $id,
					'COMPONENT_CATEGORY_NAME' => $tab['name'],
					'COMPONENT_CATEGORY_COUNT' => $tab['count']
				));

				$this->getPage()->parse('eportfolio_component_tab_block', 'eportfolio_component_tab', true);
			}
		}
	}

	/**
	 * Render customised colours
	 */
	private function renderColours() {
		// Colors (original)
		$this->getPage()->setVar('COLOUR_ARRAY', 'original');
		$this->getPage()->setVar($this->getColours());
		$this->getPage()->parse('colour_list_block', 'colour_list', true);

		// Colors (picker)
		$this->getPage()->setVar('COLOUR_ARRAY', 'pickers');
		$this->getPage()->setVar($this->getColours());
		$this->getPage()->parse('colour_list_block', 'colour_list', true);

		// Defaults
		$defaultColour = array(
			'head_font' => Cfg::get('eportfolio_colour_head_font'),
			'head_bg' => Cfg::get('eportfolio_colour_head_bg'),
			'font' => Cfg::get('eportfolio_colour_font'),
			'bg_primary' => Cfg::get('eportfolio_colour_bg_primary'),
			'bg_secondary' => Cfg::get('eportfolio_colour_bg_secondary'),
			'link' => Cfg::get('eportfolio_colour_link'),
			'hover' => Cfg::get('eportfolio_colour_hover')
		);

		$columns = array('top', 'left', 'right');
		$this->getPage()->setVar('COLOUR_ARRAY', 'defaults');

		foreach ($columns as $col) {
			foreach ($defaultColour as $field => $colour) {
				$this->getPage()->setVar($col . '_' . $field, $colour);
			}
		}

		$this->getPage()->parse('colour_list_block', 'colour_list', true);
	}

	/**
	 * Apply default colours to everything
	 */
	private function renderDefaultColours() {
		$defaultColour = array(
			'head_font' => Cfg::get('homepage_colour_head_font'),
			'head_bg' => Cfg::get('homepage_colour_head_bg'),
			'font' => Cfg::get('homepage_colour_font'),
			'bg_primary' => Cfg::get('homepage_colour_bg_primary'),
			'bg_secondary' => Cfg::get('homepage_colour_bg_secondary'),
			'link' => Cfg::get('homepage_colour_link'),
			'hover' => Cfg::get('homepage_colour_hover')
		);

		$this->getPage()->setVar('COLOUR_ARRAY', 'defaults');

		$columns = array('top', 'left', 'right');

		foreach ($columns as $col) {
			foreach ($defaultColour as $field => $colour) {
				$this->getPage()->setVar($col . '_' . $field, $colour);
			}
		}

		$this->getPage()->parse('colour_list_block', 'colour_list', true);

		/* Javascript colour values for the original
		 */
		$this->getPage()->setVar('COLOUR_ARRAY', 'original');
		$this->getPage()->setVar($this->getColours());
		$this->getPage()->parse('colour_list_block', 'colour_list', true);

		/* Javascript colour values for the picker
		 */
		$this->getPage()->setVar('COLOUR_ARRAY', 'pickers');
		$this->getPage()->setVar($this->getColours());
		$this->getPage()->parse('colour_list_block', 'colour_list', true);
	}

	/**
	 * Figure out column widths
	 */
	private function renderColumnWidths() {
		$allWidths = $this->getEportfolioHandler()->getColumnWidths();

		$this->getPage()->setVar(array(
			'TEMPLATE_LEFT_COLUMN_WIDTH' => $allWidths['left_column_width'],
			'TEMPLATE_RIGHT_COLUMN_WIDTH' => $allWidths['right_column_width'],
		));
	}

	/**
	 * CSS classes for mime types
	 */
	private function renderAllowedFileTypes() {
		$allowedFiles = new AllowedFile();
		$allowedList = $allowedFiles->getAll();

		if (is_array($allowedList)) {

			// append link icon to results
			$allowedList[]['iconFile'] = "link.png";
			$last = '';

			foreach ($allowedList as $allowed) {
				if ($last != $allowed["iconFile"]) {
					$this->getPage()->setVar(array(
						"NAME" => $allowedFiles->getClass($allowed["iconFile"]),
						"ICON" => $allowed["iconFile"],
					));

					$this->getPage()->parse("allowed_files_block", "allowed_files", true);

					$last = $allowed["iconFile"];
				}
			}
		}
		else {
			$this->getPage()->setVar('allowed_files_block', '');
		}
	}

	/**
	 * Get permission level
	 *
	 * @return int
	 */
	private function getPermission() {
		return 99;
	}

	/**
	 * Get the user id for a person who exists
	 *
	 * @return mixed
	 */
	private function getUserId() {
		return $this->user['uid'];
	}

	/**
	 * Get the page owner's name
	 *
	 * @return string
	 */
	private function getUserName() {
		$user = $this->getUser();

		return Scrub::text($user['firstname'] . ' ' . $user['lastname']);
	}

	/* Getters/Setters
	 */

	/**
	 * @param null $colours
	 */
	private function setColours($colours) {
		$this->colours = $colours;
	}

	/**
	 * @return null
	 */
	private function getColours() {
		return $this->colours;
	}

	/**
	 * @param null $componentCollection
	 */
	private function setComponentCollection($componentCollection) {
		$this->componentCollection = $componentCollection;
	}

	/**
	 * @return null
	 */
	private function getComponentCollection() {
		return $this->componentCollection;
	}

	/**
	 * @param null $componentFactory
	 */
	private function setComponentFactory($componentFactory) {
		$this->componentFactory = $componentFactory;
	}

	/**
	 * @return null
	 */
	private function getComponentFactory() {
		return $this->componentFactory;
	}

	/**
	 * @param null $eportfolioHandler
	 */
	private function setEportfolioHandler($eportfolioHandler) {
		$this->eportfolioHandler = $eportfolioHandler;
	}

	/**
	 * @return null
	 */
	public function getEportfolioHandler() {
		return $this->eportfolioHandler;
	}

	/**
	 * @param null $page
	 */
	private function setPage($page) {
		$this->page = $page;
	}

	/**
	 * @return null
	 */
	private function getPage() {
		return $this->page;
	}

	/**
	 * @param int $pageId
	 */
	private function setPageId($pageId) {
		$this->pageId = $pageId;
	}

	/**
	 * @return int
	 */
	private function getPageId() {
		return $this->pageId;
	}

	/**
	 * @param array $tabList
	 */
	private function setTabList($tabList) {
		$this->tabList = $tabList;
	}

	/**
	 * @return array
	 */
	private function getTabList() {
		return $this->tabList;
	}

	/**
	 * @param string $templateFile
	 */
	private function setTemplateFile($templateFile) {
		$this->templateFile = $templateFile;
	}

	/**
	 * @return string
	 */
	private function getTemplateFile() {
		return $this->templateFile;
	}

	/**
	 * @param array $user
	 */
	private function setUser($user) {
		$this->user = $user;
	}

	/**
	 * @return array
	 */
	private function getUser() {
		return $this->user;
	}

	/**
	 * @param null $userHandler
	 */
	private function setUserHandler($userHandler) {
		$this->userHandler = $userHandler;
	}

	/**
	 * @return null
	 */
	private function getUserHandler() {
		return $this->userHandler;
	}
}