<?php

$groupHandler = new Group;
$categoryHandler = new Category;

$userId = $_SESSION['id'];

if ( !empty($_POST) ) {
    $sourceCategoryId = $_POST['sourceCategoryId'];
    $destinationCategoryList = $_POST['destinationCategoryList'];

    if ( is_numeric($sourceCategoryId) && is_array($destinationCategoryList) ) {
        foreach ( $destinationCategoryList as $destinationCategoryId ) {

            if ( $sourceCategoryId != $destinationCategoryId ) {
                $categoryHandler->migrateCategory($sourceCategoryId, $destinationCategoryId);
            }

        }
    }
    else {
        $this->error(2);
    }

    $this->setFile(TEMPLATE_content, 'modules/homepage-migrate/success.html');

    $this->setBlock(TEMPLATE_content, 'destination_category', 'destination_category_block');

    $sourceCategory = $categoryHandler->getByID($sourceCategoryId);

    $this->setVar('SOURCE_CATEGORY_NAME', $sourceCategory['name']);

    if ( is_array($destinationCategoryList) ) {
        foreach ( $destinationCategoryList as $destinationCategoryId ) {
            $destinationCategory = $categoryHandler->getByID($destinationCategoryId);

            $this->setVar('DESTINATION_CATEGORY_NAME', $destinationCategory['name']);

            $this->parse('destination_category_block', 'destination_category');
        }
    }
}
else {
    $this->setFile(TEMPLATE_content, 'modules/homepage-migrate/index.html');

    $this->setBlock(TEMPLATE_content, 'no_categories', 'no_categories_block');
    $this->setBlock(TEMPLATE_content, 'homepage_migrate', 'homepage_migrate_block');

    $this->setBlock('homepage_migrate', 'destination_category', 'destination_category_block');

    /* Source category list */
    $openCategory = 0;

    if (is_numeric($this->arg[0])) {
        $openCategory = $this->arg[0];
    }

    // check permissions on selected category
    if ($groupHandler->getPermission($openCategory) < READ_ACCESS) {
    	$openCategory = 0;
    }

    // log view
    if ($openCategory > 0) {
    	$categoryHandler->logPageView($openCategory);
    }

    $treeBuilder = new Schoolbox\Resource\CategoryTreeBuilder(
        Schoolbox\Resource\CategoryMapper::getInstance(),
        Schoolbox\Assessment\Course\TaskMapper::getInstance(),
        $groupHandler
    );
    $this->setVar('TREEDATA', json_encode($treeBuilder->buildPartialTree($openCategory)));

    if ( $involvedCategoryList = $groupHandler->involvedInCategoryList($userId, true, WRITE_ACCESS) ) {
        $this->setVar('no_categories_block', '');

        foreach ( $involvedCategoryList as $involvedCategory ) {
            $this->setVar( array(
                'DESTINATION_HOMEPAGE_ID' => $involvedCategory['id'],
                'DESTINATION_HOMEPAGE_NAME' => $involvedCategory['name'],
                'DESTINATION_CLASS_CODE' => '',
            ) );

            if($involvedCategory['code'])
                $this->setVar('DESTINATION_CLASS_CODE', '('.$involvedCategory['code'].')');

            $this->parse('destination_category_block', 'destination_category', true);
        }

        $this->parse('homepage_migrate_block', 'homepage_migrate');
    }
    else {
        $this->setVar('homepage_migrate_block', '');

        $this->parse('no_categories_block', 'no_categories');
    }
}