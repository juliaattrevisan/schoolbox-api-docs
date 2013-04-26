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
     * @var Component_ControllerAbstract
     */
    protected $upcoming_work_controller;

    /**
     * @var Component_ControllerAbstract
     */
    protected $homepage_activity_controller;

    public function __construct($windowStart, $windowEnd, $debug = false) {

        $this->debug = $debug;

        $this->host = Cfg::get('site_hostname') . '.' . Cfg::get('site_domain');
        //
        // TODO remove debug host
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


        $chf = new Component_Dashboard_Factory(new \User(), new SitePage(false));

        //
        // Setup of components for:
        // Dashboard/Homepage Activity
        // Dashboard/Upcoming Due Work
        $uwc             = new Component_Instance();
        $uwc->definition = Component_DefinitionMapper::getInstance()->findOneByColumn(
            'class', 'Component_Dashboard_UpcomingWorkController'
        );
        $this->upcoming_work_controller = $chf->buildComponent($uwc);


        $hac             = new Component_Instance();
        $hac->definition = Component_DefinitionMapper::getInstance()->findOneByColumn(
            'class', 'Component_Dashboard_HomepageNewsController'
        );
        $this->homepage_activity_controller = $chf->buildComponent($hac);
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
                LEFT JOIN userCampus uc ON uc.uid = u.id
                where u.id IN (15, 229)";

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
            $poster = $article['title'].'. '.$poster;
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
            $poster = $title.'. '.$poster;
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

        if(is_array($newsList)) {
            foreach($newsList as $article) {
                $u['articles']['c'][0][$article['item_id']] = array(
                    'id'       => $article['item_id'],
                    'title'    => $article['item_heading'],
                    'abstract' => $this->scrub($article['item_body']),
                    'topic'    => $article['topicName'],
                    'date'     => strtotime($article['item_datetime_posted']),
                    'poster'   => $this->getPosterFromArticle($article),
                    'image'    => $article['item_image'],
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
                                'poster'   => $this->getPosterFromArticle($art),
                                'image'    => $art['item_image'],
                            );
                        }
                    }
                }
            }
        }


        //
        // Don't try to shart due work into news through the controller, because
        // layout is too far from what would be wanted in the email.
        $user_model = $this->user_mapper->findById($u['id']);
        if ($user_model instanceof User) {
            $categories = array();
            foreach ($this->category_mapper->findForUser($user_model) as $category) {
                $categories[] = $category->getId();
            }

            if (!empty($categories)) {
                $limit      = 15;
                $activities = $this->submission_box_mapper
                    ->getActivities($u['id'], $limit, $categories)
                    ->merge($this->assessment_mapper->getActivities($u['id'], $limit, $categories))
                    ->merge($this->submission_mapper->getActivities($u['id'], $limit, $categories))
                    ->merge($this->return_mapper->getActivities(
                        $u['id'], $limit, $categories,
                        array(SubmissionBox::MARK_VISIBILITY_STUDENT, SubmissionBox::MARK_VISIBILITY_PARENT_STUDENT,)
                    ))
                    ->sortBy('date', 'desc')
                    ->limit(0, $limit);
                foreach ($activities as $activity) {
                    $relative_date = new Alaress_Date_Relative($activity->getDate());
                    //
                    // Filter out items that aren't in window.
                    if ($this->withinWindow($activity->getDate()->getUnixTimestamp())) {
                        $u['activities'][] = array(
                            'object'        => $activity,
                            'poster'        => $this->getPosterFromUser($activity->getOwner()),
                            'event'         => $activity->getEvent(),
                            'category_name' => $activity->getCategory()->getName(),
                            'category_id'   => $activity->getCategory()->getId(),
                            'title'         => $activity->getName(),
                            'type'          => $activity->getTypeName(),
                            'date'          => $relative_date->getRelativeTime(),
                        );
                    }
                }

                //
                // Get upcoming work items here too (as activity list)
                $due_work_list = $this->submission_box_mapper
                    ->getUpcomingActivities($u['id'], $limit, $categories)
                    ->merge($this->assessment_mapper->getUpcomingActivities($u['id'], $limit, $categories))
                    ->sortBy('date', 'asc')
                    ->limit(0, $limit);
                foreach ($due_work_list as $due_work) {
                    $relative_date = new Alaress_Date_Relative($due_work->getDate());
                    $u['due_work'][] = array(
                        'object'        => $due_work,
                        'category_name' => $due_work->getCategory()->getName(),
                        'category_id'   => $due_work->getCategory()->getId(),
                        'title'         => $due_work->getName(),
                        'type'          => $due_work->getTypeName(),
                        'date'          => $relative_date->getRelativeTime(),
                    );
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

        $mailer->setBlock('tplBODY', 'upcominglist', 'upcominglist_block');
        $mailer->setBlock('upcominglist', 'upcoming', 'upcoming_block');

        $mailer->setBlock('tplBODY', 'newslist', 'newslist_block');
        $mailer->setBlock('newslist', 'article', 'article_block');
        $mailer->setBlock('article', 'image', 'image_block');

        $mailer->setBlock('tplBODY', 'activitylist', 'activitylist_block');
        $mailer->setBlock('activitylist', 'activity', 'activity_block');

        $mailer->setBlock('activity', 'activity_new',       'activity_new_block');
        $mailer->setBlock('activity', 'activity_overdue',   'activity_overdue_block');
        $mailer->setBlock('activity', 'activity_submitted', 'activity_submitted_block');
        $mailer->setBlock('activity', 'activity_marked',    'activity_marked_block');
        $mailer->setBlock('activity', 'activity_returned',  'activity_returned_block');
        $mailer->setBlock('activity', 'activity_closed',    'activity_closed_block');

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

                $is_news_shown = false;
                foreach($newsList as $catID => $news) {
                    $i = 0;
                    foreach($news as $article) {
                        if($this->withinWindow($article['date'])) {
                            $is_news_shown = true;
                            $mailer->setVar(array(
                                'ARTICLE_SLUG'       => $slugBase . ($newsType == 'h' ? $catID : $article['id']),
                                'ARTICLE_TITLE'      => $article['title'],
                                'ARTICLE_ABSTRACT'   => $article['abstract'],
                                'ARTICLE_TOPIC'      => $article['topic'],
                                'ARTICLE_DATE'       => date('l, jS \o\f F', $article['date']),
                                'ARTICLE_BACKGROUND' => ++$i % 2 == 1 ? "none" : "#F4F4F8",
                                'ARTICLE_POSTER'     => $article['poster'],
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
                            $newsFlag = true;
                        }
                    }
                }
                if ($is_news_shown) {
                    $mailer->parse('newslist_block', 'newslist', true);
                }
                else {
                    $mailer->setVar('newslist_block', '');
                }
                $mailer->setVar('article_block', '');
            }
        }

        $component_config = array(
            'actionable'    => array('label' => 'actionable',    'value' => true),
            'allow_parents' => array('label' => 'allow_parents', 'value' => true),
            'size'          => array('label' => 'size',          'value' => 0),
            'user'          => array('label' => 'user',          'value' => $u['id']),
        );

        $this->homepage_activity_controller->setup(array(), $component_config);
        $this->homepage_activity_controller->execute();
        var_dump($this->homepage_activity_controller->getDataFromExecute());

        $this->upcoming_work_controller->setup(array(), $component_config);
        $this->upcoming_work_controller->execute();
        var_dump($this->upcoming_work_controller->getDataFromExecute());

        //
        // Upcoming Work component
//        $sitepage = new SitePage(false);
//        $sitepage->setFile(TEMPLATE_body, 'component/bare.html');
//        $chf = new Component_Dashboard_Factory(new \User(), $sitepage);
//        $controller = $chf->buildComponent($this->upcoming_work_component);
//        $controller->setup(array(), $component_config);
//        $controller->execute();
//
//        //
//        // Don't render anything - just give me that juicy data
//        $controller->renderTemplate('COMPONENT');
//
//        ob_start();
//        $sitepage->outputPage(true);
//        $mailer->setVar('upcominglist_block', ob_get_clean());
//        unset($sitepage, $controller);


//        //
//        // Homepage News dashboard component
//        $sitepage = new SitePage(false);
//        $sitepage->setFile(TEMPLATE_body, 'component/bare.html');
//        $chf = new Component_Dashboard_Factory(new \User(), $sitepage);
//        $controller = $chf->buildComponent($this->homepage_activity_component);
//        $controller->setup(array(), $component_config);
//        $controller->execute();
//        $controller->renderTemplate('COMPONENT');
//
//        ob_start();
//        $sitepage->outputPage(true);
//        $mailer->setVar('activitylist_block', ob_get_clean());
//        unset($sitepage, $controller);



//        if (!empty($u['activities'])) {
//            $newsFlag = true;
//            foreach ($u['activities'] as $activity) {
//                $is_assessment = $activity['object']->isAssessment();
//                $activity_id   = $activity['object']->getId();
//                $mailer->setVar('activity_new_block',       '');
//                $mailer->setVar('activity_overdue_block',   '');
//                $mailer->setVar('activity_submitted_block', '');
//                $mailer->setVar('activity_marked_block',    '');
//                $mailer->setVar('activity_returned_block',  '');
//                $mailer->setVar('activity_closed_block',    '');
//                $mailer->setVar(array(
//                    'ACTIVITY_OWNER'    => $activity['poster'],
//                    'ACTIVITY_TYPE'     => strtolower($activity['type']),
//                    'ACTIVITY_CAT_ID'   => $activity['category_id'],
//                    'ACTIVITY_CAT_NAME' => $activity['category_name'],
//                    'ACTIVITY_TITLE'    => $activity['title'],
//                    'ACTIVITY_DATE'     => $activity['date'],
//                    'ACTIVITY_URL'      => $is_assessment ?
//                        ('assess/intro/'.$activity_id) :
//                        ('submission/submissionHistory/'.$activity_id.'/'.$u['id']),
//                ));
//
//                //
//                // What sort of block is this?
//                $block = 'closed';
//                $icon  = 'cross';
//                switch ($activity['event']) {
//                    case 'new':
//                        $block = 'new';
//                        $icon  = 'dropbox';
//                        break;
//
//                    case 'overdue':
//                        $block = 'overdue';
//                        $icon  = 'notice';
//                        break;
//
//                    case 'submitted':
//                        $block = 'submitted';
//                        $icon  = 'upload';
//                        break;
//
//                    case 'returned':
//                        //
//                        // Is there a mark?
//                        $base_mark = $activity['object']->getReturnedNormalizedMark();
//                        if (isset($base_mark)) {
//                            $block = 'marked';
//                            $mailer->setVar('ACTIVITY_MARK', $activity['object']->getReturnedMark());
//                        }
//                        else {
//                            $block = 'returned';
//                        }
//                        //
//                        // Activity comes from submission box's owner, not user.
//                        $mailer->setVar('ACTIVITY_OWNER', $this->getPosterFromUser($activity['object']->getSubmissionBox()->getOwner()));
//                        $icon = 'refresh';
//                        break;
//
//                    case 'closed':
//                    default:
//                        break;
//                }
//                $mailer->setVar('ACTIVITY_ICON', $icon);
//                $mailer->parse("activity_{$block}_block", "activity_{$block}");
//                $mailer->parse('activity_block', 'activity', true);
//            }
//            $mailer->parse('activitylist_block', 'activitylist');
//        }
//        else {
//            $mailer->setVar('activitylist_block', '');
//        }
//
//        if (!empty($u['due_work'])) {
//            $newsFlag = true;
//            foreach ($u['due_work'] as $due_work) {
//                $is_assessment = $due_work['object']->isAssessment();
//                $due_work_id   = $due_work['object']->getId();
//                $mailer->setVar(array(
//                    'UPCOMING_TYPE'        => $due_work['type'],
//                    'UPCOMING_CAT_ID'      => $due_work['category_id'],
//                    'UPCOMING_CAT_NAME'    => $due_work['category_name'],
//                    'UPCOMING_TITLE'       => $due_work['title'],
//                    'UPCOMING_DATE'        => $due_work['date'],
//                    'UPCOMING_URL'         => $is_assessment ?
//                        ('assess/intro/'.$due_work_id) :
//                        ('submission/submissionHistory/'.$due_work_id.'/'.$u['id']),
//                    'UPCOMING_SUBMIT_ICON' => 'submit',
//                    'UPCOMING_LINK_TEXT'   => 'Submit',
//                ));
//                $mailer->parse('upcoming_block', 'upcoming', true);
//            }
//            $mailer->parse('upcominglist_block', 'upcominglist');
//        }
//        else {
//            $mailer->setVar('upcominglist_block', '');
//        }

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

    protected function withinFutureWindow($stamp) {
        return (($stamp >= $this->digestEnd) && ($stamp < ($this->digestEnd + ($this->digestEnd - $this->digestStart))));
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
