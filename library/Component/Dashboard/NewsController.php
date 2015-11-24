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
    protected $headlineList;

    public function __construct(Component_Instance $instance, \Schoolbox\User $user, SitePage $sitepage, News $news) {
        parent::__construct($instance, $user, $sitepage);

        $this->news = $news;
    }

    public function execute() {
        $this->headlineList = $this->news->getNewsListing(false);
        return !empty($this->headlineList);
    }

    public function renderTemplate($templateHandle) {
        if (!$this->loadTemplate($templateHandle, 'news')) {
            return false;
        }

        $this->parseConfig();

        $this->setBlock($templateHandle, "topic_header", "topic_header_block");
        $this->setBlock($templateHandle, "topic", "topic_block");
        $this->setBlock('topic', "news", "news_block");
        $this->setBlock('news', "news_image", "news_image_block");
        $this->setBlock('news', "news_no_image", "news_no_image_block");

        $this->setVar("topic_block", "");
        $this->setVar("topic_header_block", "");
        $this->setVar("news_block", "");
        $this->setVar("news_image_block", "");
        $this->setVar("news_no_image_block", "");

        $topicList = [];
        if (is_array($this->headlineList)) {

            $limits = array();

            $j = $k = 0;
            $total = count($this->headlineList);

            // iterate and truncate down to the max limit
            if($this->getConfig('size') > 0) {
                foreach ($this->headlineList as $index => $item) {
                    if(isset($limits[$item['topicName']])) {
                        // skip over this article if the category has hit the size limit
                        if($limits[$item['topicName']] >= $this->getConfig('size')) {
                            // decrement the total expected by 1
                            --$total;
                            // remove the news article
                            unset($this->headlineList[$index]);
                            continue;
                        }
                        ++$limits[$item['topicName']];
                    }
                    else {
                        $limits[$item['topicName']] = 1;
                    }
                }
            }

            // Group the headlines by topic
            $groupedList = [];
            foreach ($this->headlineList as $item) {
                $groupedList[$item['topicName']][] = $item;
            }
            $topicList = array_keys($groupedList);

            $userFormatter = new User();
            $i             = 0;
            foreach ($groupedList as $topicName => $headlineList) {
                $url = new Url($topicName);
                $this->setVar([
                    'NEWS_TOPIC'   => Scrub::text($topicName),
                    'TOPIC_CLEAN'  => $url->getSlug(),
                    'news_block'   => '',
                    'TOPIC_ACTIVE' => ($i++ == 0 ? 'active' : ''),
                ]);
                foreach ($headlineList as $item) {
                    $newsTime = new Alaress_Date_Format($item['item_datetime_posted'], 'Y-m-d H:i:s');
                    $output = array(
                        'NEWS_TITLE' => $item['item_heading'],
                        'NEWS_URL' => '/news/item/' . $item['item_id'],
                        'NEWS_SHORT' => TextFormatter::cutSentence(Scrub::html2text($item['item_body']), 300),
                        'NEWS_TIME' => $newsTime->getRelativeTime(),
                        'NEWS_IMAGE' => $item['item_image'],
                        'NEWS_AUTHOR_NAME' => $userFormatter->formatName($item),
                        'NEWS_AUTHOR_ID' => $item['uid'],
                    );

                    $this->setVar($output);

                    if ($item['item_image']) {
                        $this->parse('news_image_block', 'news_image');
                        $this->setVar('news_no_image_block', '');
                    } else {
                        $this->parse('news_no_image_block', 'news_no_image');
                        $this->setVar('news_image_block', '');
                    }
                    $this->parse("news_block", "news", true);
                }
                $this->parse('topic_block', 'topic', true);
            }
        } else {
            $this->setVar("topic_block", '');
        }

        // output tabs
        if (is_array($topicList)) {
            foreach ($topicList as $i => $topic) {
                $url = new Url($topic);

                $output = array(
                    'NEWS_TOPIC'   => $topic,
                    'TOPIC_CLEAN'  => $url->getSlug(),
                    'TOPIC_ACTIVE' => ($i == 0 ? 'active' : ''),
                );
                $this->setVar($output);
                $this->parse('topic_header_block', 'topic_header', true);
            }
        } else {
            $this->setVar('topic_header_block', '');
        }


        return true;
    }

}
