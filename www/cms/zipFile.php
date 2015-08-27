<?php

require_once(dirname(__FILE__) . '/../../include/Bootstrap.php');

$folderMapper = new \Schoolbox\Resource\CategoryMapper();

$isSuperuser = $_SESSION['user']->isSuperuser();
$isAllowedTeacher = ($_SESSION['user']->getRole()->isStaff() && \Cfg::get('enable_teacher_zip_upload'));

if (($isSuperuser || $isAllowedTeacher) && is_numeric($this->arg[0])) {
    $this->setFile(TEMPLATE_content, 'cms/zipFile.html');
    $this->setBlock(TEMPLATE_content, 'perm_table', 'perm_table_block');

    try {
        $folder = $folderMapper->findById($this->arg[0]);
    } catch (\Exception $e) {
    }

    $this->setVar(array(
        'CATEGORY_NAME' => Scrub::text($folder->getName()),
        'CID' => $folder->getId()
    ));

    /* Set up permissions options
	 */
    $roleMapper = new \Schoolbox\RoleMapper();
    $allRoles = $roleMapper->findAll();

    $zone = new \Schoolbox\Resource\Attachment\Zone('news-feature');
    $zone->setThumbnailSize(64);
    $zone->setAttachPrompt('Upload zip file');
    $zone->setAttachmentsLabel('');
    $zone->setMimeFilter('application/x-zip-compressed');
    $zone->setModalTitle('Select zip file');

    $zone->renderIntoSitePage($this, 'ATTACH_ZONE');

    if ($allRoles->count()) {
        foreach ($allRoles as $currentRole) {
            $this->setVar(array(
                'GROUP_NAME'		=> Scrub::text($currentRole->getName()),
                'GROUP_ID'			=> $currentRole->getId(),
                'PERM_0_SELECTED'	=> 'checked="checked"',
                'PERM_1_SELECTED'	=> '',
                'PERM_2_SELECTED'	=> '',
            ));

            $this->parse('perm_table_block', 'perm_table', true);
        }
    } else {
        $this->setVar('perm_table_block', '');
    }
} else {
    error(19);
}
