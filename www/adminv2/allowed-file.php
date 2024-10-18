<?php

use Schoolbox\Admin\Navigation\Subnav;
use Schoolbox\Collection;
use Schoolbox\File\ExtensionMapper;
use Schoolbox\File\Mimetype;
use Schoolbox\File\MimetypeMapper;

if (!$_SESSION['user']->isSuperuser()) {
    error(19, "", 401);
}

$this->setTitle('Admin - Allowed Files');
$this->setFile(TEMPLATE_content, 'adminv2/allowed-files.html');
$this->setBlock(TEMPLATE_content, 'file', 'file_block');
$this->setBlock('file', 'file_extension', 'file_extension_block');
$this->setBlock('file', 'enabled', 'enabled_block');
$this->setBlock(TEMPLATE_content, 'image', 'image_block');

// include the admin navigation
(new Subnav($this->getDi()->get('config'), $this->getDi()->get('user')))
    ->generatePhplib($this, '/adminv2/allowed-file')
    ->generateBreadcrumbsHtml($this, '/adminv2/allowed-file');

/** @var Collection|Mimetype[] $mimes */
$mimes = MimetypeMapper::getInstance()->findAll([ 'deleted' => false, 'order' => [ 'mime' => 'asc' ] ]);

$default_size = Alaress_Byte_Format::bytesToString(Config::get('max_file_size'));
$placeholder  = "$default_size (default)";

if ($mimes->count()) {
    foreach ($mimes as $mime) {
        $this->setVar('file_extension_block', '');

        $extensions   = ExtensionMapper::getInstance()->findByColumn('fileTypeID', $mime->getId());
        $extensions   = $extensions->getPropertyValues('extension');
        $extensionCSV = implode(',', $extensions);
        if (count($extensions)) {
            foreach ($extensions as $ext) {
                $this->setVar('FILE_EXTENSION', $ext);
                $this->parse('file_extension_block', 'file_extension', true);
            }
        } else {
            $this->setVar('file_extension_block', '(none)');
        }

        if ($mime->isEnabled()) {
            $this->setVar('enabled_block', '');
        } else {
            $this->parse('enabled_block', 'enabled');
        }

        $max_size_output = Alaress_Byte_Format::bytesToString($mime->getActualMaxFileSize());
        $output = array(
            'FILE_ID'                  => $mime->getId(),
            'FILE_ENABLED'             => $mime->isEnabled() ? 'true'    : 'false',
            'FILE_ENABLED_ACTION'      => $mime->isEnabled() ? 'disable' : 'enable',
            'FILE_ENABLED_ICON'        => $mime->isEnabled() ? 'close'   : 'approve',
            'FILE_ENABLED_TITLE'       => $mime->isEnabled() ? 'Disable' : 'Enable',
            'FILE_DESC'                => Scrub::text($mime->getDescription()),
            'FILE_MIME'                => $mime->getMime(),
            'FILE_EXTENSION_CSV'       => $extensionCSV,
            'FILE_ICON'                => urlencode($mime->getIconFile()),
            'FILE_MAXSIZE'             => $mime->getMaxFileSize() ? $max_size_output : "<em>$placeholder</em>",
            'FILE_MAXSIZE_INPUT'       => $mime->getMaxFileSize() ? $max_size_output : '',
            'FILE_MAXSIZE_PLACEHOLDER' => htmlspecialchars($placeholder, ENT_QUOTES),
            'FILE_INLINE'              => $mime->getInline() ? 'Inline' : 'External',
            'FILE_EXTERN_CHECKED'      => $mime->getInline() ? '' : 'selected="selected"',
        );
        $this->setVar($output);
        $this->parse('file_block', 'file', true);
    }
}

/* Output list of icons */
$allowedFile = new AllowedFile();
$iconList = $allowedFile->getIconList();

if (is_array($iconList)) {
    foreach ($iconList as $icon) {
        $this->setVar('IMAGE_FILE', urlencode($icon));
        $this->parse('image_block', 'image', true);
    }
}
