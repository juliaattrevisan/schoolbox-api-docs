<?php

use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Resource\LinkMapper;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Schoolbox\Breadcrumbs;

$groupHandler = new Group();

if (is_numeric($this->arg[0])) {
    try {
        /** @var \Schoolbox\Resource\Link $link */
        $link = LinkMapper::getInstance()->findById($this->arg[0]);
        /** @var \Schoolbox\Resource\Folder\Model $folder */
        $folder = FolderMapper::getInstance()->findById($link->getFolder()->getId());

        /* Check permissions to submit */
        if ($groupHandler->getPermission($link->getFolder()->getId()) < UserAccess::ACCESS_WRITE) {
            error(6);
        }
    } catch (Exception $e) {
        error(41);
    }

    $this->setFile(TEMPLATE_content, 'cms/modify-link.html');

    $this->setTitle('Modify Link');
    $this->setTab('resources');

    $output = array(
        'ACTION'           => 'Modify',
        'LINK_ID'          => Scrub::text($link->getId()),
        'URL'              => Scrub::text($link->getUrl()),
        'DESCRIPTION'      => Scrub::text($link->getDescription()),
        'COMMENT'          => Scrub::text($link->getComment()),
        'CATEGORY_NAME'    => Scrub::text($folder->getName()),
        'CATEGORY_ID'      => Scrub::text($link->getFolder()->getId()),
        'VISIBLE_SELECTED' => $link->getHidden() > 0 ? '' : 'checked',
        'BLANK_SELECTED'   => $link->getTarget() == '_blank' ? 'selected="selected"' : '',
    );

    $this->setVar($output);

    /* Render out breadcrumbs
     */
    $trail = new Breadcrumbs();
    $trail->renderIntoSitePage($this, 'BREADCRUMB', [
        [
            'text' => $folder->getName(),
            'href' => "/homepage/{$folder->getId()}",
            'class' => '',
        ],
        [
            'text' => 'Modify Link',
            'href' => '',
            'class' => 'active',
        ]
    ]);
} elseif ($this->arg[0] == 'new' && is_numeric($this->arg[1])) {
    try {
        $folder = FolderMapper::getInstance()->findById($this->arg[1]);

        /* Check permissions to submit */
        if ($groupHandler->getPermission($folder->getId()) < UserAccess::ACCESS_WRITE) {
            error(6);
        }
    } catch (Exception $e) {
        error(13);
    }

    /* Add new link to system */
    $this->setFile(TEMPLATE_content, 'cms/modify-link.html');
    $this->setTitle('Add Link');
    $this->setTab('resources');
    $this->setBlock(TEMPLATE_content, 'delete_button', 'delete_button_block');
    $this->setVar('delete_button_block', '');

    $output = array(
        'ACTION'           => 'Add',
        'LINK_ID'          => '',
        'URL'              => '',
        'DESCRIPTION'      => '',
        'COMMENT'          => '',
        'VISIBLE_SELECTED' => 'checked',
        'BLANK_SELECTED'   => '',
        'CATEGORY_NAME'    => Scrub::text($folder->getName()),
        'CATEGORY_ID'      => Scrub::text($folder->getId()),
    );
    $this->setVar($output);

    /* Render out breadcrumbs
     */
    $trail = new Breadcrumbs();
    $trail->renderIntoSitePage($this, 'BREADCRUMB', [
        [
            'text' => $folder->getName(),
            'href' => "/homepage/{$folder->getId()}",
            'class' => '',
        ],
        [
            'text' => 'Add Link',
            'href' => '',
            'class' => 'active',
        ]
    ]);

    // Multi-folder upload has not been supported since the new resource browser debuted
//}
//elseif ($this->arg[0] == 'multicat') {
//    /* Upload a link to multiple categories */
//
//    $this->setFile(TEMPLATE_content, 'cms/add-link-multi-cat.html');
//    $this->setBlock(TEMPLATE_content, 'category_list', 'category_list_block');
//
//    $this->setTitle('Upload a Link to Multiple Locations');
//    $this->setTab('resources');
//
//    $groupHandler = new Group;
//    $categoryList = $groupHandler->involvedInCategoryList($_SESSION['id'], true, UserAccess::ACCESS_WRITE);
//
//    if (is_array($categoryList) && count($categoryList)) {
//        foreach ($categoryList as $category) {
//            $name = $category['name'];
//
//            /* Optionally append the categoryCode if one exists
//             */
//            if (strlen(trim($category['code']))) {
//                $name .= ' (' . $category['code'] . ')';
//            }
//
//            $this->setVar(
//                array(
//                    'CATEGORY_NAME' => Scrub::text($name),
//                    'CATEGORY_ID'   => Scrub::text($category['id']),
//                )
//            );
//
//            $this->parse('category_list_block', 'category_list', true);
//        }
//    } else {
//        error(154);
//    }
} else {
    error(2);
}
