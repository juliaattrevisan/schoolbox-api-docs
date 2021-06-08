<?php

/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Page\News;

use AcceptanceTester;
use Page\Common;

/**
 * News index page object
 */
class Index
{
    public const URL = '/news?topic={topic}';

    // Selectors

    // TODO all of these should have better selectors
    // TODO all of these belong in the main content of a page (#content)

    public const LINK_ADD_NEWS = '.icon-add';
    public const LINK_ARCHIVE  = '.icon-archive';
    public const LINK_RSS      = '.icon-rss';

    public const ELEMENT_BREADCRUMB   = '.breadcrumb';
    public const ELEMENT_FILTER_TOPIC = '.tabs';

    public const ELEMENT_NEWS_LIST                  = '.information-list';
    public const ELEMENT_NEWS_LIST_ITEM             = '.actions-small-1';
    public const ELEMENT_NEWS_LIST_ITEM_HEADLINE    = 'div.small-12.card h3 a';
    public const ELEMENT_NEWS_LIST_ITEM_ARTICLE     = '.card article';
    public const ELEMENT_NEWS_LIST_ITEM_META_AUTHOR = '.card > .meta:nth-of-type(1)';
    public const ELEMENT_NEWS_LIST_ITEM_META_DATE   = '.card > .meta:nth-of-type(2)';


    public const LINK_NEWS_ITEM_VIEW   = '.card > a';
    public const LINK_NEWS_ITEM_MODIFY = '.icon-modify';

    /**
     * @var AcceptanceTester
     */
    protected $tester;

    /**
     * Constructor
     *
     * @param AcceptanceTester $i
     */
    public function __construct(AcceptanceTester $i)
    {
        $this->tester = $i;
    }

    /**
     * Generates route for this page.
     *
     * @param string $topic
     * @return string
     */
    public function generateRoute(string $topic): string
    {
        return str_replace('{topic}', $topic, self::URL);
    }


    /**
     * Generate a news item selector.
     *
     * @param int|null $position If set: select only this number news item
     * @return string
     */
    private function generateNewsItemSelector(?int $position = null): string
    {
        $positionSelector = (null !== $position)
            ? sprintf(':nth-child(%d)', $position)
            : '';
        return sprintf(
            '%s %s %s%s',
            Common::ELEMENT_CONTENT,
            self::ELEMENT_NEWS_LIST,
            self::ELEMENT_NEWS_LIST_ITEM,
            $positionSelector
        );
    }


    /**
     * Generate a news item headline selector.
     *
     * @param int|null $position If set: select only this number news item
     * @return string
     */
    private function generateNewsItemHeadlineSelector(?int $position = null): string
    {
        return sprintf('%s %s', $this->generateNewsItemSelector($position), self::ELEMENT_NEWS_LIST_ITEM_HEADLINE);
    }


    /**
     * Generate a news item article selector.
     *
     * @param int|null $position If set: select only this number news item
     * @return string
     */
    private function generateNewsItemArticleSelector(?int $position = null): string
    {
        return sprintf('%s %s', $this->generateNewsItemSelector($position), self::ELEMENT_NEWS_LIST_ITEM_ARTICLE);
    }


    /**
     * Do I see this headline on this page?
     *
     * @param string   $headline
     * @param int|null $position If set: search only this number news item
     */
    public function seeHeadline(string $headline, ?int $position = null): void
    {
        $this->tester->see($headline, $this->generateNewsItemHeadlineSelector($position));
    }


    /**
     * Do I NOT see this headline on this page?
     *
     * @param string   $headline
     * @param int|null $position If set: search only this number news item
     */
    public function dontSeeHeadline(string $headline, ?int $position = null): void
    {
        $this->tester->dontSee($headline, $this->generateNewsItemHeadlineSelector($position));
    }


    /**
     * Do I see this article summary on this page?
     *
     * @param string   $article  Raw HTML version of article
     * @param int|null $position If set: search only this number news item
     */
    public function seeArticleSummary(string $article, ?int $position = null): void
    {
        $this->tester->seeInSource($article, $this->generateNewsItemArticleSelector($position));
    }


    /**
     * Do I NOT see this article summary on this page?
     *
     * @param string   $article  Raw HTML version of article
     * @param int|null $position If set: search only this number news item
     */
    public function dontSeeArticleSummary(string $article, ?int $position = null): void
    {
        $this->tester->dontSeeInSource($article, $this->generateNewsItemArticleSelector($position));
    }


    /**
     * View an article.
     *
     * @param int $position Article number to view
     */
    public function viewArticle(int $position): void
    {
        $this->tester->click($this->generateNewsItemSelector($position) . ' ' . self::LINK_NEWS_ITEM_VIEW);
    }


    /**
     * Modify an article.
     *
     * @param int $position Article number to view
     */
    public function modifyArticle(int $position): void
    {
        $this->tester->click($this->generateNewsItemSelector($position) . ' ' . self::LINK_NEWS_ITEM_MODIFY);
    }
}
