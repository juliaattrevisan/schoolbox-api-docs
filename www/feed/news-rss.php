<?php
/**
 * ${NAME}
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
require_once $_SERVER['DOCUMENT_ROOT'] . '/../include/Bootstrap.php';

$user = null;
$category = null;

$params = explode('/', $_SERVER['REQUEST_URI']);

if (strlen($params[3])) {
    try {
        $userMapper = new \Schoolbox\UserMapper();
        $user = $userMapper->findOneByColumn('hash', $params[3]);
    }
    catch (\Exception $e) {
        error(31);
    }

    /* Optional category filter
     */
    if (is_numeric($params[4])) {
        try {
            $categoryMapper = new \Schoolbox\Resource\CategoryMapper();
            $category = $categoryMapper->findById($params[4]);
        }
        catch (\Exception $e) {
        }
    }

    /* Find the articles
     */
    $articleMapper = new \Schoolbox\News\ArticleMapper();
    $allNews = $articleMapper->findAllForUserAndCategory($user, $category);

    foreach($allNews as $currentNews) {
        new \Alaress_Probe($currentNews->getHeading());
    }
}
else {
    error(31);
}