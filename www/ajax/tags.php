<?php
require_once dirname(__FILE__) . '/../../include/Bootstrap.php';
require_once SITE_root . '/www/include/stdfuncs.php';

$tags = \Schoolbox\Pastoral\Tags\Mapper::getInstance()->findAll();

$tagsarray = [];
$found = [];

foreach ($tags as $tag) {
    if (!isset($found[strtolower($tag->getName())])) {
        if (strpos(strtolower($tag->getName()), $_GET["query"]) !== false) {
            $tagsarray[] = ['id' => $tag->getName(), 'name' => $tag->getName()];
        }
        $found[strtolower($tag->getName())] = 1;
    }
}

header('Content-type: application/json');
echo json_encode($tagsarray);
