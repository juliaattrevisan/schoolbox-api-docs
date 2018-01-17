<?php
/**
 * @author    Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\News\Component\Dashboard;

use Component_Dashboard_ControllerAbstract;
use Component_Instance;
use News;
use Schoolbox\Collection;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\News\Topic;
use Schoolbox\News\TopicMapper;
use Schoolbox\User;
use Scrub;
use SitePage;
use TextFormatter;

class Controller extends Component_Dashboard_ControllerAbstract
{
    /**
     * @param News $news
     */
    protected $news;

    /**
     * @param array $headlineList
     */
    protected $headlineList;

    /**
     * A collection of all known news topics
     *
     * @var
     */
    protected $allTopics;

    /**
     * Number of items per topic
     * 
     * @var
     */
    protected $limit;

    /**
     * Injectable constructor
     *
     * @param Component_Instance $instance
     * @param User $user
     * @param SitePage $sitepage
     * @param News $news
     */
    public function __construct(Component_Instance $instance, User $user, SitePage $sitepage, News $news) {
        parent::__construct($instance, $user, $sitepage);
        $this->news = $news;
    }

    /**
     * Loads the grid component into its set configuration.
     *
     * @return true
     */
    public function execute()
    {
        $this->setAllTopics(TopicMapper::getInstance()->findAll()->sortBy('sequence', 'asc'));
        $this->setLimit($this->getConfig('size','value'));

        $archive     = false;
        $year        = 0;
        $month       = 0;
        $index       = 0;
        $orderByDate = true;

        /* Get $limit items per news topic
         */
        $this->headlineList = [];

        /** @var Topic $currentTopic */
        foreach ($this->getAllTopics() as $currentTopic) {
            $topicItems = $this->news->getNewsListing($archive, $currentTopic->getName(), $year, $month, $index, $this->getLimit(), $orderByDate);

            if ($topicItems) {
                $this->headlineList[$currentTopic->getId()] = $topicItems;
            }
        }

        return count($this->headlineList) > 0;
    }

    /**
     * @param String $templateHandle
     * @return bool
     */
    public function renderTemplate($templateHandle)
    {
        $newsByTopic = [];                     // Holds news items grouped by topic
        $newsAll = new Collection();           // Holds all news item grouped by date
        $added = [];

        /* Preprocess all the items we found in the db
         */
        foreach ($this->headlineList as $topicId => $topicItems) {
            foreach ($topicItems as $itemCurrent) {
                $scrubbedHtml = Scrub::html($itemCurrent['item_body']);
                $strippedContent = strip_tags($scrubbedHtml, '<img><video>');

                if (in_array($itemCurrent['item_id'], $added)) {
                    continue;
                } else {
                    $added[] = $itemCurrent['item_id'];
                }

                $item = [
                    'id' => $itemCurrent['item_id'],

                    // We only want to show a read-more link if the body of the news article is not blank (but we need to trim all the junk
                    // HTML (eg. "<p>&nbsp;</p>"... "<ul></ul>") that CKeditor tends to leave behind when someone clears out the editor in the
                    // WYSIWYG view)

                    'showLink' => !empty(trim($strippedContent)),

                    'blurb' => empty(trim(strip_tags(Scrub::html2text($itemCurrent['item_blurb'])))) ? '<p>' . TextFormatter::cutSentence(Scrub::html2text($itemCurrent['item_body']), 300) . '</p>' : Scrub::html($itemCurrent['item_blurb']),
                    'date' => new \Alaress_Date_Format($itemCurrent['item_datetime_posted'], \Alaress_Date_Format::DATABASE_FORMAT),

                    /* Used for sorting articles in All tab
                     */
                    'datetime' => $itemCurrent['item_datetime_published'],

                    'uid' => $itemCurrent['uid'],
                    'image' => $itemCurrent['item_image'],
                    'heading' => $itemCurrent['item_heading'],
                    'author' => "{$itemCurrent['title']} {$itemCurrent['firstname']} {$itemCurrent['lastname']}",
                    'authorDeleted' => null !== $itemCurrent['u.deleted_at'],
                ];

                if (!isset($newsByTopic[$itemCurrent['newsTopicID']])) {
                    $newsByTopic[$itemCurrent['newsTopicID']] = [];
                }

                $newsByTopic[$itemCurrent['newsTopicID']][] = $item;

                /* Accumulate all the items that we found so that we can order
                 * them by date, then slice away all the excess, like fat
                 */
                $newsAll->add($item);
            }
        }

        /* Order by datetime published (most recent first)
         * and trim away the fat
         */
        $newsAll->sortByCallback(function($a, $b) {
            return strcmp($b['datetime'], $a['datetime']);
        });

        return $this->renderTwigTemplate(
            $templateHandle,
            '@newsDashboard/component.twig',
            [
                'componentInstanceId'   => $this->getInstance()->getId(),
                'componentClass'        => $this->getComponentName(),
                'news'                  => $newsByTopic,
                'newsAll'               => $this->getLimit() ? $newsAll->limit($this->getLimit()) : $newsAll,
                'topics'                => $this->getAllTopics(),
                'heading'               => $this->getConfig('componentname'),
                'allDefault'            => $this->getConfig('default'),
                'compact'               => $this->getConfig('compactMode'),
            ],
            true,
            false,
            true
        );
    }

    /**
     * @return mixed
     */
    protected function getAllTopics()
    {
        return $this->allTopics;
    }

    /**
     * @param mixed $allTopics
     * @return $this
     */
    protected function setAllTopics($allTopics)
    {
        $this->allTopics = $allTopics;
        return $this;
    }

    /**
     * @return mixed
     */
    protected function getLimit()
    {

        return $this->limit;
    }

    /**
     * @param mixed $limit
     * @return $this
     */
    protected function setLimit($limit)
    {
        $this->limit = $limit;
        return $this;
    }
}
