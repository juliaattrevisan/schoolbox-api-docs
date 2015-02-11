<?php


$this->setFile(TEMPLATE_content, 'group/bulk.html');

$this->setTitle('Add Bulk Members');
$this->setTab('resources');

$folderMapper = new \Schoolbox\Resource\CategoryMapper();

if (is_numeric($this->arg[0])) {
    try {
        $folder = $folderMapper->findById($this->arg[0]);
    }
    catch (\Exception $e) {
        error(19);
    }

    $userAccessMapper = new \Schoolbox\Permission\Access\UserAccessMapper();
    $userAccess = $userAccessMapper->findByColumnList(array(
        'sid' => $folder->getId(),
        'service' => HOMEPAGE_SERVICE
    ))->first();

    $isFolderOwner = false;

    if ($userAccess instanceof \Schoolbox\Permission\Access\UserAccess) {
        if ($userAccess->getUser()->getId() == $_SESSION['user']->getId()) {
            $isFolderOwner = true;
        }
    }

	// check access
	if (!$_SESSION['su'] && !$isFolderOwner) {
		error(19);
    }

	$output = array('CATEGORY_NAME'	=> Scrub::text($folder->getName()),
					'CATEGORY_ID'	=> $folder->getId(),
					);
	$this->setVar($output);

} else {
	error(2);
}
