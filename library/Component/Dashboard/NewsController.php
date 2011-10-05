<?php

/**
 *
 * @author      Alex Tworowsky <alex.tworowsky@alaress.com.au>
 * @copyright   2011 Alaress Pty Ltd
 * @link        http://www.alaress.com.au
 * @version     SVN: $Id$
 *
 */

class Component_Dashboard_NewsController extends Component_Dashboard_ControllerAbstract {

    protected $news;

    public function __construct (Component_Instance $instance, User $user, SitePage $sitepage, News $news) {
        parent::__construct($instance, $user, $sitepage);

        $this->news = $news;
    }

    public function execute () {
        $this->headlineList = $this->news->getHeadlines();

        return true;
    }

    public function renderTemplate ($templateHandle) {
        if ( !$this->loadTemplate($templateHandle, 'news') ) {
            return false;
        }

        $this->setBlock($templateHandle, "topic_header", "topic_header_block");
        $this->setBlock($templateHandle, "topic", "topic_block");
        $this->setBlock('topic', "news", "news_block");

        $this->setVar("topic_block", "");
        $this->setVar("topic_header_block", "");
        $this->setVar("news_block", "");

        if ( is_array($this->headlineList) ) {

            $j = $k = 0;
            $total = count($this->headlineList);

            foreach ( $this->headlineList as $item ) {
                $j++;
                $url = new Url( $previous['topicName'] );

                $newsTime = new Alaress_Date_Relative( $item['item_datetime_modified'] );
                $newsTime->setPrefix('');

                $output = array(
                    'NEWS_TITLE' => $item['item_heading'], 'NEWS_URL' => '/news/#' . $item['item_id'],
                    'NEWS_TIME' => $newsTime->getRelativeTime(), 'ON_OFF' => ++$k % 2 ? '' : 'dark',
                    'NEWS_TOPIC' => Scrub::text($previous['topicName']), 'TOPIC_CLEAN' => $url->getSlug(),
                );

                $this->setVar($output);
                $topicList[$item['topicName']] = 1;

                if ( $item['topicName'] != $previous['topicName'] && $j > 1 ) {

                    /* end topic */
                    $this->parse('topic_block', 'topic', true);

                    $k = 0;
                    $this->setVar('ON_OFF', ++$k % 2 ? '' : 'dark');

                    $this->setVar("news_block", "");
                    $this->parse("news_block", "news", true);

                    /* last rotation */
                    if ( $total == $j ) {
                        $url = new Url( $item['topicName'] );

                        $this->setVar('NEWS_TOPIC', Scrub::text($item['topicName']));
                        $this->setVar('TOPIC_CLEAN', $url->getSlug());

                        $this->parse('topic_block', 'topic', true);
                    }

                }
                elseif ( $total == $j ) {
                    /* last rotation */
                    $this->parse("news_block", "news", true);

                    $this->parse('topic_block', 'topic', true);
                }
                else {
                    /* normal rotation */
                    $this->parse("news_block", "news", true);
                }

                $previous = $item;
            }

        }
        else {
            $this->setVar("topic_block", "<li>No news articles available</li>");
        }

        // output tabs
        if(is_array($topicList)) {
            foreach ($topicList as $topic => $bin) {
                $url = new Url($topic);

                $output = array('NEWS_TOPIC'	=> Scrub::text($topic),
                                'TOPIC_CLEAN'	=> $url->getSlug(),
                                );
                $this->setVar($output);
                $this->parse('topic_header_block', 'topic_header', true);
            }
        }
        else{
            $this->setVar('topci_header_block','');
        }


        return true;
    }

}