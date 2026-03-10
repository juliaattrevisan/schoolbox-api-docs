<?php

/**
 * @copyright Copyright 2022, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

use Eportfolio\PageMapper;
use Schoolbox\Breadcrumbs;
use Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\GenericCollection;
use Schoolbox\Mapper\Exception\Deleted;
use Schoolbox\Mapper\Exception\NotFound;
use Schoolbox\Resource\Attachment\GenericZone;
use Schoolbox\Resource\My\File\Mapper as MyFileMapper;
use Schoolbox\Resource\My\Folder\Mapper as MyFolderMapper;
use Schoolbox\Resource\My\Folder\Model as MyFolderModel;

/** @var SitePage $this */
$this->setFile(TEMPLATE_content, 'cms/myFile.html');
$this->setBlock(TEMPLATE_content, 'browse_row', 'browse_row_block');
$this->setBlock(TEMPLATE_content, 'folder_list', 'folder_list_block');
$this->setBlock(TEMPLATE_content, 'publish_page', 'publish_page_block');
$this->setTab('myfiles');

/**
 * @uri /cms/myFile/new/:userId[/:myFolderId][/:publishComponent]
 * @uri /cms/myFile/:myFileId/:userId
 */

$fileMapper   = MyFileMapper::getInstance();
$folderMapper = MyFolderMapper::getInstance();
$zone         = new GenericZone('cms', $fileMapper)
    ->setAttachmentsLabel('')
     ->setAttachPrompt('Post file')
     ->setMimeFilter('*/*')
     ->setMulti(false)
     ->setThumbnailSize(64)
     ->setModalTitle('Select file');

// userId is always arg[1]
$userMapper = \Schoolbox\UserMapper::getInstance();
/** @var \Schoolbox\User $sessionUser */
$sessionUser = $_SESSION['user'];
if (is_numeric($this->arg[1] ?? false)) {
    try {
        $fileOwner = $userMapper->findById($this->arg[1]);
    } catch (\Schoolbox\Mapper\Exception $e) {
        error(\Schoolbox\Error\Mapper::ERROR_PAGE_NOT_FOUND);
    }
} else {
    $fileOwner = $sessionUser;
}

// PERMISSION: must be either session user, or staff
/** @var \Schoolbox\User $fileOwner */
if (!($sessionUser->getRole()->isStaff() || $fileOwner->getId() === $sessionUser->getId())) {
    error(\Schoolbox\Error\Mapper::ERROR_PERM_PAGE_VIEW);
}

