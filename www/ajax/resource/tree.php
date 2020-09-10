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
    $this->error(2);
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

/* NodeFactory leaks all manner of exceptions from the pieces in its constructor
 * There's a variety that could be thrown but storm can't detect them, apparently
 */
define('CONNECT_365_HTML', '<a data-gettoken href="/ajax/oauth/microsoft" class="button submit">Connect Office 365 account</a>');

try {
    $factory = new \Schoolbox\Resource\Browser\NodeFactory();
} catch (\Schoolbox\Resource\Exception|\DomainException|\InvalidArgumentException $e) {
    $this->error(6);
} catch (RuntimeException $e) {
    die(CONNECT_365_HTML);
}

if ($oAuthToken = $_SESSION['azureAccessToken']) {

    /* Try to connect to the Office365 filesystem
     */
    $conf = $this->getDi()->get('config');
    try {
        $tenant = new Tenant($conf->get('office365_tenant'), $conf->get('sharepoint_sitepath') ?: null);
        $browser = $factory->buildOAuth($source, $resourceId, $tenant, $oAuthToken, $mimeFilter);
    } catch (InvalidArgumentException|OutOfBoundsException $e) {

        /* Any errors results in you getting the button since you weren't previously connected
         */
        die(CONNECT_365_HTML);
    } catch (AuthenticationTimedOut $e) {
        $this->error(null, "Office 365 error: {$e->getMessage()}");
    }
} else {

    /* Otherwise try to load up a node from our resource tree
     */
    try {
        $browser = $factory->build($source, $resourceId, $mimeFilter);
    } catch (OutOfRangeException|UnexpectedValueException|OutOfBoundsException|Unauthorized $e) {
        $this->error(2);
    } catch (\Schoolbox\Mapper\Exception $e) {

        /* This node couldn't be found or was deleted
         * Send them back to the root node instead
         */
        $browser = $factory->build($source, 0, $mimeFilter);
    }
}

$nodeData = $browser->render();

// Render the stuff in this node
$renderer = new \Schoolbox\Resource\Browser\NodeRenderer($this, $nodeData);
$renderer->render($useFolder);
