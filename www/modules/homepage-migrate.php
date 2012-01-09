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

    $this->setBlock(TEMPLATE_content, 'category_list', 'category_list_block');
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

    // get parentIDs from openCategory to 0
    $parentList[] = $parentID = $openCategory;
    $i = 0;
    while ($parentID > 0) {
    	if ($i++ > 100) {
    		break;
    	}
    	$parentList[] = $parentID = (int)$categoryHandler->getParent($parentID);
    }

    $output = array(
    	'WRITE' => $groupHandler->getPermission(0) >= WRITE_ACCESS ? 'write' : '',
    	'FULL' => $groupHandler->getPermission(0) >= FULL_ACCESS ? 'full' : '',
    	'OPEN' => $openCategory,
    	'HTTP_HOST' => $_SERVER['HTTP_HOST'],
    );
    $this->setVar($output);

    $sourceCategoryList = $categoryHandler->getCategoryNode($openCategory);

    if (is_array($sourceCategoryList)) {

    	foreach ($sourceCategoryList as $category) {
    		// get permissions
    		if (!isset($permission[$category['id']])) {
    			$permission[$category['id']] = $groupHandler->getPermission($category['id']);
    		}

    		// limit permission by parent
    		//if (isset($permission[$category['parent']]) && $permission[$category['id']] > $permission[$category['parent']])
    		//	$permission[$category['id']] = $permission[$category['parent']];

    		// check permission
    		if ($permission[$category['id']] >= READ_ACCESS) {

    			$class = array();

    			// set parent category
    			$class[] = "child-of-node-{$category['parent']}";

    			/* add plus sign to parent nodes */
    			if ($category['isParent'] == '1') {
    				$class[] = in_array($category['id'], $parentList) ? 'expanded loaded' : 'collapsed';
    			}

    			/* select default dir */
    			if ($category['id'] == $openCategory) {
    				$class[] = 'selected';
    			}

    			/* mark homepages */
    			if ($category['template'] > 0) {
    				$class[] = "homepage";
    			}

    			/* mark write access */
    			if ($permission[$category['id']] == WRITE_ACCESS) {
    				$class[] = "write";
    			}

    			/* mark full access*/
    			if ($permission[$category['id']] == FULL_ACCESS) {
    				$class[] = "full";
    			}

    			/* append code to name */
    			$name = Scrub::text($category['name']);
    			if ($category['code'] != '' && $category['code'] != $category['name']) {
    				$name .= ' (' . Scrub::text($category['code']) . ')';
    			}

    			$output = array(
    				'CATEGORY_ID' => $category['id'],
    				'CATEGORY_NAME' => $name,
    				'CLASS' => implode(" ", $class),
    			);
    			$this->setVar($output);
    			$this->parse('category_list_block', 'category_list', true);
    		}
    	}

    	/* If no permission to folders handle blank block */
    	if (!$output['CATEGORY_ID']) {
    		$this->setVar('category_list_block', '');
    	}
    }
    else {
    	$this->setVar('category_list_block', '');
    }

    if ( $involvedCategoryList = $groupHandler->involvedInCategoryList($userId, true, WRITE_ACCESS) ) {
        $this->setVar('no_categories_block', '');

        foreach ( $involvedCategoryList as $involvedCategory ) {
            $this->setVar( array(
                'DESTINATION_HOMEPAGE_ID' => $involvedCategory['id'],
                'DESTINATION_HOMEPAGE_NAME' => $involvedCategory['name'],
            ) );

            $this->parse('destination_category_block', 'destination_category', true);
        }

        $this->parse('homepage_migrate_block', 'homepage_migrate');
    }
    else {
        $this->setVar('homepage_migrate_block', '');
    }

}