<?php

require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/category.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/file.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/link.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/group.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/allowed-file.php';

$this->setFile(TEMPLATE_content, 'resources.html');
$this->setBlock(TEMPLATE_content, 'category_list', 'category_list_block');
$this->setBlock(TEMPLATE_content, 'file_list', 'file_list_block');
$this->setBlock('custom_head', 'allowed_files', 'allowed_files_block');
$this->setBlock('custom_head', 'delete_all', 'delete_all_block');
$this->setBlock('custom_head', 'zip_file_upload', 'zip_file_upload_block');

$this->setTitle('Resources');
$this->setTab('resources');

$categoryHandler = new Category;
$fileHandler = new File;
$linkHandler = new Link;
$groupHandler = new Group;
$allowedFiles = new AllowedFile;


if (is_numeric($this->arg[0])) {
	$_SESSION['resourcesID'] = $this->arg[0];
	localRedir('/resources/');
} else
	$openCategory = (int)$_SESSION['resourcesID'];

// check permissions on selected category
if ($groupHandler->getPermission($openCategory) < READ_ACCESS)
	$openCategory = 0;

// log view
if ($openCategory > 0)
	$categoryHandler->logPageView($openCategory);

// get parentIDs from openCategory to 0
$parentList[] = $parentID = $openCategory;
$i = 0;
while ($parentID > 0) {
	if ($i++ > 100) break;
	$parentList[] = $parentID = (int)$categoryHandler->getParent($parentID);
}

$output = array('WRITE'	=> $groupHandler->getPermission(0) >= WRITE_ACCESS ? 'write' : '',
				'FULL'	=> $groupHandler->getPermission(0) >= FULL_ACCESS ? 'full' : '',
				'OPEN' 	=> $openCategory,
				'HTTP_HOST'	=> $_SERVER['HTTP_HOST'],
				);
$this->setVar($output);

// output folders, column 1
$categoryList = $categoryHandler->getCategoryNode($openCategory);
if (is_array($categoryList)) {

	foreach ($categoryList as $category) {
		// get permissions
		if (!isset($permission[$category['id']]))
			$permission[$category['id']] = $groupHandler->getPermission($category['id']);

		// limit permission by parent
		//if (isset($permission[$category['parent']]) && $permission[$category['id']] > $permission[$category['parent']])
		//	$permission[$category['id']] = $permission[$category['parent']];

		// check permission
		if ($permission[$category['id']] >= READ_ACCESS) {

			$class = array();

			// set parent category
			$class[] = "child-of-node-{$category['parent']}";

			/* add plus sign to parent nodes */
			if ($category['isParent'] == '1')
				$class[] = in_array($category['id'], $parentList) ? 'expanded loaded' : 'collapsed';

			/* select default dir */
			if ($category['id'] == $openCategory)
				$class[] = 'selected';

			/* mark homepages */
			if ($category['template'] > 0)
				$class[] = "homepage";

			/* mark write access */
			if ($permission[$category['id']] == WRITE_ACCESS)
				$class[] = "write";

			/* mark full access*/
			if ($permission[$category['id']] == FULL_ACCESS)
				$class[] = "full";

			/* append code to name */
			$name = scrubTEXT($category['name']);
			if($category['code'] != '' && $category['code'] != $category['name'])
				$name .= ' ('.scrubTEXT($category['code']).')';

			$output = array('CATEGORY_ID'		=> $category['id'],
							'CATEGORY_NAME'		=> $name,
							'CLASS'				=> implode(" ", $class),
							);
			$this->setVar($output);
			$this->parse('category_list_block', 'category_list', true);
		}
	}

        /* If no permission to folders handle blank block */
        if(!$output['CATEGORY_ID']) {
            $this->setVar('category_list_block', '');
        }

	/* Prevent certain options from appearing unless you are a superuser
	 */

	if ($groupHandler->isSuperuser()) {
		$this->parse('delete_all_block', 'delete_all');
		$this->parse('zip_file_upload_block', 'zip_file_upload');
	}
	else {
		$this->setVar('delete_all_block', '');
		$this->setVar('zip_file_upload_block', '');
	}
}
else {
	$this->setVar('category_list_block', '');
}

// output files, column 2
$fileList = $fileHandler->mergeLinks( $fileHandler->getAllInCategory($openCategory), $linkHandler->getAllInCategory($openCategory));
$permission = $groupHandler->getPermission($openCategory);
if (is_array($fileList) && count($fileList) > 0) {

	foreach ($fileList as $file) {

		if (empty($file['modified']))
			$file['modified'] = $file['submitted'];

		$class = array();

		// is link
		$file['url'] ? $class[] = "link" : null;

		// write access
		$permission >= WRITE_ACCESS ? $class[] = "write" : null;

		// is hidden
		$file['hidden'] > 0 ? $class[] = "hidden" : null;

		// icon
		$class[] = !empty($file['iconFile']) ? $allowedFiles->getClass($file["iconFile"]) : "textfile";

		if (!($i++%2))
			$class[] = 'dark';

		$output = array('FILE_ID' 	 	=> $file['id'],
						'TYPE'			=> $file['url'] ? 'link' : 'file',
						'FILE_NAME' 	=> TextFormatter::cutString(scrubText($file['description']),32),
						'FILE_MODIFIED'	=> getRelativeTime($file['modified'], 0, ''),
						'FILE_OWNER'	=> formatName($file),
						'FILE_SIZE'		=> $file['file_size'] > 0 ? TextFormatter::bytesToString($file['file_size']) : "--",
						'CATEGORY_ID'	=> $file['category'],
						'CLASS'			=> implode(" ", $class),
						);

		$this->setVar($output);
		$this->parse('file_list_block', 'file_list', true);

	}
} else
	$this->setVar('file_list_block', '');

// output mime classes
$allowedList = $allowedFiles->getAll();
if (is_array($allowedList)) {

	// append link icon to resutls
	$allowedList[]['iconFile'] = "link.png";
	$last = '';
	foreach ($allowedList as $allowed) {
		if ($last != $allowed["iconFile"]) {
			$output = array("NAME"	=> $allowedFiles->getClass($allowed["iconFile"]),
							"ICON"	=> $allowed["iconFile"],
							);

			$this->setVar($output);
			$this->parse("allowed_files_block", "allowed_files", true);

			$last = $allowed["iconFile"];
		}
	}
} else
	$this->setVar('allowed_files_block', '');

?>