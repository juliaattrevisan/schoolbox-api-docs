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
$textboxHandler = new TextBox();
$allElements = array();

/* Check permissions
 */
if (is_numeric($_GET['categoryId'])) {
    if ($groupHandler->getPermission($_GET['categoryId']) >= READ_ACCESS) {
        switch ($_GET['type']) {
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
                $allTextboxes = $textboxHandler->getAllByCategory($_GET['categoryId']);
                if (is_array($allTextboxes) && count($allTextboxes)) {
                    foreach ($allTextboxes as $currentTextbox) {
                        $element = array(
                            'name' => Scrub::text($currentTextbox['heading']),
                            'value' => $currentTextbox['textBoxID'],
                        );

                        $allElements[] = $element;
                    }
                }
                break;
        }
    }
}
echo json_encode($allElements);
