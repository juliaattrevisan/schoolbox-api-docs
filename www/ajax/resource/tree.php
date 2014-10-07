<?php
use Schoolbox\Resource\Browser\Resources\Node;

/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

if (empty($this)) {
    require_once __DIR__ . '/../../../include/Bootstrap.php';
    // nope. go away
    SitePage::localRedir('/resources');
}

/** @var SitePage $this */
$this->setAjax();
header('Content-Type: text/html');

/* signature

    /ajax/resource/tree/[cms|epf|dav]?[resourceID=:root][mimeFilter=:filter][nofiles=1]

*/

if (count($this->arg) != 1) {
    $this->error(2);
}

$source     = $this->arg[0];
$resourceId = isset($_REQUEST['relationID']) ? $_REQUEST['relationID'] : 0;
$useFolders = true;            // allow folder selection
$mimeFilter = Node::ALL_FILES; // show all files by default

if (isset($_REQUEST['mimeFilter'])) {
    // might be *set* but blank, which means show no files
    if ($_REQUEST['mimeFilter']) {
        $mimeFilter = $_REQUEST['mimeFilter'];
        $useFolders = false;
    } else {
        $mimeFilter = Node::NO_FILES;
    }
}

try {
    $factory  = new \Schoolbox\Resource\Browser\NodeFactory();
    $browser  = $factory->build($source, $resourceId, $mimeFilter);
    $nodeData = $browser->render();

    // Render the stuff in this node
    $renderer = new \Schoolbox\Resource\Browser\NodeRenderer($this, $nodeData);
    $renderer->render($useFolders);

} catch (\Schoolbox\Resource\Exception $e) {
    $this->error(6);
} catch (OutOfBoundsException $e) {
    $this->error(2);
}
