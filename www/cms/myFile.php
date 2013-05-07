<?php
require_once '../library/my-file.php';
require_once '../library/my-folder.php';

$fileHandler = new MyFile;
$folderHandler = new MyFolder;


if (is_numeric($this->arg[0])) {

    /* Modify existing file */

    $file = $fileHandler->getByID($this->arg[0]);
    if (!is_array($file))
        error(41);

    $this->setFile(TEMPLATE_content, 'cms/myFile.html');
    $this->setBlock(TEMPLATE_content, 'browse_row', 'browse_row_block');
    $this->setBlock(TEMPLATE_content, 'folder_list', 'folder_list_block');
    $this->setTitle('Modify File');
    $this->setTab('myfiles');

    $this->setVar('browse_row_block', '');

    $output = array('ACTION' => 'Modify',
        'FILE_ID' => $file['id'],
        'DESCRIPTION' => Scrub::text($file['description']),
        'MAX_FILE_SIZE' => Cfg::get('max_file_size'),
        'PARENT_ID' => $file['parent'],
    );
    $this->setVar($output);

    $folderTree = $folderHandler->getFolderTree();
    if (is_array($folderTree)) {
        array_unshift($folderTree, array('id' => 0, 'name' => 'Top Level', 'depth' => -1));
        foreach ($folderTree as $folder) {
            $output = array('FOLDER_ID' => $folder['id'],
                'FOLDER_NAME' => $folder['name'],
                'FOLDER_INDENT' => str_repeat('&nbsp;', ($folder['depth'] + 1) * 4),
                'FOLDER_SELECTED' => $folder['id'] == $file['parent'] ? 'selected="selected"' : '',
            );
            $this->setVar($output);
            $this->parse('folder_list_block', 'folder_list', true);
        }
    }
}
elseif ($this->arg[0] == 'new' && is_numeric($this->arg[1])) {

    /* Add new file to system */
    if ($this->arg[1] == 0)
        $parent = array('id' => 0);
    elseif (!$parent = $folderHandler->getByID($this->arg[1]))
        error(13);

    $this->setFile(TEMPLATE_content, 'cms/myFile.html');
    $this->setBlock(TEMPLATE_content, 'folder_list', 'folder_list_block');
    $this->setTitle('Add File');
    $this->setTab('resources');

    $output = array('ACTION' => 'Add',
        'FILE_ID' => '',
        'DESCRIPTION' => '',
        'MAX_FILE_SIZE' => Cfg::get('max_file_size'),
        'PARENT_ID' => $parent['id'],
    );

    $this->setVar($output);

    $folderTree = $folderHandler->getFolderTree();
    if (is_array($folderTree)) {
        array_unshift($folderTree, array('id' => 0, 'name' => 'Top Level', 'depth' => -1));
        foreach ($folderTree as $folder) {
            $output = array('FOLDER_ID' => $folder['id'],
                'FOLDER_NAME' => $folder['name'],
                'FOLDER_INDENT' => str_repeat('&nbsp;', ($folder['depth'] + 1) * 4),
                'FOLDER_SELECTED' => $folder['id'] == $parent['id'] ? 'selected="selected"' : '',
            );
            $this->setVar($output);
            $this->parse('folder_list_block', 'folder_list', true);
        }
    }

	/* Get a list of pages with the File List component in it
	 * and offer these as places to which you can publish.
	 */
	$componentDefinition = Component_DefinitionMapper::getInstance()->findByColumn('class', 'Component_Eportfolio_FileListController');

	$availablePages = \Eportfolio\PageMapper::getInstance()->findAvailablePages($_SESSION['id'], $componentDefinition->current());

	/* Manufacture the necessary info
	 */
	if (is_array($availablePages) && count($availablePages)) {
		$allPageData = array();

		foreach ($availablePages as $currentPage) {
			if (is_array($currentPage['componentList']) && count($currentPage['componentList'])) {
				foreach ($currentPage['componentList'] as $currentComponent) {
					foreach ($currentComponent->config as $currentConfig) {
						if ($currentConfig->key == 'componentname') {
							$componentName = $currentConfig->value;
						}
					}

					/* Each link only needs to know this
					 */
					$pageData = array(
						'pageId' => $currentPage['page']->getPageId(),
						'pageTitle' => $currentPage['page']->getPageTitle(),
						'pageOwnerId' => $currentPage['page']->getUid(),
						'componentInstanceId' => $currentComponent->id,
						'componentName' => $componentName,
					);

					$allPageData[] = $pageData;
				}
			}
		}

		$this->setBlock(TEMPLATE_content, 'publish_page', 'publish_page_block');

		if (is_array($allPageData) && count($allPageData)) {
			foreach($allPageData as $currentPageData) {
				$this->setVar(array(
					'PUBLISHED' => '',
					'PUBLISH_PAGE_PAGE_ID' => $currentPageData['pageId'],
					'PUBLISH_PAGE_PAGE_OWNER_ID' => $currentPageData['pageOwnerId'],
					'PUBLISH_PAGE_PAGE_NAME' => $currentPageData['pageTitle'],
					'PUBLISH_PAGE_COMPONENT_INSTANCE_ID' => $currentPageData['componentInstanceId'],
					'PUBLISH_PAGE_COMPONENT_NAME' => $currentPageData['componentName'],
				));

				$this->parse('publish_page_block', 'publish_page', true);
			}
		}
		else {
			$this->setVar('publish_page_block', '');
		}
	}
}
else
    error(2);