<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2017, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Page\News;

use AcceptanceTester;

/**
 * News index page object
 */
class Index
{
    public const URL = '/news/{topic}';

    // Selectors

    // TODO all of these should have better selectors
    // TODO all of these belong in the main content of a page (#content)

    public const LINK_ADD_NEWS = '.icon-add';
    public const LINK_ARCHIVE  = '.icon-archive';
    public const LINK_RSS      = '.icon-rss';

    public const ELEMENT_BREADCRUMB   = '.breadcrumb';
    public const ELEMENT_FILTER_TOPIC = '.tabs';

    public const ELEMENT_NEWS_LIST             = '.information-list';
    public const ELEMENT_NEWS_ITEM             = self::ELEMENT_NEWS_LIST . ' .actions-small-1';
    public const ELEMENT_NEWS_ITEM_META_AUTHOR = self::ELEMENT_NEWS_ITEM . ' .card > .meta:nth-of-type(1)';
    public const ELEMENT_NEWS_ITEM_META_DATE   = self::ELEMENT_NEWS_ITEM . ' .card > .meta:nth-of-type(2)';
    public const LINK_NEWS_ITEM_VIEW           = self::ELEMENT_NEWS_ITEM . ' .card > a';
    public const LINK_NEWS_ITEM_MODIFY         = self::ELEMENT_NEWS_ITEM . ' .icon-modify';

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
     * @param $topic
     * @return mixed
     */
    public function generateRoute($topic)
    {
        return str_replace('{topic}', $topic, self::URL);
    }
}
