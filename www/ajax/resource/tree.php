<?php

use Alaress\Authorizer\Failure\AuthenticationTimedOut;
use Alaress\Authorizer\Failure\Unauthorized;
use Schoolbox\Resource\Browser\Office365\Tenant;
use Schoolbox\Resource\Folder\IFolder;

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

if (empty($this)) {
    require_once __DIR__ . '/../../../conf/core/require.php';
    $kernel = new \Schoolbox\Application\WebRequest\Kernel();
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
    $this->error(\Schoolbox\Error\Mapper::ERROR_INFORMATION_MISSING);
}

$source     = $this->arg[0];
// relationID is not numeric for office365
$resourceId = $_REQUEST['relationID'] ?? IFolder::ROOT_ID;

/* For rendering "Use this folder" ability
 */
$useFolder = isset($_REQUEST['useFolder']);

if (!isset($_REQUEST['mimeFilter'])) {
    $mimeFilter = null;
} elseif ('' === $_REQUEST['mimeFilter']) {
    $mimeFilter = false;
} else {
    $mimeFilter = $_REQUEST['mimeFilter'];
}

try {
    $factory  = new \Schoolbox\Resource\Browser\NodeFactory();

    if ($oAuthToken = $_SESSION['azureAccessToken']) {
        $conf       = $this->getDi()->get('config');
        $tenant     = new Tenant($conf->get('office365_tenant'), $conf->get('sharepoint_sitepath') ?: null);
        $browser    = $factory->buildOAuth($source, $resourceId, $tenant, $oAuthToken, $mimeFilter);
    } else {
        try {
            $browser = $factory->build($source, $resourceId, $mimeFilter);
        } catch (\Schoolbox\Mapper\Exception $e) {
            /* Our mappers encountered deleted node; go back to root node
             */
            $browser = $factory->build($source, IFolder::ROOT_ID, $mimeFilter);
        }
    }

    $nodeData = $browser->render();

    // Render the stuff in this node
    $renderer = new \Schoolbox\Resource\Browser\NodeRenderer($this, $nodeData);
    $renderer->render($useFolder);
} catch (Unauthorized $e) {
    $this->error(\Schoolbox\Error\Mapper::ERROR_INFORMATION_MISSING);
} catch (\Schoolbox\Resource\Exception $e) {
    $this->error(\Schoolbox\Error\Mapper::ERROR_PERM_CATEGORY_WRITE);
} catch (OutOfBoundsException $e) {
    $this->error(\Schoolbox\Error\Mapper::ERROR_INFORMATION_MISSING);
} catch (AuthenticationTimedOut $e) {
    $this->error(null, "Office 365 error: {$e->getMessage()}");
} catch (RuntimeException $e) {
    die('<a data-gettoken href="/ajax/oauth/microsoft" class="button submit">Connect Office 365 account</a>');
}
