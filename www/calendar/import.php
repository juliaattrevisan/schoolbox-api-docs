<?php
/**
 * ${NAME}
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
use Schoolbox\Application\WebRequest\Kernel;
use Schoolbox\Permission\Access\UserAccess;
use \Schoolbox\Resource\Folder\Mapper as FolderMapper;
use \Schoolbox\Breadcrumbs;

// not in SitePage context?
if (!isset($this)) {
    http_response_code(404);
    die('No direct access permitted');
}
/** @var SitePage $this */

$groupHandler = new Group();

if ($groupHandler->getPermission($this->arg[0]) < UserAccess::ACCESS_WRITE) {
	error(19);
}
else {
	try {
		$folderMapper = new FolderMapper();
		$folder = $folderMapper->findById($this->arg[0]);

		$this->setFile(TEMPLATE_content, 'calendar/import.html');

		/* Render out breadcrumbs
 		*/
		$trail = new Breadcrumbs();
		$trail->renderIntoSitePage($this, 'BREADCRUMB', [
			[
				'text' => $folder->getName(),
				'href' => "/homepage/{$folder->getId()}",
				'class' => '',
			],
			[
				'text' => 'Import Calendar',
				'href' => '',
				'class' => 'active',
			]
		]);

		$zone = new \Schoolbox\Resource\Attachment\Zone('upload-calendar');
		$zone->setThumbnailSize(64);
		$zone->setAttachPrompt('Upload calendar file');
		$zone->setAttachmentsLabel('');
//		$zone->setMimeFilter('text/csv,text/calendar');
		$zone->setModalTitle('Select zip file');

		$zone->renderIntoSitePage($this, 'ATTACHZONE');

		$this->setVar(array(
			'HOMEPAGE_ID' => $this->arg[0],
			'HOMEPAGE_NAME' => $folder->getName()
		));
	}
	catch (\Exception $e) {
		error(13);
	}
}
