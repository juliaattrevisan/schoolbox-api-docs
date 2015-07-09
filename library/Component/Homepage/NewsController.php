<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class Component_Homepage_NewsController extends Component_Homepage_ControllerAbstract
{

    /**
     * @var Group
     */
    private $groupHandler;

    /**
     * @var CategoryNews
     */
    private $newsHandler;

    /**
     * An instance of Category
     *
     * @var \Schoolbox\Resource\Category
     */
    private $source;

    /**
     * Category Mapper
     *
     * @var \Schoolbox\Resource\CategoryMapper
     */
    private $categoryMapper = null;

    /**
     * Prepares data for the render function. Business logic.
     *
     * @return void
     */
    public function execute()
    {
        $this->groupHandler = new Group();
        $this->newsHandler  = new CategoryNews();
        $this->setCategoryMapper(new \Schoolbox\Resource\CategoryMapper());

        $this->source = $this->getContext();

        if (is_numeric($this->getConfig('src'))) {
            try {
                // remove any non-numeric values from the source config
                $this->source = $this->getCategoryMapper()->findById(preg_replace('#[/a-zA-Z]#', '', $this->getConfig('src')));
            } catch (\Exception $e) {
            }
        }
    }

    /**
     * Renders the data into the SitePage. Presentation logic.
     * @param String $templateHandle
     * @return boolean
     */
    public function renderTemplate($templateHandle)
    {
        if (!parent::loadTemplate($templateHandle, 'news')) {
            return false;
        }

        if ($this->permission < READ_ACCESS) {
            return false;
        }

        $this->setBlock($templateHandle, 'news', 'news_block');
        $this->setBlock($templateHandle, "submit_news", "submit_news_block");
        $this->setBlock('news', "has_publish_date", "has_publish_date_block");
        $this->setBlock('news', 'can_modify_news', 'can_modify_news_block');

        /* Set up component name
         */
        $this->setComponentName(strlen(trim($this->getConfig('componentname'))) ? $this->getConfig('componentname') : $this->getComponentName());

        $this->setVar(
            array(
                // These are for the Select Alternate Source resourcebrowser
                'COMPONENT_CONFIG_SRC_ID'   => $this->source->getId(),
                'COMPONENT_CONFIG_SRC_NAME' => $this->source->getId() == $this->getContext()->getId() ? 'Local' : $this->source->getName(),
                'NEWS_CATEGORY_ID'          => $this->source->getId(),
            )
        );

        /* Prevent multiple news from other categories stacking up in the wrong place
         * and initialise empty news roll by default
         */
        $this->setVar(array(
            'news_block' => '',
            'submit_news_block' => '',
            'can_modify_news_block' => '',
        ));

        $newsPermission = $this->permission;

        switch ($newsPermission) {
            case FULL_ACCESS:
            case WRITE_ACCESS:
                $this->parse("submit_news_block", "submit_news");
                // no break, intentionally carry through

            case READ_ACCESS:
                $newsList = $this->newsHandler->getAllForCategory(
                    $this->source->getId(),
                    array('size' => $this->getConfig('size'))
                );
                break;

            default: // NO ACCESS
                $newsList = null;
                break;
        }

        // Got news? Yes? Go.
        if (is_array($newsList)) {
            $userHandler = new User();

            $j = 0;
            foreach ($newsList as $news) {
                // Student view: don't show unpublished even if I created it
                if ($newsPermission < WRITE_ACCESS && $news['published'] > $_SERVER['REQUEST_TIME']) {
                    continue;
                }

                $postedTime = new Alaress_Date_Format($news['posted'], 'U');

                $this->setVar(
                    array(
                        "NEWS_ID"            => $news['id'],
                        "NEWS_HEADING"       => Scrub::text($news['heading']),
                        "NEWS_BODY"          => Scrub::html($news['body']),
                        "EMAIL_CONTACT"      => "<a href=\"/search/user/{$news['uid']}\">"
                            . $userHandler->formatName($news)
                            . "</a>",
                        "AUTHOR"             => $userHandler->formatName($news),
                        "AUTHOR_ID"          => $news['uid'],
                        "AUTHOR_EXTERNAL_ID" => $news['synid'],
                        "POSTED_DATE"        => $postedTime->getRelativeTime(),
                        'ON_OFF'             => ++$j % 2 ? '' : 'dark'
                    )
                );

                // news_edit_other allows users with write access to edit other's news
                $canModify = $news['owner'] == $_SESSION['id']
                    || $this->permission >= FULL_ACCESS
                    || ($this->permission >= WRITE_ACCESS && Config::get('news_edit_other'));

                if ($canModify) {
                    $this->parse('can_modify_news_block', 'can_modify_news');
                }
                else {
                    $this->setVar(array(
                        'can_modify_news_block' => ''
                    ));
                }

                /* Anyone with write access can now see the publish date for news items
		         */
                if ($this->permission >= WRITE_ACCESS) {
                    $publishDate = new Alaress_Date_Format($news['published'], 'U');

                    if (strlen($news['published']) && $news['published'] > time()) {
                        $this->setVar(
                            array(
                                'NEWS_PUBLISH_DATE' => $publishDate->getDateOnly()
                            )
                        );

                        $this->parse('has_publish_date_block', 'has_publish_date');
                    } else {
                        $this->setVar('has_publish_date_block', '');
                    }

                } else {
                    $this->setVar('has_publish_date_block', '');
                }

                $this->parse("news_block", "news", true);

                /* Clear children after parsing out the parent
                 */
                $this->setVar(array(
                    'can_modify_news_block' => ''
                ));
            }
        }

        /* Need title injected here
         */
        $this->parseConfig();

        return true;
    }

    /* Getters/Setters
     */

    /**
     * Set category mapper
     *
     * @param null $categoryMapper
     */
    private function setCategoryMapper($categoryMapper)
    {
        $this->categoryMapper = $categoryMapper;
    }

    /**
     * Get category mapper
     *
     * @return null
     */
    private function getCategoryMapper()
    {
        return $this->categoryMapper;
    }
}
