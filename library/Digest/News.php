<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class Digest_News {

    protected $host;

    protected $catHandler;
    protected $newsHandler;
    protected $groupHandler;
    protected $catnewsHandler;
    protected $emailHandler;

    protected $digestStart;
    protected $digestEnd;

    protected $userList;

    protected $debug;

    protected $textFormatter;

    public function __construct($windowStart, $windowEnd, $debug = false) {

        $this->debug = $debug;

        $this->host = Cfg::get('site_hostname') . '.' . Cfg::get('site_domain');
	    $this->host = '192.168.1.179';

        $this->cli('Link URI base is ' . $this->host);

        $this->catHandler = new Category();
        $this->newsHandler = new News();
        $this->groupHandler = new Group();
        $this->catnewsHandler = new CategoryNews();
        $this->emailHandler = new Email();

        $this->digestStart = $windowStart;
        $this->digestEnd = $windowEnd;


        $this->textFormatter = new TextFormatter();
    }

    public function __destruct() {
        unset($this->userList);
        unset($this->catHandler);
        unset($this->newsHandler);
        unset($this->groupHandler);
        unset($this->catnewsHandler);
        unset($this->textFormatter);
    }

    public function compileUserList() {
        $sql = "SELECT u.id, u.username, u.firstname, u.lastname, u.group_id gid, u.local_email, u.alt_email, uc.campus
                FROM `user` u
                LEFT JOIN userCampus uc ON uc.uid = u.id";

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
                    'email'     => $this->emailHandler->formatEmailFromArray($user),
                    'campus'    => array(),
                );
                $prevUID = $user['id'];
            }
            $userList[$user['id']]['campus'][] = $user['campus'];
        }
        $this->userList = $userList;
    }

    /**
     *
     */
    public function send() {
        $keys = array_keys($this->userList);

        $this->cli(sprintf("Memory limit: %s", ini_get('memory_limit')));

        for($i = 0; $i < count($keys); $i++) {
            $this->cli(sprintf("Memory usage at %03d: %s (%s)", $i, $this->convert(memory_get_usage(true)), memory_get_usage(true)));
            $this->sendEmail($this->compileNews($this->userList[$keys[$i]]));
        }
    }


    /**
     * Take the user array by value and populate its news fields
     * @param $u
     * @return array
     */
    protected function compileNews($u) {
        $newsList = $this->newsHandler->getFilteredNews($u['id'], $u['campus'], $u['gid']);

        if(is_array($newsList)) {
            foreach($newsList as $article) {
                $u['articles']['c'][0][$article['item_id']] = array(
                    'id'       => $article['item_id'],
                    'title'    => $article['item_heading'],
                    'abstract' => $this->scrub($article['item_body']),
                    'topic'    => $article['topicName'],
                    'date'     => strtotime($article['item_datetime_posted']),
                );
            }

            $access = $this->groupHandler->getAllUserAccess($u['id']);

            if($access) {
                foreach($access as $a) {
                    if($a['name'] != 'Homepage') {
                        continue;
                    }
                    $cat = $this->catHandler->getByID($a['sid']);
                    $hNews = $this->catnewsHandler->getAllForCategory($a['sid'], false, false, $u['id']);
                    if($hNews) {
                        foreach($hNews as $art) {
                            $u['articles']['h'][$a['sid']][$art['id']] = array(
                                'id'       => $art['id'],
                                'title'    => $art['heading'],
                                'abstract' => $this->scrub($art['body']),
                                'topic'    => $cat['name'],
                                'date'     => $art['posted'],
                            );
                        }
                    }
                }
            }
        }

        return $u;
    }


    /**
     *
     * @param $u
     * @return boolean
     */
    protected function sendEmail($u) {

        // do nothing if there's no email address
        if(!trim($u['email'])) {
            return false;
        }

        $mailer = new Mailer('news-digest.html', 'News', $u['email'], $this->host);
        $mailer->setBlock('tplBODY', 'newslist', 'newslist_block');
        $mailer->setBlock('newslist', 'article', 'article_block');
        $mailer->setVar('NAME', $u['firstname']);

        $this->cli(sprintf("Email to %s %s (%s)", $u['firstname'], $u['lastname'], $u['email']));
        $newsFlag = false;

        if(is_array($u['articles'])) {
            foreach($u['articles'] as $newsType => $newsList) {

                switch($newsType) {
                    case 'c':
                        $mailer->setVar('NEWS_TYPE', 'School News');
                        $slugBase = "news/item/";
                        break;
                    case 'h':
                        $mailer->setVar('NEWS_TYPE', 'From your homepages');
                        $slugBase = "homepage/";
                        break;
                    default:
                        $mailer->setVar('NEWS_TYPE', '');
                        $slugBase = "";
                        break;
                }

                foreach($newsList as $catID => $news) {
                    $i = 0;
                    foreach($news as $article) {
                        if($this->withinWindow($article['date'])) {
                            $mailer->setVar(array(
                                'ARTICLE_SLUG'       => $slugBase . ($newsType == 'h' ? $catID : $article['id']),
                                'ARTICLE_TITLE'      => $article['title'],
                                'ARTICLE_ABSTRACT'   => $article['abstract'],
                                'ARTICLE_TOPIC'      => $article['topic'],
                                'ARTICLE_DATE'       => date('l, jS \o\f F', $article['date']),
                                'ARTICLE_BACKGROUND' => ++$i % 2 == 1 ? "none" : "#F4F4F8"
                            ));

                            $mailer->parse('article_block', 'article', true);
                            $newsFlag = true;
                        }
                    }
                }
                $mailer->parse('newslist_block', 'newslist', true);
                $mailer->setVar('article_block', '');
            }

            if(!$this->debugging() && $newsFlag) {
                $success = $mailer->send();
                if(!$success) {
                    $this->cli("Failed to send email to {$u['email']}", true);
                }
                return $success;
            }
        }

        return true;
    }

    protected function debugging() {
        return $this->debug;
    }

    protected function withinWindow($stamp) {
        return (($stamp > $this->digestStart) && ($stamp < $this->digestEnd));
    }

    protected function cli($output, $err = false) {
        if($this->debugging() || $err) {
            printf("[%s] %s\n", $err ? '!' : ' ', $output);
        }
    }

    /**
     * Useful for the memory debugging. Can be deleted in production.
     * from http://www.php.net/manual/en/function.memory-get-usage.php#96280
     * @param $size
     * @return string
     */
    protected function convert($size) {
        $unit=array('','K','M','G','T','P');
        return @round($size / pow(1024, ($i = floor(log($size, 1024)))), 2) . $unit[(int)$i];
    }

    protected function scrub($html) {
        return $this->textFormatter->cutSentence(Scrub::html2text($html), 300);
    }

}
