<?php
/* Allow users to customise their homepages
 */
require_once(dirname(__FILE__) . '/../../include/Bootstrap.php');

$page = new SitePage();

$groupHandler = new Group();

if (is_numeric($_REQUEST['uid'])) {

    /* Check that you own the current ePortfolio being viewed
     */
    if ($_SESSION['id'] != $_REQUEST['uid'] && !$groupHandler->isSuperuser($_SESSION['id'])) {
        error(19);
    }

    $eportfolioHandler = new Eportfolio_Container($_REQUEST['uid']);

    /* ajax request for drag and drop of components
     * this is found in jquery.eportfoliocomponent.js
     */
    if ($_POST['uid'] && $_POST['type'] == 'components') {
        $columns = array($_POST['column1'], $_POST['column2'],$_POST['column3']);

        foreach ($columns as $key => $column) {
            /* Explode the columns */
            $key++;
            $components[$key] = explode(',', $column);

            /* Update the db */
            foreach ($components[$key] as $ckey => $component) {
                if ( !empty($component) ) {
                    $ckey++;

                    $eportfolioHandler->updateComponentPosition($component, $key, $ckey);
                }
            }
        }
    }
	/* AJAX request to delete component from homepage
	 */
	else if ($_POST['uid'] && is_numeric($_POST['delete'])) {
        $eportfolioHandler->deleteComponent($_POST['delete']);
    }
	/* Add component to page and output render of component
	 */
	else if ($_POST['uid'] && is_numeric($_POST['add'])) {
        $eportfolioHandler->addComponent($_POST['add']);

        //Output component HTML, we do this by loading sitepage and loading a specially designed harness to produce the homepage component on its own
        require_once $_SERVER['DOCUMENT_ROOT'] . '/../include/SitePage.php';
        $page = new SitePage;
        $page->loadContent('/ajax/eportfolioComponent');
        $page->template->pparse('finaloutput', TEMPLATE_content);
    }
	/* AJAX Save homepage colours
	 * strip hash
	 */
	else if ($_POST['uid'] && is_array($_POST['colors'])) {
        foreach ($_POST['colors'] as $key => $value) {
            $color[$key] = substr($value, 1);
        }

        $eportfolioHandler->saveDisplayProperties($color);
    }
	/* Post config options from homepage
	 */
	else if ($_GET['uid'] && $_GET['type'] == 'config' && $_GET['component']) {
        $config = $_GET;

		unset($config['uid'], $config['type'], $config['component']);

        /* All remaining options and serialised and sent to db
         */
        $config = serialize($config);

        $eportfolioHandler->updateComponentConfig($_GET['component'], $config);

		SitePage::localRedir('/eportfolio/' . $_GET['uid']);
    }
	else if ($_POST['uid'] && is_numeric($_POST['leftColWidth']) && is_numeric($_POST['rightColWidth'])) {
		$eportfolioHandler->updateColumnWidths(Scrub::text($_POST['leftColWidth']), Scrub::text($_POST['rightColWidth']));
	}
}
else {
    error(2);
}
