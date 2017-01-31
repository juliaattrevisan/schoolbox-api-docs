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
use Schoolbox\Mapper\Exception as MapperException;
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
        $archive     = false;
        $topic       = '';
        $year        = 0;
        $month       = 0;
        $index       = 0;
        $limit       = $this->getConfig('size','value');
        $orderByDate = true;

        $this->headlineList = $this->news->getNewsListing($archive, $topic, $year, $month, $index, $limit, $orderByDate);

        return !empty($this->headlineList);
    }

    /**
     * @param String $templateHandle
     * @return bool
     */
    public function renderTemplate($templateHandle)
    {
        $newsByTopic = [];                     // Holds news items grouped by topic
        $newsAll = [];                  // Holds all news item grouped by date

        $topics = TopicMapper::getInstance()->findAll()->sortBy('sequence', 'asc');

        if (is_array($this->headlineList)) {
            foreach ($this->headlineList as $itemCurrent) {
                $item = [];
                $item['id'] = $itemCurrent['item_id'];

                // We only want to show a read-more link if the body of the news article is not blank (but we need to trim all the junk
                // HTML (eg. "<p>&nbsp;</p>"... "<ul></ul>") that CKeditor tends to leave behind when someone clears out the editor in the
                // WYSIWYG view)

                $item['showLink'] = !empty(trim(strip_tags(Scrub::html2text($itemCurrent['item_body']))));
                $item['blurb'] = empty(trim(strip_tags(Scrub::html2text($itemCurrent['item_blurb'])))) ? '<p>' . TextFormatter::cutSentence(Scrub::html2text($itemCurrent['item_body']), 300) . '</p>' : Scrub::html($itemCurrent['item_blurb']);

                $item['date'] = new \Alaress_Date_Format($itemCurrent['item_datetime_posted'], \Alaress_Date_Format::DATABASE_FORMAT);
                $item['uid'] = $itemCurrent['uid'];
                $item['image'] = $itemCurrent['item_image'];
                $item['heading'] = $itemCurrent['item_heading'];
                $item['author'] = "{$itemCurrent['title']} {$itemCurrent['firstname']} {$itemCurrent['lastname']}";

                if (!isset($newsByTopic[$itemCurrent['newsTopicID']])) {
                    $newsByTopic[$itemCurrent['newsTopicID']] = [];
                }

                $newsByTopic[$itemCurrent['newsTopicID']][] = $item;
                $newsAll[] = $item;
            }
        }

        return $this->renderTwigTemplate(
            $templateHandle,
            '@newsDashboard/component.twig',
            [
                'componentInstanceId'   => $this->getInstance()->getId(),
                'componentClass'        => $this->getComponentName(),
                'news'                  => $newsByTopic,
                'newsAll'               => $newsAll,
                'topics'                => $topics,
                'heading'               => $this->getConfig('componentname','value'),
                'allDefault'            => $this->getConfig('default', 'value'),
            ],
            true,
            false,
            true
        );
    }
}