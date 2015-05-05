<?php

use Schoolbox\File\MimetypeMapper;

if (!Cfg::get('webdav_enable')) {
    error(141, "Drives service not enabled");
}

/* Check if password is set; if not, the user was authenticated via saml
 * and we never knew their password, so ask them to provide it
 */
if (!strlen($_SESSION['password'])) {
	$this->localRedir('/cms/networkLogin');
}

$this->setFile(TEMPLATE_content, 'cms/network.html');
$this->setBlock(TEMPLATE_content, 'file_list', 'file_list_block');
$this->setBlock('custom_head', 'allowed_files', 'allowed_files_block');

$this->setTitle('My Network');
$this->setTab('network');

$groupHandler = new Group;
$allowedFiles = new AllowedFile;

if (isset($this->arg[0])) {
    $_SESSION['portfolioID'] = $this->arg[0];
    SitePage::localRedir('/cms/network/');
} else {
    $openFolder = $_SESSION['portfolioID'];
}

try {
    $folderHandler = PortfolioWebDAV::factory();
} catch (DomainException $e) {
    error(141, $e->getMessage());
}

$fileHandler =& $folderHandler;

$openFolder = (int)$openFolder;

// Build a tree up to the last resource we looked at
$treeBuilder = new WebdavTreeBuilder($folderHandler);
$this->setVar(
    array(
        'TREEDATA' => json_encode($treeBuilder->buildPartialTree($openFolder)),
        'OPEN'     => intval($openFolder),
    )
);

// output files, column 2
$fileList = $fileHandler->getAllInFolder($openFolder);
if (is_array($fileList) && count($fileList) > 0) {

    foreach ($fileList as $file) {

        $class = array();

        // icon
        try{
            $mime = MimetypeMapper::getInstance()->getByFilename($file['description']);
        } catch (Exception $e) {
            $mime = MimetypeMapper::getInstance()->getUnknownMime();
        }

        /* Note that WebDAV returns the $file['submitted'] value in a different format
           * than Schoolbox.
           */
        $modifiedTime = new Alaress_Date_Format(strtotime($file['submitted']), 'U');

        $output = array(
            'FILE_ID' => $file['id'],
            'TYPE' => 'file',
            'FILE_NAME' => Scrub::text($file['description']),
            'FILE_MODIFIED' => $modifiedTime->getShort(),
            'FILE_SIZE' => $file['file_size'] > 0 ? Alaress_Byte_Format::bytesToString($file['file_size']) : "--",
            'FOLDER_ID' => $file['parent'],
            'CLASS' => $mime->getClass(),
            'PUBLISHED' => $file['published'] != '' ? $file['published'] : '',
        );

        $this->setVar($output);
        $this->parse('file_list_block', 'file_list', true);

    }
} else {
    $this->setVar(
        'file_list_block', '<tr class="category-' . $openFolder . ' empty"><td>No files to display</td></tr>'
    );
}

// output mime classes
$allowedList = $allowedFiles->getAll();
if (is_array($allowedList)) {

    $last = '';
    foreach ($allowedList as $allowed) {
        if ($last != $allowed["iconFile"]) {
            $output = array(
                "NAME" => $allowedFiles->getClass($allowed["iconFile"]),
                "ICON" => $allowed["iconFile"],
            );

            $this->setVar($output);
            $this->parse("allowed_files_block", "allowed_files", true);

            $last = $allowed["iconFile"];
        }
    }
} else {
    $this->setVar('allowed_files_block', '');
}

