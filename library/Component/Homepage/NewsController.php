<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class Component_Homepage_NewsController extends Component_Homepage_ControllerAbstract {

    /**
     * @var Group
     */
    private $groupHandler;

    /**
     * @var Category
     */
    private $catHandler;

    /**
     * @var CategoryNews
     */
    private $newsHandler;

    /**
     * @var array
     */
    private $source;

    /**
     * Prepares data for the render function. Business logic.
     *
     * @return void
     */
    public function execute() {
        $this->groupHandler = new Group();
        $this->newsHandler = new CategoryNews();
        $this->catHandler = new Category();

        // remove any non-numeric values from the source config
        $this->source = $this->catHandler->getByID(
            $this->getConfig('src') ?
                preg_replace('#[/a-zA-Z]#', '', $this->getConfig('src')) :
                $this->getContext('id')
        );

	    if($this->source === false) {
		    $this->source = $this->catHandler->getByID($this->getContext('id'));
	    }

	    if($this->source === false) {
		    $this->source = $this->catHandler->getByID($this->getContext('id'));
	    }
    }

    /**
     * Renders the data into the SitePage. Presentation logic.
     * @param String $templateHandle
     * @return boolean
     */
    public function renderTemplate($templateHandle) {
        if (!parent::loadTemplate($templateHandle, 'news')) {
            return false;
        }

        $this->parseConfig();

	    if($this->groupHandler->getPermission($this->source['id']) < READ_ACCESS) {
		    return false;
	    }

	    $this->setBlock($templateHandle, 'news', 'news_block');
        $this->setBlock($templateHandle, "submit_news", "submit_news_block");
        $this->setBlock('news', "has_publish_date", "has_publish_date_block");


        /* Set up component name
         */
        $this->setVar(
            array(
                'COMPONENT_NAME' => strlen(trim($this->getConfig('componentname'))) ? $this->getConfig(
                    'componentname'
                ) : 'News',
                // These are for the Select Alternate Source resourcebrowser
                'COMPONENT_CONFIG_SRC_ID' => $this->source['id'],
                'COMPONENT_CONFIG_SRC_NAME' => $this->source['id'] == $this->getContext(
                    'id'
                ) ? 'Local' : $this->source['name'],
                'NEWS_CATEGORY_ID' => $this->source['id'],
            )
        );

        /* Prevent multiple news from other categories stacking up in the wrong place
         * and initialise empty news roll by default
         */
        $this->setVar('news_block', '');
        $this->setVar('submit_news_block', '');

        $newsPermission = $this->groupHandler->getPermission($this->source['id']);

        switch ($newsPermission) {

            case FULL_ACCESS:
            case WRITE_ACCESS:
                $this->parse("submit_news_block", "submit_news");
            // no break, intentionally carry through

            case READ_ACCESS:
                $newsList = $this->newsHandler->getAllForCategory(
                    $this->source['id'], array('size' => $this->getConfig('size'))
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
                if ($news['owner'] == $_SESSION['id'] || $this->permission >= FULL_ACCESS || (NEWS_teacher_edit_other && $this->permission >= WRITE_ACCESS)) {
                    $this->setVar(
                        'MOD_NEWS',
                        '<a class="btn modify" href="/news/homepage/' . $news['id'] . '"><span></span>Modify</a>'
                    );
                } else {
                    $this->setVar('MOD_NEWS', '');
                }

                $postedTime = new Alaress_Date_Relative($news['posted']);

                $this->setVar(
                    array(
                        "NEWS_ID" => $news['id'],
                        "NEWS_HEADING" => Scrub::text($news['heading']),
                        "NEWS_BODY" => Scrub::html($news['body']),
                        "EMAIL_CONTACT" => "<a href=\"/search/user/{$news['uid']}\">" . $userHandler->formatName(
                            $news
                        ) . "</a>",
                        "AUTHOR" => $userHandler->formatName($news),
                        "AUTHOR_ID" => $news['uid'],
                        "AUTHOR_EXTERNAL_ID" => $news['synid'],
                        "POSTED_DATE" => $postedTime->getRelativeTime(),
                        'ON_OFF' => ++$j % 2 ? '' : 'dark'
                    )
                );

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
                    }
                    else {
                        $this->setVar('has_publish_date_block', '');
                    }

                }
                else {
                    $this->setVar('has_publish_date_block', '');
                }

                $this->parse("news_block", "news", true);
            }
        }

        return true;

    }

}
