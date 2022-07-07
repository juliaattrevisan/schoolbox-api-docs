<?php

/**
 *
 * @author      Alex Tworowsky <alex.tworowsky@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 *
 */

class Component_Dashboard_ActivityController extends Component_Dashboard_ControllerAbstract
{
    protected $group;

    protected $activity;

    /** @var  array */
    private $catList;

    public function __construct(Component_Instance $instance, \Schoolbox\User $user, SitePage $sitepage, Group $group, Activity $activity)
    {
        parent::__construct($instance, $user, $sitepage);

        $this->group = $group;
        $this->activity = $activity;
    }

    /**
     * Prepares data for the render function. Business logic.
     *
     * @return bool Whether to continue on and attempt Component render
     */
    public function execute()
    {
        $this->catList = $this->group->involvedInCategoryList($_SESSION['id']);
        return true;
    }

    public function renderTemplate($templateHandle)
    {
        if (!$this->loadTemplate($templateHandle, 'activity')) {
            return false;
        }

        $this->setBlock($templateHandle, 'category_block', 'new_category');
        $this->setBlock('category_block', "new_news", "new_news_item");
        $this->setBlock("category_block", "new_file", "new_file_item");
        $this->setBlock("category_block", "new_url", "new_url_item");
        $this->setBlock("category_block", "new_forum", "new_forum_item");
        $this->setBlock("category_block", "new_wiki", "new_wiki_item");
        $this->setBlock("category_block", "new_blog", "new_blog_item");

        $flag = 0;

        //for each subject
        $this->setVar('new_category', '');
        if (is_array($this->catList)) {
            foreach ($this->catList as $catInfo) {
                $this->setVar("NEW_CATEGORY_ID", $catInfo['id']);

                $cat = $this->activity->newCategory($catInfo['id']);

                $this->setVar("new_news_item", "");
                if ($cat['news']) {
                    if ($cat['news'] > 1) {
                        $this->setVar('NEW_PLURAL_NEWS', 's');
                    } else {
                        $this->setVar('NEW_PLURAL_NEWS', '');
                    }
                    $this->setVar("NEW_NEWS", $cat['news']);
                    $this->parse("new_news_item", "new_news");
                }

                $this->setVar('new_blog_item', '');
                if ($cat['blog']) {
                    if ($cat['blog'] > 1) {
                        $this->setVar('NEW_PLURAL_BLOG', 's');
                    } else {
                        $this->setVar('NEW_PLURAL_BLOG', '');
                    }
                    $this->setVar('NEW_BLOG', $cat['blog']);
                    $this->parse('new_blog_item', 'new_blog');
                }

                $this->setVar("new_file_item", "");
                if ($cat['files']) {
                    if ($cat['files'] > 1) {
                        $this->setVar('NEW_PLURAL_FILE', 's');
                    } else {
                        $this->setVar('NEW_PLURAL_FILE', '');
                    }
                    $this->setVar("NEW_FILES", $cat['files']);
                    $this->parse("new_file_item", "new_file");
                }

                $this->setVar("new_url_item", "");
                if ($cat['links']) {
                    if ($cat['links'] > 1) {
                        $this->setVar('NEW_PLURAL_LINK', 's');
                    } else {
                        $this->setVar('NEW_PLURAL_LINK', '');
                    }
                    $this->setVar("NEW_URLS", $cat['links']);
                    $this->parse("new_url_item", "new_url");
                }

                $this->setVar("new_forum_item", "");
                if ($cat['forum']) {
                    if ($cat['forum'] > 1) {
                        $this->setVar('NEW_PLURAL_FORUM', 's');
                    } else {
                        $this->setVar('NEW_PLURAL_FORUM', '');
                    }
                    $this->setVar("NEW_FORUM", $cat['forum']);
                    $this->parse("new_forum_item", "new_forum");
                }

                $this->setVar("new_wiki_item", "");
                if ($cat['wiki']) {
                    if ($cat['wiki'] > 1) {
                        $this->setVar('NEW_PLURAL_WIKI', 's');
                    } else {
                        $this->setVar('NEW_PLURAL_WIKI', '');
                    }
                    $this->setVar("NEW_WIKI", $cat['wiki']);
                    $this->parse("new_wiki_item", "new_wiki");
                }

                if ($cat['news'] || $cat['files'] || $cat['links'] || $cat['forum'] || $cat['wiki']) {
                    $this->setVar("NEW_CATEGORY_NAME", $catInfo['name']);
                    $this->parse("new_category", "category_block", true);
                    $flag = 1;
                }
            }
        }

        return $flag ? true : false;
    }
}
