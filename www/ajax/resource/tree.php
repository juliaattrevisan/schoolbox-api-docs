<?php
use Alaress\Authorizer\Failure\Unauthorized;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\Config\Loader;
use Schoolbox\Resource\Browser\Office365\Tenant;
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

  /ajax/resource/tree/[cms|epf|dav|office365]?[relationID=:root][mimeFilter=:filter][nofiles=1]

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

    if ($oAuthToken = $_SESSION['azureAccessToken']) {
        $conf       = new Loader();
        $tenant     = new Tenant($conf->get('office365_tenant'), $conf->get('sharepoint_sitepath') ?: null);
        $browser    = $factory->buildOAuth($source, $resourceId, $tenant, $oAuthToken, $mimeFilter);

    } else {
        $browser = $factory->build($source, $resourceId, $mimeFilter);

    }

    $nodeData = $browser->render();

    // Render the stuff in this node
    $renderer = new \Schoolbox\Resource\Browser\NodeRenderer($this, $nodeData);
    $renderer->render($useFolders);

} catch (Unauthorized $e) {
    $this->error(2);
} catch (\Schoolbox\Resource\Exception $e) {
    $this->error(6);
} catch (OutOfBoundsException $e) {
    $this->error(2);
} catch (RuntimeException $e) {
    die('<span>Click <a data-gettoken href="/ajax/oauth/microsoft">here</a> to connect your Office 365 account.</span>');
}
