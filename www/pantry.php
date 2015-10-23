<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2015, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
use Schoolbox\Resource\Attachment\Zone;

/* @var SitePage $this */

$tpl = isset($this->arg[0]) ? $this->arg[0] : 'index';

$this->setFile(TEMPLATE_content, "pantry/$tpl.htm");

$files = [
    '01d5ac6b3d48b0d661dc47409a900cf8a95692b5',
    '8d6cc6bca5c82e880802293380e360435bac9b7d'
];

// render in a libbed attachzone
$zone = new Zone('affix-lib-label-here');
$zone->attachFiles($files)->setMulti(true);
$zone->renderIntoSitePage($this, 'AFFIX_LIB_LABEL_HERE');

// jam in a twigged attachzone
$twig = new Twig_Environment(new Twig_Loader_Filesystem(SITE_root . '/templates/core/sub'));

$zone = new Zone('affix-twig-label-here');
$zone->attachFiles($files)->setMulti(true)->setAttachmentsLabel('Attach your files here');
$this->setVar('AFFIX_TWIG_LABEL_HERE', $twig->render('attachzone.twig', [ 'attachzone' => $zone->getTwigContext() ]));

// output a few common vars
$this->setVar([

    'REQUEST_TIME' => Alaress_Date_Format::now()->setTimestamp($_SERVER['REQUEST_TIME'])->getLong()

]);

if (isset($_GET['ajax']) && $_GET['ajax']) {
    $this->setAjax();
    $this->template->pparse('finaloutput', TEMPLATE_content);
}
