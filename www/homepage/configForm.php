<?php
require_once dirname(__FILE__) . '/../../include/Bootstrap.php';

$page = new SitePage();
$groupHandler = new Group(HOMEPAGE_SERVICE);

if (is_numeric($_REQUEST['cid'])) {
	if ($groupHandler->getPermission($_REQUEST['cid']) < WRITE_ACCESS) {
		error(19);
	}

	$homepage = new Homepage($_REQUEST['cid']);

	/* AJAX request for drag and drop of components
	 */
	if ($_POST['cid'] && $_POST['type'] == 'components') {
		$columns = array($_POST['column1'], $_POST['column2'], $_POST['column3']);

		foreach ($columns as $key => $column) {
			// Explode the columns
			$key++;
			$components[$key] = explode(',', $column);

			// Update the db
			foreach ($components[$key] as $ckey => $component) {
				$ckey++;
				$homepage->updateComponentPosition($component, $key, $ckey);
			}
		}
	}
	/* AJAX request to delete component from homepage
	 */
	else if ($_POST['cid'] && is_numeric($_POST['delete'])) {
		$homepage->deleteComponent($_POST['delete']);
	}
	/* Add component to page and output render of component
	 */
	else if ($_POST['cid'] && is_numeric($_POST['add'])) {
		$homepageComponentLocationID = $homepage->addComponent($_POST['add']);

		//Output component HTML, we do this by loading sitepage and loading a specially designed harness to produce the homepage component on its own
		//require_once $_SERVER['DOCUMENT_ROOT'] . '/../include/SitePage.php';

		$page->loadContent('/ajax/homepageComponent');
		$page->template->pparse('finaloutput', TEMPLATE_content);
	}
	/* AJAX Save homepage colours
	 * strip hash
	 */
	else if ($_POST['cid'] && is_array($_POST['colors'])) {
		foreach ($_POST['colors'] as $key => $value) {
			$color[$key] = substr($value, 1);
		}

		$homepage->saveDisplayProperties($color);
	}
	/* Post config options from homepage
	 */
	else if ($_GET['cid'] && $_GET['type'] == 'config' && $_GET['component']) {
        $config = $_GET;

		unset($config['cid'], $config['type'], $config['component']);

		//all remaining options and serialised and sent to db
		$config = serialize($config);
		$homepage->updateComponentConfig($_GET['component'], $config);

		SitePage::localRedir("/homepage/" . $_GET['cid']);
	}
	else if ($_POST['cid'] && is_numeric($_POST['leftColWidth']) && is_numeric($_POST['rightColWidth'])) {
		$homepage->updateColumnWidths(Scrub::text($_POST['leftColWidth']), Scrub::text($_POST['rightColWidth']));
	}
}
else {
	error(2);
}
