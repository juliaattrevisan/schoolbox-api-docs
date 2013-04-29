<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

use Schoolbox\User;
use Schoolbox\UserMapper;
use Schoolbox\Resource\CategoryMapper;
use Schoolbox\Assessment\Component\SubmissionBox;
use Schoolbox\Assessment\Component\SubmissionBoxMapper;
use Schoolbox\Assessment\Component\OnlineAssessmentMapper;
use Schoolbox\Assessment\Component\SubmissionBox\SubmissionMapper;
use Schoolbox\Assessment\Component\SubmissionBox\ReturnedWorkMapper;

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

    protected $user_mapper;
    protected $category_mapper;
    protected $submission_box_mapper;
    protected $assessment_mapper;
    protected $submission_mapper;
    protected $return_mapper;

    /**
     * @var Component_Instance
     */
    protected $upcoming_work_component;

    /**
     * @var Component_Instance
     */
    protected $homepage_activity_component;

    public function __construct($windowStart, $windowEnd, $debug = false) {

        $this->debug = $debug;

        $this->host = Cfg::get('site_hostname') . '.' . Cfg::get('site_domain');

        $this->cli('Link URI base is ' . $this->host);

        $this->catHandler = new Category();
        $this->newsHandler = new News();
        $this->groupHandler = new Group();
        $this->catnewsHandler = new CategoryNews();
        $this->emailHandler = new Email();

        $this->digestStart = $windowStart;
        $this->digestEnd = $windowEnd;


        $this->textFormatter = new TextFormatter();

        $this->user_mapper           = new UserMapper();
        $this->category_mapper       = new CategoryMapper();
        $this->submission_box_mapper = new SubmissionBoxMapper();
        $this->assessment_mapper     = new OnlineAssessmentMapper();
        $this->submission_mapper     = new SubmissionMapper();
        $this->return_mapper         = new ReturnedWorkMapper();

        //
        // Set up category mapper with external user object for querying
        // external DB.
        $this->category_mapper->setExternalUser(new External_User());

        //
        // Setup of components for:
        // Dashboard/Homepage Activity
        // Dashboard/Upcoming Due Work
        $this->upcoming_work_component             = new Component_Instance();
        $this->upcoming_work_component->definition = Component_DefinitionMapper::getInstance()->findOneByColumn(
            'class', 'Component_Dashboard_UpcomingWorkController'
        );


        $this->homepage_activity_component             = new Component_Instance();
        $this->homepage_activity_component->definition = Component_DefinitionMapper::getInstance()->findOneByColumn(
            'class', 'Component_Dashboard_HomepageNewsController'
        );
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
                INNER JOIN userCampus uc ON uc.uid = u.id";

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

        //
        // Compile category list for users
        foreach ($userList as &$user) {
            $user['user_object']   = $this->user_mapper->findById($user['id']);
            $user['category_list'] = $this->category_mapper->findForUser($user['user_object']);
        }
        unset($user);

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
     * Retrieves poster's name from article - used by compileNews() to generate
     * article for digest.
     *
     * @param $article
     *
     * @return string
     */
    protected function getPosterFromArticle($article) {
        $poster = sprintf('%s %s', $article['firstname'],  $article['lastname']);
        if ($article['title']) {
            $poster = $article['title'].' '.$poster;
        }
        return $poster;
    }


    /**
     * Retrieves poster's name from user object - used by compileNews() to
     * generate activity list for digest.
     *
     * @param User $user
     *
     * @return string
     */
    protected function getPosterFromUser($user) {
        $poster = $user->getFirstName().' '.$user->getLastName();
        if ($title = $user->getTitle()) {
            $poster = $title.' '.$poster;
        }
        return $poster;
    }


    /**
     * Take the user array by value and populate its news fields
     * @param $u
     * @return array
     */
    protected function compileNews($u) {
        $newsList = $this->newsHandler->getFilteredNews($u['id'], $u['campus'], $u['gid']);

        $u['articles'] = array('c' => array(), 'h' => array());
        if(is_array($newsList)) {
            foreach($newsList as $article) {
                $u['articles']['c'][$article['item_id']] = array(
                    'id'        => $article['item_id'],
                    'title'     => $article['item_heading'],
                    'abstract'  => $this->scrub($article['item_body']),
                    'topic'     => $article['topicName'],
                    'topic_url' => 'news/'.strtolower(urlencode($article['topicName'])),
                    'date'      => strtotime($article['item_datetime_posted']),
                    'poster'    => $this->getPosterFromArticle($article),
                    'cat_id'    => 0,
                    'poster_id' => $article['uid'],
                    'image'     => $article['item_image'],
                    'slug'      => "news/item/".$article['item_id'],
                );
            }
        }

        //
        // Use category list (from CategoryMapper->findForUser()) instead of
        // access list, as it's more complete.
        foreach ($u['category_list'] as $category) {
            $cat_id = $category->getId();
            $hNews = $this->catnewsHandler->getAllForCategory($cat_id, false, false, $u['id']);
            if($hNews) {
                foreach($hNews as $art) {
                    $u['articles']['h'][$art['id']] = array(
                        'id'        => $art['id'],
                        'title'     => $art['heading'],
                        'abstract'  => $this->scrub($art['body']),
                        'topic'     => $category->getName(),
                        'topic_url' => "homepage/".$cat_id,
                        'date'      => $art['posted'],
                        'poster'    => $this->getPosterFromArticle($art),
                        'cat_id'    => $cat_id,
                        'poster_id' => $art['uid'],
                        'image'     => $art['item_image'],
                        'slug'      => "homepage/".$cat_id,
                    );
                }
            }
        }

        //
        // Sort each type of news - most recent first, then by name.
        foreach ($u['articles'] as &$articles) {
            usort($articles, function($a, $b) {
                if ($a['date'] != $b['date']) {
                    return $a['date'] < $b['date'] ? 1 : -1;
                }
                return strcmp($a['title'], $b['title']);
            });
        }
        unset($articles);
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

        $mailer->setBlock('tplBODY', 'upcominglist', 'upcominglist_block');
        $mailer->setBlock('upcominglist', 'upcoming', 'upcoming_block');
        $mailer->setBlock('upcoming', 'upcomingaction', 'upcomingaction_block');

        $mailer->setBlock('tplBODY', 'newslist', 'newslist_block');
        $mailer->setBlock('newslist', 'article', 'article_block');
        $mailer->setBlock('article', 'image', 'image_block');

        $mailer->setBlock('tplBODY', 'activitylist', 'activitylist_block');
        $mailer->setBlock('activitylist', 'activity', 'activity_block');
        $mailer->setBlock('activity', 'activity_link', 'activity_link_block');
        $mailer->setBlock('activity', 'activity_nolink', 'activity_nolink_block');

        $mailer->setVar('NAME', $u['firstname']);

        $this->cli(sprintf("Email to %s %s (%s)", $u['firstname'], $u['lastname'], $u['email']));
        $newsFlag = false;

        $mailer->setVar('newslist_block', '');
        if (is_array($u['articles'])) {
            foreach($u['articles'] as $newsType => $newsList) {
                $i = 0;
                $mailer->setVar('article_block', '');
                switch($newsType) {
                    case 'c':
                        $mailer->setVar('NEWS_TYPE', 'School News');
                        break;
                    case 'h':
                        $mailer->setVar('NEWS_TYPE', 'From your homepages');
                        break;
                    default:
                        $mailer->setVar('NEWS_TYPE', '');
                        break;
                }

                $is_news_shown = false;
                foreach ($newsList as $article) {
                    if ($this->withinWindow($article['date'])) {
                        $is_news_shown = true;
                        $mailer->setVar(array(
                            'ARTICLE_SLUG'       => $article['slug'],
                            'ARTICLE_TITLE'      => $article['title'],
                            'ARTICLE_ABSTRACT'   => $article['abstract'],
                            'ARTICLE_TOPIC'      => $article['topic'],
                            'ARTICLE_DATE'       => date('l, jS \o\f F', $article['date']),
                            'ARTICLE_BACKGROUND' => ++$i % 2 == 1 ? "none" : "#F4F4F8",
                            'ARTICLE_POSTER'     => $article['poster'],
                            'ARTICLE_POSTER_ID'  => $article['poster_id'],
                            'ARTICLE_CAT_ID'     => $article['cat_id'],
                            'ARTICLE_TOPIC_URL'  => $article['topic_url'],
                            'ARTICLE_IMAGE'      => $article['image'],
                            'ARTICLE_COLSPAN'    => $article['image'] ? 1 : 2,
                        ));
                        if ($article['image']) {
                            $mailer->parse('image_block', 'image');
                        }
                        else {
                            $mailer->setVar('image_block', '');
                        }

                        $mailer->parse('article_block', 'article', true);
                    }
                }
                if ($is_news_shown) {
                    $newsFlag = true;
                    $mailer->parse('newslist_block', 'newslist', true);
                }
            }
        }

        $component_config = array(
            'actionable'    => array('label' => 'actionable',    'value' => true),
            'allow_parents' => array('label' => 'allow_parents', 'value' => true),
            'size'          => array('label' => 'size',          'value' => 10),
            'user'          => array('label' => 'user',          'value' => $u['id']),
        );


        //
        // Give dashboard component factory a user and sitepage to shut it up
        $chf = new Component_Dashboard_Factory(new \User(), new SitePage(false));

        $homepage_activity_controller = $chf->buildComponent($this->homepage_activity_component);
        $homepage_activity_controller->setup(array(), $component_config);
        $homepage_activity_controller->execute();

        //
        // Filter out homepage activities that aren't within the date window.
        $activity_list = array();
        foreach ($homepage_activity_controller->getDataFromExecute() as $activity) {
            if ($this->withinWindow($activity['timestamp'])) {
                $activity_list[] = $activity;
            }
        }

        if (!empty($activity_list)) {
            $i = 0;
            foreach ($activity_list as $activity) {
                $newsFlag = true;
                $mailer->setVar(array(
                    'ACTIVITY_OWNER'      => $activity['avatar_title'],
                    'ACTIVITY_CONTENT'    => $activity['content'],
                    'ACTIVITY_DATE'       => $activity['time'],
                    'ACTIVITY_NAME'       => Scrub::text($activity['name']),
                    'ACTIVITY_URL'        => $activity['link'],
                    'ACTIVITY_USER_ID'    => $activity['user_id'],
                    'ACTIVITY_CAT_ID'     => $activity['cat_id'],
                    'ACTIVITY_CAT_NAME'   => Scrub::text($activity['cat_name']),
                    'ACTIVITY_ACTION'     => Scrub::text($activity['action']),
                    'ACTIVITY_BACKGROUND' => ++$i % 2 == 1 ? "none" : "#F4F4F8",
                ));

                //
                // What sort of block is this?
                $icon = $activity['icon'];
                switch ($icon) {
                    case 'news':
                        $icon = 'lightning';
                        break;

                    case 'onlineassessment':
                        $icon = 'grade';
                        break;

                    case 'forum':
                        $icon = 'discuss';
                        break;

                    case 'survey':
                        $icon = 'poll';
                        break;

                    default:
                        break;
                }
                $mailer->setVar('ACTIVITY_ICON', $icon);
                $mailer->setVar('activity_link_block', '');
                $mailer->setVar('activity_nolink_block', '');
                if ($activity['link']) {
                    $mailer->parse('activity_link_block', 'activity_link');
                }
                else {
                    $mailer->parse('activity_nolink_block', 'activity_nolink');
                }
                $mailer->parse('activity_block', 'activity', true);
            }
            $mailer->parse('activitylist_block', 'activitylist');
        }
        else {
            $mailer->setVar('activitylist_block', '');
        }

        $upcoming_controller = $chf->buildComponent($this->upcoming_work_component);
        $upcoming_controller->setup(array(), $component_config);
        $upcoming_controller->execute();
        $upcoming_list = $upcoming_controller->getDataFromExecute();
        if (!empty($upcoming_list)) {
            $i = 0;
            foreach ($upcoming_list as $upcoming) {
                $newsFlag = true;
                switch ($upcoming['UPCOMING_BTN_ICON']) {
                    case 'modify':
                        $upcoming['UPCOMING_BTN_ICON'] = 'sit';
                        break;
                    case 'up-arrow':
                        $upcoming['UPCOMING_BTN_ICON'] = 'submit';
                        break;
                }
                $color = 'inherit';
                switch ($upcoming['UPCOMING_URGENCY']) {
                    case 10:
                        $color = "#1fbb00";
                        break;
                    case 9:
                        $color = "#3ebb00";
                        break;
                    case 8:
                        $color = "#5ebb00";
                        break;
                    case 7:
                        $color = "#7dbb00";
                        break;
                    case 6:
                        $color = "#9cbb00";
                        break;
                    case 5:
                        $color = "#bb0";
                        break;
                    case 4:
                        $color = "#bb9c00";
                        break;
                    case 3:
                        $color = "#bb7d00";
                        break;
                    case 2:
                        $color = "#bb5d00";
                        break;
                    case 1:
                        $color = "#bb3e00";
                        break;
                    case 0:
                        $color = "#bb1f00";
                        break;
                }
                $upcoming['UPCOMING_URGENCY_COLOR'] = $color;
                $upcoming['UPCOMING_BACKGROUND']    = ++$i % 2 == 1 ? "none" : "#F4F4F8";
                $mailer->setVar($upcoming);
                if (!isset($upcoming['upcoming_work_actionable_block'])) {
                    $mailer->parse('upcomingaction_block', 'upcomingaction');
                }
                $mailer->parse('upcoming_block', 'upcoming', true);
            }
            $mailer->parse('upcominglist_block', 'upcominglist');
        }
        else {
            $mailer->setVar('upcominglist_block', '');
        }

        if (!$this->debugging() && $newsFlag) {
            $success = $mailer->send();
            if(!$success) {
                $this->cli("Failed to send email to {$u['email']}", true);
            }
            return $success;
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
