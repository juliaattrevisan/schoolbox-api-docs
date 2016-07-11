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
     * Prepares data for the render function. Business logic.
     *
     * @return bool Whether to continue on and attempt Component render
     */
    public function execute()
    {
        // If current user can't view component's source, only display the
        // component if the user has context WRITE+ permission
        if(!$this->isSourceRenderable()){
            return $this->hasContextWriteAccess();
        }

        $this->groupHandler = new Group();
        $this->newsHandler  = new CategoryNews();
        return true;
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

        $this->setBlock($templateHandle, 'source_folder_deleted', 'source_folder_deleted_block');
        $this->setBlock($templateHandle, 'source_folder_exists', 'source_folder_exists_block');
        $this->setBlock('source_folder_exists', 'news', 'news_block');
        $this->setBlock('source_folder_exists', "submit_news", "submit_news_block");
        $this->setBlock('news', "has_publish_date", "has_publish_date_block");
        $this->setBlock('news', 'can_modify_news', 'can_modify_news_block');
        $this->setBlock('news', "compact", "compact_block");
        $this->setBlock('news', "full", "full_block");

        /* Need title injected here
         */
        $this->parseConfig();



        $this->setVar(
            array(
                // These are for the Select Alternate Source Resource Browser
                'COMPONENT_CONFIG_SRC_ID'   => $this->getSource()->getId(),
                'COMPONENT_CONFIG_SRC_NAME' => $this->getSource()->getId() == $this->getContext()->getId()
                    ? 'Local'
                    : $this->getSource()->getName(),
                'NEWS_CATEGORY_ID'          => $this->getSource()->getId(),
            )
        );

        if(!$this->isSourceRenderable()) {
            return $this->renderSourceUnavailable();
        } else {
            $this->setVar('source_folder_deleted_block', '');

            /* Set up component name
            */
            $this->setComponentName(strlen(trim($this->getConfig('componentname'))) ? $this->getConfig('componentname') : $this->getComponentName());

            /* Prevent multiple news from other categories stacking up in the wrong place
             * and initialise empty news roll by default
             */
            $this->setVar(array(
                'news_block' => '',
                'submit_news_block' => '',
                'can_modify_news_block' => '',
            ));

            $newsPermission = $this->contentPermission;

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
                            "NEWS_ID" => $news['id'],
                            "NEWS_HEADING" => Scrub::text($news['heading']),
                            "NEWS_BODY" => Scrub::html($news['body']),
                            "EMAIL_CONTACT" => "<a href=\"/search/user/{$news['uid']}\">"
                                . $userHandler->formatName($news)
                                . "</a>",
                            "AUTHOR" => $userHandler->formatName($news),
                            "AUTHOR_ID" => $news['uid'],
                            "AUTHOR_EXTERNAL_ID" => $news['synid'],
                            "POSTED_DATE" => $postedTime->getRelativeTime(),
                            'ON_OFF' => ++$j % 2 ? '' : 'dark'
                        )
                    );

                    // news_edit_other allows users with write access to edit other's news
                    $canModify = $news['owner'] == $_SESSION['id']
                        || $this->contentPermission >= FULL_ACCESS
                        || ($this->contentPermission >= WRITE_ACCESS && Config::get('news_edit_other'));

                    if ($canModify) {
                        $this->parse('can_modify_news_block', 'can_modify_news');
                    } else {
                        $this->setVar(array(
                            'can_modify_news_block' => ''
                        ));
                    }

                    /* Anyone with write access can now see the publish date for news items
                     */
                    if ($this->contentPermission >= WRITE_ACCESS) {
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

                    if ($this->getConfig('compactMode') == '1') {
                        // Compact mode
                        $this->parse('compact_block', 'compact');
                        $this->setVar('full_block', '');
                    } else {
                        // Full mode
                        $this->setVar('compact_block', '');
                        $this->parse('full_block', 'full');
                    }

                    $this->parse("news_block", "news", true);

                    /* Clear children after parsing out the parent
                     */
                    $this->setVar(array(
                        'can_modify_news_block' => ''
                    ));
                }
            }
            $this->parse('source_folder_exists_block','source_folder_exists');

        }

        return true;
    }
}