// create a new file
if ($this->arg[0] == 'new') {
    $myFolderId = $this->arg[2] ?? 0;
    $publishComponent = $this->arg[3] ?? false;

    $parentFolder = null;
    $parentId = null;
    if ($myFolderId) {
        try {
            $folder = $folderMapper->findOneByColumnList([
                'id'    => $myFolderId,
                'owner' => $fileOwner->getId(),
            ]);
            $parentId = $folder->getId();
        } catch (NotFound | Deleted $e) {
            $this->error(ErrorMapper::ERROR_INFORMATION_MISSING);
        }
    }

    $this->setTitle('Add File');
    $this->parse('browse_row_block', 'browse_row');

    $output = array(
        'ACTION' => 'Add',
        'FILE_ID' => '',
        'DESCRIPTION' => '',
        'MAX_FILE_SIZE' => Config::get('max_file_size'),
        'PARENT_ID' => $parentId ?? 0,
        "COMPONENT_INSTANCE_ID" => $publishComponent,
        "USER_ID" => $fileOwner->getId(),
        'RETURN_URL' => $_GET['returnUrl'] ?? ''
    );

    $this->setVar($output);

    $zone->renderIntoSitePage($this, 'ATTACH_ZONE');

    $folderTree = $folderMapper->loadDescendants(
        $folderMapper->findByColumnList([
            'owner'  => $fileOwner->getId(),
            'parent' => null,
        ]),
    );
    $this->renderTreeSelect(
        'folder_list',
        'FOLDER_',
        $folderTree,
        (int)($parentId ?? 0),
        fn (MyFolderModel $f) => $f->getId(),
        fn (MyFolderModel $f) => $f->getName(),
        fn (MyFolderModel $f) => $f->getChildren(),
    );
//    renderTreeSelect($this, $folderTree, $parentId ?? 0);
    renderPublishingOptions($this, null, $fileOwner->getId(), true, $publishComponent);

    /* Render out breadcrumbs
     */
    $trail = new Breadcrumbs();
    $trail->renderIntoSitePage($this, 'BREADCRUMB', [
        [
            'text' => $fileOwner->formatName() . "'s Files",
            'href' => "/my-files",
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

    try {
        $file = $fileMapper->findOneByColumnList([
            'id'    => $myFileId,
            'owner' => $fileOwner->getId(),
        ]);
    } catch (NotFound | Deleted $e) {
        $this->error(\Schoolbox\Error\Mapper::ERROR_FILE_NOT_FOUND);
    }

    $zone->attachFiles(array($myFileId));

    $this->setTitle('Modify File');
    $this->setVar('browse_row_block', '');

    $output = array(
        'ACTION' => 'Modify',
        'FILE_ID' => $file->getId(),
        'DESCRIPTION' => Scrub::text($file->getTitle()),
        'MAX_FILE_SIZE' => Config::get('max_file_size'),
        'PARENT_ID' => $file->getFolder()->getId(),
        'RETURN_URL' => $_GET['returnUrl'] ? \Schoolbox\Learning\Assessment\Redirect::decode($_GET['returnUrl']) : ''
    );
    $this->setVar($output);

    $zone->renderIntoSitePage($this, 'ATTACH_ZONE');

    $folderTree = $folderMapper->loadDescendants(
        $folderMapper->findByColumnList([
            'owner'  => $fileOwner->getId(),
            'parent' => null,
        ]),
    );
    $this->renderTreeSelect(
        'folder_list',
        'FOLDER_',
        $folderTree,
        (int)($file->getFolder()->getId()),
        fn (MyFolderModel $f) => $f->getId(),
        fn (MyFolderModel $f) => $f->getName(),
        fn (MyFolderModel $f) => $f->getChildren(),
    );
//    renderTreeSelect($this, $folderTree, $file->getFolder()->getId());
    renderPublishingOptions($this, $file->getId(), $fileOwner->getId(), true);

    /* Render out breadcrumbs
     */
    $trail = new Breadcrumbs();
    $trail->renderIntoSitePage($this, 'BREADCRUMB', [
        [
            'text' => $fileOwner->formatName() . "'s Files",
            'href' => "/my-files",
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
    $this->error(\Schoolbox\Error\Mapper::ERROR_INFORMATION_MISSING);
}


///**
// * Render the <select> element with the tree structure defined,
// * or just a default "top level" if there is none.
// *
// * @param SitePage                         $page       The SitePage object to render into
// * @param GenericCollection<MyFolderModel> $tree       A list of folders to display, each of which may have its own
// *                                                     children (which are rendered recursively)
// * @param int                              $selectedId The currently selected folder
// * @param int                              $depth      The current tree depth: used to indent child folders
// * @return void
// */
//function renderTreeSelect(SitePage $page, GenericCollection $tree, int $selectedId = 0, int $depth = 0): void
//{
//    if ($depth === 0) {
//        $output = [
//            'FOLDER_ID'       => 0,
//            'FOLDER_NAME'     => 'Top Level',
//            'FOLDER_INDENT'   => '',
//            'FOLDER_SELECTED' => $selectedId === 0 ? 'selected="selected"' : '',
//        ];
//        $page->setVar($output);
//        $page->parse('folder_list_block', 'folder_list', true);
//    }
//    foreach ($tree as $folder) {
//        /** @var MyFolderModel $folder */
//        $output = [
//            'FOLDER_ID'       => $folder->getId(),
//            'FOLDER_NAME'     => Scrub::text($folder->getName()),
//            'FOLDER_INDENT'   => str_repeat('&nbsp;', ($depth + 1) * 4),
//            'FOLDER_SELECTED' => $folder->getId() === $selectedId ? 'selected="selected"' : '',
//        ];
//        $page->setVar($output);
//        $page->parse('folder_list_block', 'folder_list', true);
//        if ($folder->getChildren()->count() > 0) {
//            renderTreeSelect($page, $folder->getChildren(), $selectedId, $depth + 1);
//        }
//    }
//}

/**
 * Render the UI options for publishing, if there are any
 *
 * @param SitePage $page
 * @param int|null $fileId
 * @param int $userId
 * @param bool $includeImageControllers
 * @param bool|int $publishComponent
 */
function renderPublishingOptions($page, $fileId, $userId, $includeImageControllers, $publishComponent = false)
{
    $eMap = PageMapper::getInstance();
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

    $publishedPageIds = [];
    if ($fileId) {
        $fileListMapper = \Component\Eportfolio\FileListMapper::getInstance();
        $publishedPageIds = $fileListMapper->findByColumn('file_id', $fileId)->getPropertyValues('pageId');
    }

    /* Manufacture the necessary info
     */
    if (is_array($availablePages) && count($availablePages)) {
        foreach ($availablePages as $currentPage) {
            /** @var Eportfolio\Page $ePage */
            $ePage    = $currentPage['page'];
            $selected = $ePage->getPageId() === $targetPageId || in_array($ePage->getPageId(), $publishedPageIds);
            $page->setVar(
                [
                    'PUBLISHED'                  => $selected ? 'checked="checked"' : '',
                    'PUBLISH_PAGE_PAGE_ID'       => $ePage->getPageId(),
                    'PUBLISH_PAGE_PAGE_OWNER_ID' => $ePage->getOwner()->getId(),
                    'PUBLISH_PAGE_PAGE_NAME'     => $ePage->getPageTitle(),
                ]
            );

            $page->parse('publish_page_block', 'publish_page', true);
        }
    } else {
        $page->setVar('publish_page_block', '<p>You have no locations available for publishing.</p>');
    }
}
