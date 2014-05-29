<?php

use Schoolbox\Permission\Access\RoleAccessMapper;
use Schoolbox\Resource\CategoryCodeMapper;
use Schoolbox\Resource\CategoryMapper;
use Schoolbox\Resource\HomepageConfigMapper;
use Schoolbox\Resource\HomepageTemplate;
use Schoolbox\Resource\HomepageTemplateMapper;

require_once(dirname(__FILE__) . '/../../include/Bootstrap.php');

$page = new SitePage();
$categoryHandler = new Category();
$groupHandler = new Group();

if ($_POST['name']) {
	$category = array(
		'name' => $_POST['name'],
		'parent' => $_POST['parent'],
		'template' => $_POST['template'],
		'code' => $_POST['code'],
		'group' => $_POST['group'],
		'recursive' => $_POST['recursive'] ? '1' : '0',
	);

	$codeCollection = new \Schoolbox\Collection();
	foreach(explode(',', $_POST['code']) as $code) {
		$trimmedCode = trim($code);

		if (strlen($trimmedCode)) {
			$codeModel = new \Schoolbox\Resource\CategoryCode();
			$codeModel->setCode($trimmedCode);
			$codeCollection->add($codeModel);
		}
	}

	/** @var \Schoolbox\Resource\Category $model */
	$model = CategoryMapper::getInstance()->createModel()
		->setName($_POST['name'])
		->setParent(CategoryMapper::getInstance()->createModel()
			->setId($_POST['parent'])
		)
		->setCodes($codeCollection);

	if (is_numeric($_POST['categoryID'])) {
		if ($groupHandler->getPermission($_POST['categoryID']) < WRITE_ACCESS) {
			error(6);
		}

		$model->setId($_POST['categoryID']);

		$category['id'] = $_POST['categoryID'];
		$model->setId($_POST['categoryID']);
	}
	elseif ($groupHandler->getPermission($_POST['parent']) < WRITE_ACCESS) {
		error(6);
	}

	$affectedIds = CategoryMapper::getInstance()->save($model);

	if($affectedIds === false) {
		// something went wrong
		error(2);
	}

	$model->setId($affectedIds[0]);

	// if rolepermission collection is defined, reapply permissions (possibly recursively)
	$rolePermissions = RoleAccessMapper::getInstance()->savePermissions($model, $_POST['group'], $_POST['recursive']);

	// if homepage is to be removed
	if($_POST['template'] == HomepageTemplate::HOMEPAGE_REMOVE) {
		HomepageConfigMapper::getInstance()->resetToTemplate($model->getHomepage());
	}
	// if template defined
	elseif($_POST['template'] > 0) {
		$homepageConfig = HomepageConfigMapper::getInstance()
			->createModel()
			->setTemplate(HomepageTemplateMapper::getInstance()->findById($_POST['template']))
			->setCategory($model);

		/* Save homepage config
		 */
		HomepageConfigMapper::getInstance()->save($homepageConfig);
		HomepageConfigMapper::getInstance()->resetToTemplate($homepageConfig);

		/* Ram it back into its parent object where it belongs
		 */
		$model->setHomepage($homepageConfig);
	}

	// no changes in "KEEP_EXISTING" case

	if ($_POST['groupcheck']) {

		/* Prevent students with write access from being able to
		 * create folders
		 */
		if ($groupHandler->allowOwner($_SESSION['id']) ) {

            /* Setup group and forward to group management page */
            $groupHandler->createGroup($category['id']);
            $groupHandler->addUser($category['id'], $_SESSION['id'], 3);

            SitePage::localRedir('/group/modify/'.$category['id']);
		}

        error(20);
	}
	else {
		/* Redirect to / if the homepage was removed from a category
		 * Otherwise go back to the category
		 */
		if ($_POST['template'] == Homepage::REMOVE_HOMEPAGE_TEMPLATE_ID) {
			SitePage::localRedir('/resources/' . $model->getId());
		}
		else {

			/* If a category code exists, just go back to where you came from
			 */
			if (strlen(trim($category['code']))) {
				$page->backToTheFuture(2);
			}

			/* Otherwise if you deleted the category code, redirect to
			 * categoryId link instead
			 */
			SitePage::localRedir('/homepage/' . $model->getId());
		}
	}
}
else if(is_numeric($_POST['move']) && is_numeric($_POST['categoryID'])) {

	/* Move link from one category to another, after checking permission on both from and to categories */
	if ($_POST['move'] == $_POST['categoryID'] || !$category = $categoryHandler->getByID($_POST['move']) ) {
		echo "Failed";
		exit;
	}

	if ($groupHandler->getPermission($_POST['move']) < WRITE_ACCESS  || $groupHandler->getPermission($_POST['categoryID']) < WRITE_ACCESS) {
		echo "Insufficient permssions";
		exit;
	}

	CategoryMapper::getInstance()->save(
		CategoryMapper::getInstance()->findById($_POST['move'])
			->setParent(CategoryMapper::getInstance()->createModel()->setId($_POST['categoryID']))
	);
}
else if (is_numeric($_GET['delete'])) {
	if (!$category = $categoryHandler->getByID($_GET['delete'])) {
		error(2);
	}

	if ($groupHandler->getPermission($category['id']) < WRITE_ACCESS) {
		error(8);
	}

	CategoryMapper::getInstance()->delete(
		CategoryMapper::getInstance()->findById($_GET['delete'])
	);

	$_SESSION['resourcesID'] = $category['parent'];
}
else if (is_numeric($_GET['deleteAll'])) {
	if (!$category = $categoryHandler->getByID($_GET['deleteAll'])) {
		error(2);
	}

	$categoryHandler->recursiveDelete($_GET['deleteAll']);
	$_SESSION['resourcesID'] = $category['parent'];
}
else {
	error(2);
}

$page->backToTheFuture(1);