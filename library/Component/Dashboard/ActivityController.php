<?php

/**
 *
 * @author      Alex Tworowsky <alex.tworowsky@alaress.com.au>
 * @copyright   2011 Alaress Pty Ltd
 * @link        http://www.alaress.com.au
 * @version     SVN: $Id$
 *
 */

class Component_Dashboard_ActivityController extends Component_Dashboard_ControllerAbstract {

    protected $group;

    protected $activity;

    public function __construct (Component_Instance $instance, \Schoolbox\User $user, SitePage $sitepage, Group $group, Activity $activity) {
        parent::__construct($instance, $user, $sitepage);

        $this->group = $group;
        $this->activity = $activity;
    }

    public function execute () {
        $this->catList = $this->group->involvedInCategoryList($_SESSION['id']);
    }

    public function renderTemplate ($templateHandle) {
        if ( !$this->loadTemplate($templateHandle, 'activity') ) {
            return false;
        }

        $this->setBlock($templateHandle, 'category_block', 'new_category');
        $this->setBlock($templateHandle, 'mail_block', 'new_mail_block');
        $this->setBlock('category_block', 'new_online_assessment', 'online_assessment_block');
        $this->setBlock('category_block', 'reviewed_online_assessment', 'reviewed_block');
        $this->setBlock('category_block', 'due_work', 'due_block');
        $this->setBlock('category_block', 'ret_work', 'ret_block');
        $this->setBlock('category_block', 'sub_work', 'sub_block');
        $this->setBlock('category_block', "new_news", "new_news_item");
        $this->setBlock("category_block", "new_file", "new_file_item");
        $this->setBlock("category_block", "new_url", "new_url_item");
        $this->setBlock("category_block", "new_forum", "new_forum_item");
        $this->setBlock("category_block", "new_wiki", "new_wiki_item");
        $this->setBlock("category_block", "new_blog", "new_blog_item");

        $flag = 0;

        $this->setVar('new_mail_block','');

        //for each subject
        $this->setVar('new_category','');
        if(is_array($this->catList))
        {
            foreach($this->catList as $catInfo)
            {
                $this->setVar("NEW_CATEGORY_ID", $catInfo['id']);

                $sub = $this->activity->submissionDetails($catInfo['id']);

                /* What's New for Online Assessments
                 */
                $this->setVar('online_assessment_block', '');
                if ($sub['onlineAssessments']) {
                    $this->parse('online_assessment_block', 'new_online_assessment');
                }

                $this->setVar('reviewed_block', '');
                if ($sub['reviewedOnlineAssessments']) {
                    $this->parse('reviewed_block', 'reviewed_online_assessment');
                }

                $this->setVar('due_block', '');
                if($sub['duework'])
                    $this->parse('due_block', 'due_work');

                $this->setVar('ret_block', '');
                if($sub['retwork'])
                    $this->parse('ret_block', 'ret_work');

                $this->setVar('sub_block', '');
                if(is_array($sub['subboxes'])) {
                    foreach($sub['subboxes'] as $subid => $subdetails) {
                        $subout = array('BOX_ID'     => $subid,
                                        'BOX_NAME'   => $subdetails['description'],
                                        'BOX_COUNT'  => $subdetails['count'],
                                        'BOX_PLURAL' => ($subdetails['count'] > 1 ? 's' : ''),
                                        );
                        $this->setVar($subout);

                        $this->parse('sub_block', 'sub_work', true);
                    }
                }


                $cat = $this->activity->newCategory($catInfo['id']);

                $this->setVar("new_news_item", "");
                if($cat['news'])
                {
                    if($cat['news'] > 1)
                        $this->setVar('NEW_PLURAL_NEWS', 's');
                    else
                        $this->setVar('NEW_PLURAL_NEWS', '');
                    $this->setVar("NEW_NEWS", $cat['news']);
                    $this->parse("new_news_item", "new_news");
                }

                $this->setVar('new_blog_item', '');
                if($cat['blog'])
                {
                    if($cat['blog'] > 1)
                        $this->setVar('NEW_PLURAL_BLOG', 's');
                    else
                        $this->setVar('NEW_PLURAL_BLOG', '');
                    $this->setVar('NEW_BLOG', $cat['blog']);
                    $this->parse('new_blog_item', 'new_blog');
                }

                $this->setVar("new_file_item", "");
                if($cat['files'])
                {
                    if($cat['files'] > 1)
                        $this->setVar('NEW_PLURAL_FILE', 's');
                    else
                        $this->setVar('NEW_PLURAL_FILE', '');
                    $this->setVar("NEW_FILES", $cat['files']);
                    $this->parse("new_file_item", "new_file");
                }

                $this->setVar("new_url_item", "");
                if($cat['links'])
                {
                    if($cat['links'] > 1)
                        $this->setVar('NEW_PLURAL_LINK', 's');
                    else
                        $this->setVar('NEW_PLURAL_LINK', '');
                    $this->setVar("NEW_URLS", $cat['links']);
                    $this->parse("new_url_item", "new_url");
                }

                $this->setVar("new_forum_item", "");
                if($cat['forum'])
                {
                    if($cat['forum'] > 1)
                        $this->setVar('NEW_PLURAL_FORUM', 's');
                    else
                        $this->setVar('NEW_PLURAL_FORUM', '');
                    $this->setVar("NEW_FORUM", $cat['forum']);
                    $this->parse("new_forum_item", "new_forum");
                }

                $this->setVar("new_wiki_item", "");
                if($cat['wiki'])
                {
                    if($cat['wiki'] > 1)
                        $this->setVar('NEW_PLURAL_WIKI', 's');
                    else
                        $this->setVar('NEW_PLURAL_WIKI', '');
                    $this->setVar("NEW_WIKI", $cat['wiki']);
                    $this->parse("new_wiki_item", "new_wiki");
                }

                if($sub['duework'] || $sub['retwork'] || $sub['subboxes'] || $cat['news'] || $cat['files'] || $cat['links'] || $cat['forum'] || $cat['wiki'])
                {
                    if($catInfo['code'])
                        $this->setVar("NEW_CATEGORY_NAME", $catInfo['name'].' ('.$catInfo['code'].')');
                    else
                        $this->setVar("NEW_CATEGORY_NAME", $catInfo['name']);
                    $this->parse("new_category", "category_block", true);
                    $flag = 1;
                }

            }
        }

        return $flag ? true : false;
    }

}
