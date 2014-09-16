<?php
/**
 * AJAX folder browser used for quick breadcrumb based browsing of resources in SBFS
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
namespace SocialStream\Browser;

require_once dirname(__FILE__) . '/../../include/Bootstrap.php';

$page = new \SitePage();

/* Get the stuff in this node
 */
try {
    $browser = new Node($_REQUEST['relationID']);
    $nodeData = $browser->render();

    /* Render the stuff in this node
     */
    $renderer = new NodeRenderer($page, $nodeData);
    $renderer->render();
} catch (\Schoolbox\Resource\Exception $e) {
    $page->setAjax(true);
    $page->error(6);
}


