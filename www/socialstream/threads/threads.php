<?php

/**
 * ${NAME}
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace SocialStream;

require_once __DIR__ . '/../../../conf/core/require.php';
$kernel = new \Schoolbox\Application\WebRequest\Kernel();

/** @var \SitePage $page */
$page = $kernel->get('sitePage');
$page->initSession();
$page->injectGlobalVars();

$instanceId = intval($_GET['instanceId']);
$homepageId = intval($_GET['homepageId']);
$start = intval($_GET['start']);
$offset = intval($_GET['offset']);
$instance = \Component_InstanceMapper::getInstance()->findByInstanceId($instanceId, $homepageId);

/* Sew thread if instance exists
 */
if ($instance) {
    $thread = new Threads($instance, $homepageId, $start, $offset);
    $output = $thread->generate($page)->render();

    echo $output;
}
