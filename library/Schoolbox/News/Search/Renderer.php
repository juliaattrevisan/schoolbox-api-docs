<?php
/**
 * Renderer
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\News\Search;

use Schoolbox\Resource\Search\Renderer\Handler;
use Scrub;
use TextFormatter;
use Alaress_Date_Format;
use DateTime;

class Renderer extends Handler
{

    /**
     * Type (e.g. 'link', 'blog', etc)
     *
     * @var string
     */
    protected $type = 'sitenews';

    /**
     * Tab Name (e.g. 'Link', 'Blogs', etc);
     *
     * @var string
     */
    protected $tabName = 'School News';

    /**
     * Constructor
     *
     * @param \SitePage $page
     * @param Base[]    $result Equivalent of $result['blog'] etc.
     * @param array     $count  Equivalent of $count['blog'] etc.
     * @param           $currentPage
     */
    public function __construct(\SitePage $page, $result, $count, $currentPage)
    {
        parent::__construct($page, $result, $count, $currentPage);
        $this->setTabName('School '.$page->getLocalisedTerm('news'));
    }

    /**
     * Render
     */
    public function render()
    {
        $userHandler = new \User();

        $result = $this->getResult($this->getType());

        if (!empty($result)) {
            $this->getPage()->setFile('sitenews_t', 'resource/sitenews/search/results.html');
            $this->getPage()->setBlock('sitenews_t', 'sitenews', 'sitenews_block');
            $this->getPage()->setVar(array(
                'SITENEWS_DISP_COUNT' => count($this->getResult($this->getType())),
                'SITENEWS_COUNT' => $this->getCount($this->getType()),
            ));

            foreach ($result as $news) {
                $now = new DateTime();
                $expiryDate = new DateTime($news['item_datetime_expires']);

                $tName = strtolower($news['topicName']);
                $archived = ($now->getTimestamp() > $expiryDate->getTimestamp());
                $news['breadcrumb'] = ($archived ? ' Archived' : 'Posted') . " in <a href=\"/news/" .
                    ($archived ? 'archive/' : '') .
                    "$tName\">".Scrub::text($news['topicName'])."</a>";

                $news['link'] = "/news/item/{$news['item_id']}";

                $createdTime = new Alaress_Date_Format($news['item_datetime_posted'], 'Y-m-d H:i:s');

                $this->getPage()->setVar(array(
                    'SITENEWS_ID' => $news['item_id'],
                    'SITENEWS_CODE' => $news['code'],
                    'SITENEWS_LINK' => $news['link'],
                    'SITENEWS_HEADING' => Scrub::text($news['item_heading']),
                    'SITENEWS_BODY' => TextFormatter::cutSentence(Scrub::html2text($news['item_body']), 150),
                    'SITENEWS_OWNER' => $news['item_owner'],
                    'SITENEWS_OWNER_NAME' => $userHandler->formatName($news),
                    'SITENEWS_CREATED' => $createdTime->getRelativeTime(),
                    'SITENEWS_BREADCRUMB' => $news['breadcrumb'],
                    'SITENEWS_COUNT' => $this->getCount($this->getType()),
                ));

                $this->getPage()->parse('sitenews_block', 'sitenews', true);
            }

            $this->outputSeeMore();

            $this->getPage()->parse('RESULT', 'sitenews_t', true);
        } else {
            $this->getPage()->setVar('see_more_block', '');

            return;
        }
    }
}
