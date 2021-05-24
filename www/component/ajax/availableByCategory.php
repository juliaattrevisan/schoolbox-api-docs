<?php

/**
 * Get a json-encoded list of textboxes in a given category
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

use Schoolbox\Application\WebRequest\Kernel;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Tile\Layout\Mapper as TileLayoutMapper;

require_once __DIR__ . '/../../../conf/core/require.php';
$kernel = new Kernel();

/* Start a session
 */
/** @var SitePage $page */
$page = $kernel->get('sitePage');
$page->initSession();
$groupHandler = new Group();
$allElements  = [];

/* Check permissions
 */
if (is_numeric($_GET['categoryId'])) {
    if ($groupHandler->getPermission($_GET['categoryId']) >= UserAccess::ACCESS_READ) {
        switch ($_GET['type']) {
            case 'video':
                // All videos on this page
                $videoMapper = new \Schoolbox\Video\ResourceVideoMapper();
                $videos      = $videoMapper->findByCategory(
                    $_GET['categoryId'],
                    ['sort' => 'name', 'sortdir' => 'asc']
                );
                foreach ($videos as $video) {
                    /** @var \Schoolbox\Video\ResourceVideo $video */
                    // I know that this is a file from the resource browser
                    /** @var \Schoolbox\Resource\AbstractFile $file */
                    $file          = $video->getFile();
                    $element       = [
                        'name'  => $file->getTitle(),
                        'value' => $file->getId(),
                    ];
                    $allElements[] = $element;
                }
                break;

            case 'layoutId':
                // Get all layouts that are a part of this page.
                /** @var Component_Instance[]|\Schoolbox\GenericCollection $components */
                $layouts = TileLayoutMapper::getInstance()->findForFolder($_GET['categoryId']);
                foreach ($layouts as $layout) {
                    $allElements[] = [
                        'name'  => $layout->getHeading(),
                        'value' => $layout->getId(),
                    ];
                }
                break;

            case 'textbox':
            default:
                $textboxMapper = new \Schoolbox\Resource\Textbox\Mapper();
                $allTextboxes  = $textboxMapper->findByColumn('categoryID', $_GET['categoryId']);

                foreach ($allTextboxes as $currentTextbox) {
                    $element = [
                        'name'  => $currentTextbox->getHeading(),
                        'value' => $currentTextbox->getId(),
                    ];

                    $allElements[] = $element;
                }
                break;
        }
    }
}

echo json_encode($allElements);
