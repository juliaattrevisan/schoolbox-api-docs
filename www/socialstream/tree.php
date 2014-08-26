<?php
/**
<<<<<<< HEAD
 * AJAX folder browser used for quick breadcrumb based browsing of resources in SBFS
=======
>>>>>>> f9e83bb... Update homepage resource browser usage [SB-4102]
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

require_once dirname(__FILE__) . '/../../include/Bootstrap.php';

$page = new SitePage();

/* Get the stuff in this node
 */
try {

    if (isset($_REQUEST['nofiles'])) {
        $browser  = new \Schoolbox\Resource\Browser\Node($_REQUEST['relationID'], $_REQUEST['mimeFilter']);
        $renderer = new \Schoolbox\Resource\Browser\NodeRenderer($page, $browser->render());
    } else {
        $browser  = new \SocialStream\Browser\Node($_REQUEST['relationID'], $_REQUEST['mimeFilter']);
        $renderer = new \SocialStream\Browser\NodeRenderer($page, $browser->render());
    }

    /* Render the stuff in this node
     */
    $renderer->render();
} catch (\Schoolbox\Resource\Exception $e) {
    $page->setAjax(true);
    $page->error(6);
}
