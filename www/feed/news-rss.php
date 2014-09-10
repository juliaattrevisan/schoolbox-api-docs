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

if (strlen($this->arg[0])) {
    try {
        $userMapper = new \Schoolbox\UserMapper();
        $user = $userMapper->findOneByColumn('hash', $this->arg[0]);
    }
    catch (\Exception $e) {
        error(31);
    }

    /* Optional category filter
     */
    try {
        $categoryMapper = new \Schoolbox\Resource\CategoryMapper();
        $category = $categoryMapper->findById($this->arg[1]);
    }
    catch (\Exception $e) {
        $category = null;
    }

    $articleMapper = new \Schoolbox\News\ArticleMapper();
    $allNews = $articleMapper->findAllForUserAndCategory($user, $category);

    foreach($allNews as $currentNews) {
        new \Alaress_Probe($currentNews->getPublishDate()->getDatabase());
    }
}
else {
    error(31);
}