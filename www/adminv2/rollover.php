<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2013, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

$group = new Group();

if ( !$group->isSuperuser() ) {
	error(19);
}

/**
 * @var $this SitePage
 */

$this->setFile(TEMPLATE_content, 'adminv2/rollover.html');

$this->setBlock(TEMPLATE_content, 'category_list', 'category_list_block');

$nav = new AdminV2_Nav();
$nav->parse($this, 'rollover');

$categoryHandler = new Category();
$groupHandler = new Group();

$openCategory = 0;

// shamelessly copied from resources.php
$categoryList = $categoryHandler->getCategoryNode($openCategory);


// get parentIDs from openCategory to 0
$parentList[] = $parentID = $openCategory;
$i = 0;
while ($parentID > 0) {
	if ($i++ > 100) {
		break;
	}
	$parentList[] = $parentID = (int)$categoryHandler->getParent($parentID);
}


if (is_array($categoryList)) {

	foreach ($categoryList as $category) {
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

			// mark courses
			if ($category['taskCount'] > 0) {
				$class[] = "course";
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
				'CATEGORY_NAME' => htmlspecialchars_decode($name),
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