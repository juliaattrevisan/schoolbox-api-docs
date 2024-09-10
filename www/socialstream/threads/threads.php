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

/* Get the component instance based on this instance id,
 * we assume the user has access to the homepage where this instance exists
 */
$instance = \Component_InstanceMapper::getInstance()->findByInstanceId($instanceId);

/* Sew thread if instance exists
 */
if ($instance) {
    // check if component instance is for the specified homepage
    $comSource = $instance->getIdentifier();
    if ('' !== (string)$instance->getConfigByKey('src')->getValue()) {
        $comSource = (int)$instance->getConfigByKey('src')->getValue();
    }
    if ($comSource === $homepageId) {
        $thread = new Threads($instance, $homepageId, $start, $offset);
        $output = $thread->generate($page)->render();

        echo $output;
    }
}
