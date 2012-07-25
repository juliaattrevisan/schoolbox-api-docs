<?php
/**
 * News digest for JPC
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

require_once(dirname(__FILE__) . '/../../../include/Bootstrap.php');

// every user will receive an email that contains the previous week's news
// this should be at a school, campus and then category level

$digestStart = strtotime('-1 week');
$digestEnd = time();
$host = 'jpc.alaress-dev.com.au';

$catHandler = new Category();
$newsHandler = new News();
$groupHandler = new Group();
$catnewsHandler = new CategoryNews();

$sql =
"SELECT u.id, u.`firstname`, u.`lastname`, u.group_id gid, u.`alt_email`, uc.campus
FROM `user` u
LEFT JOIN userCampus uc ON uc.uid = u.id
-- LIMIT 0, 15";

$userList = compileList($sql);

// go through again, and retrieve school/campus news
$keys = array_keys($userList);


printf("Memory limit: %s\n", ini_get('memory_limit'));

for($i = 0; $i < count($keys); $i++) {

    printf("Memory usage: %s\n", convert(memory_get_usage(true)));

    compileNews($userList[$keys[$i]]);
    sendEmail($userList[$keys[$i]]);

    unset($userList[$keys[$i]]);
}

printf("Final Size of user list: %s\n", count($userList));
printf("End Memory usage: %s\n", convert(memory_get_usage(true)));
printf("Peak Memory usage: %s\n", convert(memory_get_peak_usage(true)));

die();

/* * * * * * * * * * * * * * * * * * * * *
 * END PROCEDURE
 * * * * * * * * * * * * * * * * * * * * */




/**
 * @param $sql
 * @return array
 */
function compileList($sql) {
    $prevUID = -1;
    $userList = array();
    // compile into 2D assoc array
    $statement = Database::getInstance()->query($sql);
    while($user = $statement->fetch(PDO::FETCH_ASSOC)) {
        if($user['id'] != $prevUID) {
            $userList[$user['id']] = array(
                'id'        => $user['id'],
                'firstname' => $user['firstname'],
                'lastname'  => $user['lastname'],
                'gid'       => $user['gid'],
                'email'     => $user['alt_email'],
                'campus'    => array(),
            );
            $prevUID = $user['id'];
        }
        $userList[$user['id']]['campus'][] = $user['campus'];
    }
    return $userList;
}


/**
 * @param $u
 */
function compileNews(&$u) {

    global $newsHandler;
    global $groupHandler;
    global $catHandler;
    global $catnewsHandler;

    $newslist = $newsHandler->getFilteredNews($u['id'], $u['campus'], $u['gid']);

    foreach($newslist as $article) {
        $u['articles']['c'][0][$article['item_id']] = array(
            'id'       => $article['item_id'],
            'title'    => $article['item_heading'],
            'abstract' => 'Lorem upsum,',
            'topic'    => $article['topicName'],
            'date'     => strtotime($article['item_datetime_posted']),
        );
    }

    $access = $groupHandler->getAllUserAccess($u['id']);

    if($access) {
        foreach($access as $a) {
            if($a['name'] != 'Homepage') {
                continue;
            }
            $cat = $catHandler->getByID($a['sid']);
            $hNews = $catnewsHandler->getAllForCategory($a['sid'], false, false, $u['id']);
            if($hNews) {
                foreach($hNews as $art) {
                    $u['articles']['h'][$a['sid']][$art['id']] = array(
                        'id'       => $art['id'],
                        'title'    => $art['heading'],
                        'abstract' => 'Dolor sit amet.',
                        'topic'    => $cat['name'],
                        'date'     => $art['posted'],
                    );
                }
            }
        }
    }
}


/**
 * @param $u
 */
function sendEmail(&$u) {

    global $host;

    // do nothing if there's no email address
    if(!$u['email']) {
        return;
    }

    $mailer = new Mailer('news-digest.html', 'News', $u['email'], $host);
    $mailer->setBlock('tplBODY', 'newslist', 'newslist_block');
    $mailer->setBlock('newslist', 'article', 'article_block');
    $mailer->setVar('NAME', $u['firstname']);

    printf("Email to %s %s (%s)\n", $u['firstname'], $u['lastname'], $u['email']);

    foreach($u['articles'] as $newsType => $newsList) {

        switch($newsType) {
            case 'c':
                $mailer->setVar('NEWS_TYPE', 'School News');
                break;
            case 'h':
                $mailer->setVar('NEWS_TYPE', 'From your homepages');
                break;
        }

        foreach($newsList as $news) {
            foreach($news as $article) {
                if(withinWindow($article['date'])) {
                    print_r($article);
                    $mailer->setVar(array(
                        'ARTICLE_ID'       => $article['id'],
                        'ARTICLE_TITLE'    => $article['title'],
                        'ARTICLE_ABSTRACT' => $article['abstract'],
                        'ARTICLE_TOPIC'    => $article['topic'],
                        'ARTICLE_DATE'     => date('l, jS \o\f F', $article['date']),
                    ));

                    $mailer->parse('article_block', 'article', true);
                }
            }
        }
        $mailer->parse('newslist_block', 'newslist', true);
        $mailer->setVar('article_block', '');
    }

    // TODO remove in production: testing only, to prevent mass spammage
    if($u['email'] == 'matt.day@alaress.com.au') {
        $mailer->send();
    }
}


/**
 * @param $stamp
 * @return bool
 */
function withinWindow($stamp) {
    global $digestStart;
    global $digestEnd;
    return (($stamp > $digestStart) && ($stamp < $digestEnd));
}


/**
 * Useful for the memory debugging. Can be deleted in production.
 * from http://www.php.net/manual/en/function.memory-get-usage.php#96280
 * @param $size
 * @return string
 */
function convert($size) {
    $unit=array('','K','M','G','T','P');
    return @round($size / pow(1024, ($i = floor(log($size, 1024)))), 2) . $unit[(int)$i];
}
