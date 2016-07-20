<?php
use Schoolbox\News\TopicMapper;

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

    /**
     * Prepares data for the render function. Business logic.
     *
     * @return bool Whether to continue on and attempt Component render
     */
    public function execute() {
        $archive     = false;
        $topic       = '';
        $year        = 0;
        $month       = 0;
        $index       = 0;
        $limit       = 0;
        $orderByDate = true;

        $this->headlineList = $this->news->getNewsListing($archive, $topic, $year, $month, $index, $limit, $orderByDate);

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
        $this->setBlock('news', "read_more", "read_more_block");
        $this->setBlock('news', "no_read_more", "no_read_more_block");
        $this->setBlock('news', "compact", "compact_block");
        $this->setBlock('news', "full", "full_block");

        $this->setVar("topic_block", "");
        $this->setVar("topic_header_block", "");
        $this->setVar("news_block", "");
        $this->setVar("news_image_block", "");
        $this->setVar("news_no_image_block", "");
        $this->setVar("read_more_block", "");
        $this->setVar("no_read_more_block", "");

        $topicList = [];

        if (is_array($this->headlineList))
        {
            // Group the headlines by topic with a special 'All' category

            $groupedList = [];
            $groupedList['All'] = [];

            $topicsOrdered = TopicMapper::getInstance()->findAll()->sortBy('sequence', 'asc');

            foreach ($topicsOrdered as $topic) {
                $groupedList[$topic->getName()] = [];
            }

            foreach ($this->headlineList as $item) {
                $groupedList[$item['topicName']][] = $item;
                $groupedList['All'][] = $item;
            }

            // If 'All' is not marked as default in the components configuration, move it to the end of the array

            if ($this->getConfig('default') != 1) {
                $allArray = $groupedList['All'];
                unset($groupedList['All']);
                $groupedList['All'] = $allArray;
            }

            // If applicable truncate all of the grouped lists to limit specified in config

            if ($this->getConfig('size') > 0) {
                foreach ($groupedList as $key => $value) {
                    $groupedList[$key] = array_slice($value, 0, $this->getConfig('size'));
                }
            }

            // Remove empty topics

            foreach ($groupedList as $key => $value) {
                if (count($groupedList[$key]) == 0) {
                    unset($groupedList[$key]);
                }
            }

            $topicList = array_keys($groupedList);

            $userFormatter = new User();
            $active = 0;

            foreach ($groupedList as $topicName => $headlineList)
            {
                $url = new Url($topicName);
                $this->setVar([
                    'NEWS_TOPIC'   => Scrub::text($topicName),
                    'TOPIC_CLEAN'  => $url->getSlug(),
                    'news_block'   => '',
                    'TOPIC_ACTIVE' => ($active++ == 0 ? 'active' : ''),
                ]);

                foreach ($headlineList as $item)
                {
                    $newsTime = new Alaress_Date_Format($item['item_datetime_posted'], 'Y-m-d H:i:s');
                    $output = array(
                        'NEWS_TITLE' => $item['item_heading'],
                        'NEWS_URL' => '/news/item/' . $item['item_id'],
                        'NEWS_SHORT' => empty($item['item_blurb'])
                            ? '<p>'.TextFormatter::cutSentence(Scrub::html2text($item['item_body']), 300).'</p>'
                            : Scrub::html($item['item_blurb']),
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

                    if (empty(Scrub::html($item['item_body']))) {

                        // Body empty, no read more link

                        $this->setVar('read_more_block', '');
                        $this->parse('no_read_more_block', 'no_read_more');
                    }
                    else {

                        // Blurb is empty, always link to article

                        $this->parse('read_more_block', 'read_more');
                        $this->setVar('no_read_more_block', '');
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
