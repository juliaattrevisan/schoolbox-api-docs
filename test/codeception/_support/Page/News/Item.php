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
 * News item page object
 */
class Item
{
    public const URL = '/news/item/{id}';

    // Selectors

    // TODO all of these should have better selectors
    // TODO all of these belong in the main content of a page (#content)

    public const DOT_MENU   = 'a.icon-more';
    public const LINK_MODIFY   = 'Modify';
    public const LINK_PREVIOUS = Common::ELEMENT_CONTENT . ' .icon-previous';
    public const LINK_NEXT     = Common::ELEMENT_CONTENT . ' .icon-next';

    public const ELEMENT_BREADCRUMB = Common::ELEMENT_CONTENT . ' .breadcrumb';

    public const ELEMENT_HEADLINE      = Common::ELEMENT_CONTENT . ' h1';
    public const ELEMENT_META_AUTHOR   = Common::ELEMENT_CONTENT . ' .card > .meta:nth-of-type(1)';
    public const ELEMENT_META_DATE     = Common::ELEMENT_CONTENT . ' .card > .meta:nth-of-type(2)';
    public const ELEMENT_FEATURE_IMAGE = Common::ELEMENT_CONTENT . ' .large-3 img';
    public const ELEMENT_ARTICLE       = Common::ELEMENT_CONTENT . ' .large-9 article';

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
     * @param $id
     * @return mixed
     */
    public function generateRoute($id)
    {
        return str_replace('{id}', $id, self::URL);
    }


    /**
     * Do I see this headline?
     *
     * @param string $headline
     */
    public function seeHeadline(string $headline): void
    {
        $this->tester->see($headline, self::ELEMENT_HEADLINE);
    }


    /**
     * Do I see this article's contents?
     *
     * @param string $article
     */
    public function seeArticle(string $article): void
    {
        $this->tester->seeInSource($article, self::ELEMENT_ARTICLE);
    }


    /**
     * Follow this article's modify link.
     */
    public function modifyArticle(): void
    {
        $this->tester->click(self::DOT_MENU);
        $this->tester->click(self::LINK_MODIFY);
    }
}
