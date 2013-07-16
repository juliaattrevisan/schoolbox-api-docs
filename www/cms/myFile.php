<?php

$fileHandler = new MyFile();
$folderHandler = new MyFolder();


$this->setFile(TEMPLATE_content, 'cms/myFile.html');
$this->setBlock(TEMPLATE_content, 'browse_row', 'browse_row_block');
$this->setBlock(TEMPLATE_content, 'folder_list', 'folder_list_block');
$this->setBlock(TEMPLATE_content, 'publish_page', 'publish_page_block');
$this->setTab('myfiles');


if (is_numeric($this->arg[0])) {

    /* Modify existing file */

    $file = $fileHandler->getByID($this->arg[0]);
    if (!is_array($file))
        error(41);

    $this->setTitle('Modify File');
    $this->setVar('browse_row_block', '');

    $output = array('ACTION' => 'Modify',
        'FILE_ID' => $file['id'],
        'DESCRIPTION' => Scrub::text($file['description']),
        'MAX_FILE_SIZE' => Cfg::get('max_file_size'),
        'PARENT_ID' => $file['parent'],
    );
    $this->setVar($output);

    $folderTree = $folderHandler->getFolderTree();
	renderTreeSelect($this, $folderTree, $file['parent']);
	renderPublishingOptions($this);

}
elseif ($this->arg[0] == 'new' && is_numeric($this->arg[1])) {

    /* Add new file to system */
    if ($this->arg[1] == 0)
        $parent = array('id' => 0);
    elseif (!$parent = $folderHandler->getByID($this->arg[1]))
        error(13);

    $this->setTitle('Add File');
	$this->parse('browse_row_block', 'browse_row');

    $output = array('ACTION' => 'Add',
        'FILE_ID' => '',
        'DESCRIPTION' => '',
        'MAX_FILE_SIZE' => Cfg::get('max_file_size'),
        'PARENT_ID' => $parent['id'],
    );

    $this->setVar($output);

    $folderTree = $folderHandler->getFolderTree();
	renderTreeSelect($this, $folderTree, $parent['id']);
	renderPublishingOptions($this);

}
else
    error(2);


/**
 * Render the <select> element with the tree structure defined, or just a default "top level" if there is none
 *
 * @param SitePage $page
 * @param array $tree
 * @param int $selected
 */
function renderTreeSelect($page, $tree, $selected = 0) {
	if (is_array($tree)) {
		array_unshift($tree, array('id' => 0, 'name' => 'Top Level', 'depth' => -1));
		foreach ($tree as $folder) {
			$output = array(
				'FOLDER_ID' => $folder['id'],
				'FOLDER_NAME' => $folder['name'],
				'FOLDER_INDENT' => str_repeat('&nbsp;', ($folder['depth'] + 1) * 4),
				'FOLDER_SELECTED' => $folder['id'] == $selected ? 'selected="selected"' : '',
			);
			$page->setVar($output);
			$page->parse('folder_list_block', 'folder_list', true);
		}
	}
	else {
		$output = array(
			'FOLDER_ID' => 0,
			'FOLDER_NAME' => 'Top Level',
			'FOLDER_INDENT' => '',
			'FOLDER_SELECTED' => 'selected="selected"',
		);
		$page->setVar($output);
		$page->parse('folder_list_block', 'folder_list', false);
	}
}

/**
 * Render the UI options for publishing, if there are any
 *
 * @param SitePage $page
 */
function renderPublishingOptions($page) {
	/* Get a list of pages with the File List component in it
		 * and offer these as places to which you can publish.
		 */
	$componentDefinition = Component_DefinitionMapper::getInstance()->findByColumn('class', 'Component_Eportfolio_FileListController');

	$availablePages = \Eportfolio\PageMapper::getInstance()->findAvailablePages($_SESSION['id'], $componentDefinition->first());

	/* Manufacture the necessary info
	 */
	if (is_array($availablePages) && count($availablePages)) {
		$allPageData = array();

		foreach ($availablePages as $currentPage) {
			if (is_array($currentPage['componentList']) && count($currentPage['componentList'])) {
				foreach ($currentPage['componentList'] as $currentComponent) {
					/** @var Component_Instance $currentComponent */
					foreach ($currentComponent->getConfig() as $currentConfig) {
						/** @var Component_ConfigDefinition $currentConfig */
						if ($currentConfig->getKey() == 'componentname') {
							$componentName = $currentConfig->getValue();
						}
					}

					/* Each link only needs to know this
					 */
					$pageData = array(
						'pageId' => $currentPage['page']->getPageId(),
						'pageTitle' => $currentPage['page']->getPageTitle(),
						'pageOwnerId' => $currentPage['page']->getUid(),
						'componentInstanceId' => $currentComponent->getId(),
						'componentName' => $componentName,
					);

					$allPageData[] = $pageData;
				}
			}
		}

		if (is_array($allPageData) && count($allPageData)) {
			foreach($allPageData as $currentPageData) {
				$page->setVar(array(
					'PUBLISHED' => '',
					'PUBLISH_PAGE_PAGE_ID' => $currentPageData['pageId'],
					'PUBLISH_PAGE_PAGE_OWNER_ID' => $currentPageData['pageOwnerId'],
					'PUBLISH_PAGE_PAGE_NAME' => $currentPageData['pageTitle'],
					'PUBLISH_PAGE_COMPONENT_INSTANCE_ID' => $currentPageData['componentInstanceId'],
					'PUBLISH_PAGE_COMPONENT_NAME' => $currentPageData['componentName'],
				));

				$page->parse('publish_page_block', 'publish_page', true);
			}
		}
		else {
			$page->setVar('publish_page_block', '<p>You have no locations available for publishing.</p>');
		}
	}
	else {
		$page->setVar('publish_page_block', '<p>You have no locations available for publishing.</p>');
	}
}
