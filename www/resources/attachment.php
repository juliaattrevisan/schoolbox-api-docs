<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

use Schoolbox\Resource\Attachment\External;
use Schoolbox\Resource\Attachment\Internal;

if (empty($this)) {
    require_once __DIR__ . '/../../include/Bootstrap.php';
    SitePage::localRedir('/resources/attachment');
}

/** @var SitePage $this */

$this->setAjax();

$this->setFile(TEMPLATE_content, '/resources/attach-browser.html');

$this->setBlock(TEMPLATE_content, 'source', 'source_block');

$multi       = empty($_GET['multi']) ? false : !!($_GET['multi']);
$mimeFilter  = empty($_GET['mime'])  ? false : $_GET['mime'];

// register our known defaults
$factory = new \Schoolbox\Resource\Attachment\Factory(array(
    'internal' => array(
        'upload'    => Internal\UploadController::class,
        'usermedia' => Internal\UsermediaController::class,
        'browse'    => Internal\BrowseController::class,
        'myfile'    => Internal\MyfileController::class,
    ),
    'external' => array(
        'webdav' => External\WebdavController::class,
        'google' => External\GoogleController::class,
        'office' => External\Office365Controller::class
    )
));

$factory->allowMulti($multi)
        ->setMimeFilter($mimeFilter)
        ->setConfig(new \Schoolbox\Config\Loader())
        ->setRosetta($this->getDi()->get('l10n'));

foreach ($factory->getMappedControllers() as $controller) {

    // the first controller found will be considered the 'default'
    if(!$this->getVar('DEFAULT_VIEW')) {
         $this->setVar('DEFAULT_VIEW', $controller->getSlug());
    }

    $this->setVar(array(
        'SRC_SLUG'   => str_replace('/', '-', $controller->getSlug()),
        'SRC_ICON'   => $controller->getIcon(),
        'SRC_LABEL'  => $controller->getLabel(),
        'SRC_URL'    => $controller->getUrl(),
        'SRC_METHOD' => $controller->getRenderMethod()
    ));
    $this->parse('source_block', 'source', true);
}

$this->template->pparse('finaloutput', TEMPLATE_content);