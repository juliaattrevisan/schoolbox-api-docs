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
$categoryMapper = CategoryMapper::getInstance();
$categoryMapper->setSessionData($_SESSION);
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

    /** @var \Schoolbox\Resource\Category $model */
    $model = $categoryMapper->createModel()
        ->setName($_POST['name'])
        ->setParent(
            $categoryMapper->createModel()
                ->setId($_POST['parent'])
        );

    // Only superusers get to change class codes
    $codeCollection = new \Schoolbox\Collection();
    if ($groupHandler->isSuperuser()) {
        foreach (explode(',', $_POST['code']) as $code) {
            $trimmedCode = trim($code);
            if (strlen($trimmedCode)) {
                $codeModel = new \Schoolbox\Resource\CategoryCode();
                $codeModel->setCode($trimmedCode);
                $codeCollection->add($codeModel);
            }
        }
        $model->setCodes($codeCollection);
    }

    if (is_numeric($_POST['categoryID'])) {
        if ($groupHandler->getPermission($_POST['categoryID']) < WRITE_ACCESS) {
            error(6);
        }
        $model->setId($_POST['categoryID']);
        $category['id'] = $_POST['categoryID'];
        $model->setId($_POST['categoryID']);
    } elseif ($groupHandler->getPermission($_POST['parent']) < WRITE_ACCESS) {
        error(6);
    }

    // Delete cat codes here, allowing them to be recreated when the category
    // gets saved.
    // (I'm not confident that deleting cat codes in the postSave method
    //  regardless of whether there are any won't break somewhere else)
    if ($groupHandler->isSuperuser() && $model->getId() && count($codeCollection) === 0) {
        CategoryCodeMapper::getInstance()->delete(
            CategoryCodeMapper::getInstance()->findByColumn('categoryID', $model->getId())
        );
    }
    $affectedIds = $categoryMapper->save($model);

    if ($affectedIds === false) {
     // something went wrong
        error(2);
    }

    $model->setId($affectedIds[0]);

    // if rolepermission collection is defined, reapply permissions (possibly recursively)
    $rolePermissions = RoleAccessMapper::getInstance()->savePermissions($model, $_POST['group'], $_POST['recursive']);

    /* HomepageConfig definition
     */
    $homepageConfig = HomepageConfigMapper::getInstance()
        ->createModel()
        ->setCategory($model);

    // if homepage is to be removed
    if ($_POST['template'] == HomepageTemplate::HOMEPAGE_REMOVE) {
        HomepageConfigMapper::getInstance()->emptyHomepage($homepageConfig);
        HomepageConfigMapper::getInstance()->delete($homepageConfig);
    } // if template defined
    elseif ($_POST['template'] > 0) {
        $homepageConfig->setTemplate(HomepageTemplateMapper::getInstance()->findById($_POST['template']));
     /* Save homepage config
         */
        HomepageConfigMapper::getInstance()->save($homepageConfig);
        HomepageConfigMapper::getInstance()->resetToTemplate($homepageConfig);
    }

    /* Ram it back into its parent object where it belongs
     */
    $model->setHomepage($homepageConfig);

    // no changes in "KEEP_EXISTING" case

    if ($_POST['groupcheck']) {
        /* Prevent students with write access from being able to
         * create folders
         */
        if ($groupHandler->allowOwner($_SESSION['id'])) {
            /* Setup group and forward to group management page */
            $groupHandler->createGroup($model->getId());
            $groupHandler->addUser($model->getId(), $_SESSION['id'], 3);
            SitePage::localRedir('/group/modify/'.$model->getId());
        }
        error(20);
    } else {
        /* Redirect to / if the homepage was removed from a category
         * Otherwise go back to the category
         */
        if ($_POST['template'] == Homepage::REMOVE_HOMEPAGE_TEMPLATE_ID) {
            SitePage::localRedir('/resources/' . $model->getId());
        } else {
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
} elseif (is_numeric($_POST['move']) && is_numeric($_POST['categoryID'])) {
    /* Move link from one category to another, after checking permission on both from and to categories */
    if ($_POST['move'] == $_POST['categoryID'] || !$category = $categoryHandler->getByID($_POST['move'])) {
        echo "Failed";
        exit;
    }

    if ($groupHandler->getPermission($_POST['move']) < WRITE_ACCESS
        || $groupHandler->getPermission($_POST['categoryID']) < WRITE_ACCESS
    ) {
        echo "Insufficient permssions";
        exit;
    }

    $categoryMapper->save(
        $categoryMapper->findById($_POST['move'])
            ->setParent($categoryMapper->createModel()->setId($_POST['categoryID']))
    );
} elseif (is_numeric($_GET['delete'])) {
    if (!$category = $categoryHandler->getByID($_GET['delete'])) {
        error(2);
    }

    if ($groupHandler->getPermission($category['id']) < WRITE_ACCESS) {
        error(8);
    }

    // Are there any children of this category?
    // If so, error out if not a superuser
    if ($categoryMapper->countByColumn('parent', $_GET['delete'])) {
        if (!$groupHandler->isSuperuser()) {
            error(12);
        }
    }
    $categoryMapper->delete($categoryMapper->findById($_GET['delete']));

    $_SESSION['resourcesID'] = $category['parent'];
} elseif (is_numeric($_GET['deleteAll'])) {

    // only superusers can do this no matter what
    if (!$groupHandler->isSuperuser()) {
        error(12);
    }

    try {
        $category = $categoryMapper->findById($_GET['deleteAll']);
        $categoryMapper->recursiveDelete($category);
        $_SESSION['resourcesID'] = $category->getParent()->getId();
    }
    catch (Exception $e) {
        error(2);
    }
} else {
    error(2);
}

$page->backToTheFuture(1);
