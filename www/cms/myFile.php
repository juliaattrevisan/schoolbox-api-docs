<?php

use Eportfolio\PageMapper;
use Schoolbox\Breadcrumbs;
use Schoolbox\MyFileMapper;
use Schoolbox\Resource\Attachment\GenericZone;

$this->setFile(TEMPLATE_content, 'cms/myFile.html');
$this->setBlock(TEMPLATE_content, 'browse_row', 'browse_row_block');
$this->setBlock(TEMPLATE_content, 'folder_list', 'folder_list_block');
$this->setBlock(TEMPLATE_content, 'publish_page', 'publish_page_block');
$this->setTab('myfiles');

/**
 * @uri /cms/myFile/new/:userId[/:myFolderId][/:publishComponent]
 * @uri /cms/myFile/:myFileId/:userId
 */

$zone = new GenericZone('cms', MyFileMapper::getInstance());
$zone->setAttachmentsLabel('')
     ->setAttachPrompt('Post file')
     ->setMimeFilter('*/*')
     ->setMulti(false)
     ->setThumbnailSize(64)
     ->setModalTitle('Select file');

// userId is always arg[1]
$userId = $this->arg[1] ?: $_SESSION['id'];

$userMapper = new \Schoolbox\UserMapper();
$user       = $userMapper->findById($userId);

// PERMISSION: must be either session user, or staff
$groupHandler = new Group();
if (!($groupHandler->isStaff() || $userId == $_SESSION['id'])) {
    error(19);
}

// instantiate handlers
$fileHandler = new MyFile($userId);
$folderHandler = new MyFolder($userId);

$this->setVar('USER_ID', $userId);

// create a new file
if ($this->arg[0] == 'new') {
    $myFolderId = $this->arg[2] ?: 0;
    $publishComponent = $this->arg[3] ?: false;

    if ($myFolderId == 0) {
        $parent = array('id' => 0);
    } elseif (!$parent = $folderHandler->getByID($myFolderId)) {
        error(13);
    }

    $this->setTitle('Add File');
    $this->parse('browse_row_block', 'browse_row');

    $output = array(
        'ACTION' => 'Add',
        'FILE_ID' => '',
        'DESCRIPTION' => '',
        'MAX_FILE_SIZE' => Cfg::get('max_file_size'),
        'PARENT_ID' => $parent['id'],
        "COMPONENT_INSTANCE_ID" => $publishComponent,
    );

    $this->setVar($output);

    $zone->renderIntoSitePage($this, 'ATTACH_ZONE');

    $folderTree = $folderHandler->getFolderTree();
    renderTreeSelect($this, $folderTree, $parent['id']);
    renderPublishingOptions($this, $userId, true, $publishComponent);

    /* Render out breadcrumbs
     */
    $trail = new Breadcrumbs();
    $trail->renderIntoSitePage($this, 'BREADCRUMB', [
        [
            'text' => $user->formatName()."'s Files",
            'href' => "/cms/myFile/{$user->getId()}",
            'class' => '',
        ],
        [
            'text' => 'Post File',
            'href' => '',
            'class' => 'active',
        ]
    ]);
} elseif (is_numeric($this->arg[0])) {
    // modify an existing file
    $myFileId = $this->arg[0];

    $file = $fileHandler->getByID($myFileId);
    if (!is_array($file)) {
        error(41);
    }

    $zone->attachFiles(array($myFileId));

    $ft = new FileType('', $file['mime_type']);

    $this->setTitle('Modify File');
    $this->setVar('browse_row_block', '');

    $output = array(
        'ACTION' => 'Modify',
        'FILE_ID' => $file['id'],
        'DESCRIPTION' => Scrub::text($file['description']),
        'MAX_FILE_SIZE' => Cfg::get('max_file_size'),
        'PARENT_ID' => $file['parent'],
    );
    $this->setVar($output);

    $zone->renderIntoSitePage($this, 'ATTACH_ZONE');

    $folderTree = $folderHandler->getFolderTree();
    renderTreeSelect($this, $folderTree, $file['parent']);
    renderPublishingOptions($this, $userId, true);

    /* Render out breadcrumbs
     */
    $trail = new Breadcrumbs();
    $trail->renderIntoSitePage($this, 'BREADCRUMB', [
        [
            'text' => $user->formatName()."'s Files",
            'href' => "/cms/myFile/{$user->getId()}",
            'class' => '',
        ],
        [
            'text' => 'Modify File',
            'href' => '',
            'class' => 'active',
        ]
    ]);

} else {
    // unknown case
    error(2);
}


/**
 * Render the <select> element with the tree structure defined, or just a default "top level" if there is none
 *
 * @param SitePage $page
 * @param array $tree
 * @param int $selected
 */
function renderTreeSelect($page, $tree, $selected = 0)
{
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
    } else {
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
 * @param int $userId
 * @param bool $includeImageControllers
 * @param bool|int $publishComponent
 */
function renderPublishingOptions($page, $userId, $includeImageControllers, $publishComponent = false)
{

    /**
    * @var PageMapper $eMap
    * @var Component_DefinitionMapper $cMap
    * @var Component_InstanceMapper $iMap
    */
    $eMap = PageMapper::getInstance();
    $cMap = Component_DefinitionMapper::getInstance();
    $iMap = Component_InstanceMapper::getInstance();

    // if $publishComponent is set, use it to find the page associated with it
    if ($publishComponent) {
        try {
            $component = $iMap->findById($publishComponent);
            $targetPageId = $component->getIdentifier();
        } catch (Schoolbox\Mapper\Exception $e) {
            $targetPageId = false;
        }
    } else {
        $targetPageId = false;
    }

    /* Get a list of pages with the File List component in it
	 * and offer these as places to which you can publish.
	 */
    $availablePages = $eMap->findPublishablePages($userId);

    /* Manufacture the necessary info
	 */
    if (is_array($availablePages) && count($availablePages)) {
        $allPageData = array();

        foreach ($availablePages as $currentPage) {
            $pageData = array(
                'pageId' => $currentPage['page']->getPageId(),
                'pageTitle' => $currentPage['page']->getPageTitle(),
                'pageOwnerId' => $currentPage['page']->getUid(),
            );
            $allPageData[$pageData['pageId']] = $pageData;
        }

        if (is_array($allPageData) && count($allPageData)) {
            foreach ($allPageData as $currentPageData) {
                $selected = $currentPageData['pageId'] == $targetPageId;
                $page->setVar(array(
                    'PUBLISHED' => $selected ? 'checked="checked"' : '',
                    'PUBLISH_PAGE_PAGE_ID' => $currentPageData['pageId'],
                    'PUBLISH_PAGE_PAGE_OWNER_ID' => $currentPageData['pageOwnerId'],
                    'PUBLISH_PAGE_PAGE_NAME' => $currentPageData['pageTitle'],
                    'PUBLISH_PAGE_COMPONENT_INSTANCE_ID' => $currentPageData['componentInstanceId'],
                    'PUBLISH_PAGE_COMPONENT_NAME' => $currentPageData['componentName'],
                ));

                $page->parse('publish_page_block', 'publish_page', true);
            }
        } else {
            $page->setVar('publish_page_block', '<p>You have no locations available for publishing.</p>');
        }
    } else {
        $page->setVar('publish_page_block', '<p>You have no locations available for publishing.</p>');
    }
}
