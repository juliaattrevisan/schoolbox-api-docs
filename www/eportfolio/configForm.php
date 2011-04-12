<?php

/* * **********************************************************
 *                                                           *
 *         Copyright (c) 2002-2003 Lektec Consulting         *
 *                                                           *
 * ********************************************************** */

//allow users to customise their homepages

require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/eportfolio.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/../include/sitePage.php';

$page = new SitePage;

if (is_numeric($_REQUEST['uid'])) {

    /* Check that you own the current ePortfolio being viewed
     */
    if ($_SESSION['id'] != $_REQUEST['uid']) {
        error(19);
    }

    $eportfolioHandler = new ePortfolio($_REQUEST['uid']);

    /* AJAX request for drag and drop of components
     * This is found in jquery.eportfoliocomponent.js
     */
    if ($_POST['uid'] && $_POST['type'] == 'components') {
        $columns = array($_POST['column1'], $_POST['column2']);

        foreach ($columns as $key => $column) {
            /* Explode the columns */
            $key++;
            $components[$key] = explode(',', $column);

            /* Update the db */
            foreach ($components[$key] as $ckey => $component) {
                $ckey++;
                $eportfolioHandler->updateComponentPosition($component, $key, $ckey);
            }
        }
    } elseif ($_POST['uid'] && is_numeric($_POST['delete'])) {
        //AJAX request to delete component from homepage
        $eportfolioHandler->deleteComponent($_POST['delete']);
    } else if ($_POST['uid'] && is_numeric($_POST['add'])) {
        //Add component to page and output render of component
        $eportfolioHandler->addComponent($_POST['add']);

        //Output component HTML, we do this by loading sitepage and loading a specially designed harness to produce the homepage component on its own
        require_once $_SERVER['DOCUMENT_ROOT'] . '/../include/sitePage.php';
        $page = new SitePage;
        $page->loadContent('/ajax/eportfolioComponent');
        $page->template->pparse('finaloutput', TEMPLATE_content);
    } elseif ($_POST['uid'] && is_array($_POST['colors'])) {
        /* AJAX Save homepage colours */
        //strip hash
        foreach ($_POST['colors'] as $key => $value) {
            $color[$key] = substr($value, 1);
        }

        $eportfolioHandler->saveDisplayProperties($color);
    } elseif ($_POST['uid'] && is_numeric($_POST['template'])) {
        /* AJAX Save homepage template */
        print $_POST['template'];
        $eportfolioHandler->savePageTemplate($_POST['template']);
    } elseif ($_GET['uid'] && $_GET['type'] == 'config' && $_GET['component']) {
        //Post config options from homepage
        $config = $_GET;
        unset($config['uid'], $config['type'], $config['component']);
        //all remaining options and serialised and sent to db
        $config = serialize($config);
        $eportfolioHandler->updateComponentConfig($_GET['component'], $config);
        localRedir("/eportfolio/" . $_GET['uid']);
    }
} else {
    error(2);
}
?>
