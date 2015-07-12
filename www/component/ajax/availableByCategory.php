<?php
/**
 * Get a json-encoded list of textboxes in a given category
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
require_once('../../../include/Bootstrap.php');

/* Start a session
 */
$page = new SitePage();
$groupHandler = new Group();
$allElements = array();

/* Check permissions
 */
if (is_numeric($_GET['categoryId'])) {
    if ($groupHandler->getPermission($_GET['categoryId']) >= READ_ACCESS) {
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
                    $file    = $video->getFile();
                    $element = array(
                        'name'  => $file->getTitle(),
                        'value' => $file->getId(),
                    );
                    $allElements[] = $element;
                }
                break;
            case 'tileSource':
                // Get all components that are a part of this page.
                /** @var Component_Instance[] $components */
                $components = Component_InstanceMapper::getInstance()->findByContextAndClass(
                    'homepage',
                    $_GET['categoryId'],
                    'Schoolbox\\Tile\\Component\\HomepageTileController'
                );
                foreach ($components as $component) {
                    $config        = $component->getConfigArray();
                    $allElements[] = array(
                        'name'  => $config['componentname']['value'],
                        'value' => $component->getId(),
                    );
                }
                break;
            case 'textbox':
            default:
                $textboxMapper = new \Schoolbox\Textbox\Mapper();
                $allTextboxes = $textboxMapper->findByColumn('categoryID', $_GET['categoryId']);

                foreach ($allTextboxes as $currentTextbox) {
                    $element = array(
                        'name'  => $currentTextbox->getHeading(),
                        'value' => $currentTextbox->getId(),
                    );

                    $allElements[] = $element;
                }
                break;
        }
    }
}

echo json_encode($allElements);
