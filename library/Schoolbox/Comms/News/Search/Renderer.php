<?php
/**
 * Renderer
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
namespace Schoolbox\Comms\News\Search;

use Schoolbox\Resource\Search\MapperResult\Base as MapperResult;
use Schoolbox\Resource\Search\Renderer\Base;
use Scrub;

class Renderer extends Base
{

    /**
     * Type (e.g. 'link', 'blog', etc)
     *
     * @var string
     */
    protected $type = 'news';

    /**
     * Tab Name (e.g. 'Link', 'Blogs', etc);
     *
     * @var string
     */
    protected $tabName = 'News';

    /**
     * Render html fragment
     *
     * @param MapperResult $mapperResult
     * @param              $type
     * @throws \Aura\Di\Exception\ServiceNotFound
     * @throws \RuntimeException
     */
    public function render(MapperResult $mapperResult, $type): void
    {
        if ($mapperResult->getCount()) {
            $this->getPage()->setFile('news_t', 'resource/news/search/results.html');
            $this->getPage()->setBlock('news_t', 'news', 'news_block');
            $this->getPage()->setBlock('news_t', 'see_more', 'see_more_block');

            $this->getPage()->setVar(array(
                'see_more_block' => ''
            ));

            $this->getPage()->setVar(array(
                'NEWS_DISP_COUNT' => $mapperResult->getResult()->count(),
                'NEWS_COUNT' => $mapperResult->getCount(),
            ));

            /* phplib
             */
            foreach ($mapperResult->getResult() as $currentArticle) {
                $this->getPage()->setVar(array(
                    'NEWS_ID' => $currentArticle->getId(),
                    'NEWS_LINK' => $this->getPage()->getDi()->get('router')->generate('commsNews.getDetail', ['articleId' => $currentArticle->getId()]),
                    'NEWS_TITLE' => \Scrub::text($currentArticle->getTitle()),
                    'NEWS_AUTHOR_ID' => $currentArticle->getAuthor()->getId(),
                    'NEWS_AUTHOR_NAME' => \Scrub::text($currentArticle->getAuthor()->getFullname()),
                    'NEWS_PUBLISH_AT_LONG' => $currentArticle->getPublishAt()->getLong(),
                    'NEWS_PUBLISH_AT' => $currentArticle->getPublishAt()->getRelativeTime(),
                    'NEWS_BLURB' => $currentArticle->getBlurb() ? \Scrub::html($currentArticle->getBlurb()) : \TextFormatter::cutSentence(Scrub::html2text($currentArticle->getBody()), 300),
                ));

                $this->getPage()->parse('news_block', 'news', true);
            }

            /* "outputMore"
             */
            if ($type === 'all' && $mapperResult->getCount() > \Config::get('search_all_limit')) {
                $this->getPage()->setVar('MORE_COUNT', $mapperResult->getCount());
                $this->getPage()->parse('see_more_block', 'see_more');
            }

            $this->getPage()->parse('RESULT', 'news_t', true);
        }
    }
}
