<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

use Alaress\Web\Response\Factory;
use Alaress\Web\Template\DebugTwig;
use Schoolbox\Admin\Navigation\Subnav;
use Schoolbox\Resource\Folder;

$group = new Group();

if (!$group->isSuperuser()) {
    error(19, "", 401);
}

/**
 * @var $this SitePage
 */

$this->setFile(TEMPLATE_content, 'adminv2/rollover.html');

// include the admin navigation
(new Subnav($this->getDi()->get('config'), $this->getDi()->get('user')))
    ->generatePhplib($this, '/adminv2/rollover')
    ->generateBreadcrumbsHtml($this, '/adminv2/rollover');

$openCategory = 0;

// shamelessly copied from resources.php
$treeBuilder = new \Schoolbox\Resource\Browser\Resources\TreeBuilder(
    Folder\Mapper::getInstance(),
    $_SESSION['user'],
    $this->getLocalisedTerm('resource', true, true)
);

/** @var Factory $responseFactory */
$responseFactory = $this->getDi()->get('httpResponseFactory');
$this->setVar(
    'JS_NODE_TYPE_ICON_MAP',
    $responseFactory->buildUnthemedHtml(
        '@resourceBrowser/jsNodeIconMap.twig',
        [],
        $_SESSION['user'],
    )->getBody()->getContents()
);
$this->setVar('TREEDATA', json_encode($treeBuilder->buildPartialTree($openCategory)));
