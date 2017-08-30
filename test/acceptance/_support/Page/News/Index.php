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
    const URL = '/news/{topic}';

    // Selectors

    // TODO all of these should have better selectors
    // TODO all of these belong in the main content of a page (#content)

    const LINK_ADD_NEWS = '.icon-add';
    const LINK_ARCHIVE  = '.icon-archive';
    const LINK_RSS      = '.icon-rss';

    CONST ELEMENT_BREADCRUMB   = '.breadcrumb';
    CONST ELEMENT_FILTER_TOPIC = '.tabs';

    CONST ELEMENT_NEWS_LIST             = '.information-list';
    CONST ELEMENT_NEWS_ITEM             = self::ELEMENT_NEWS_LIST . ' .actions-small-1';
    CONST ELEMENT_NEWS_ITEM_META_AUTHOR = self::ELEMENT_NEWS_ITEM . ' .card > .meta:nth-of-type(1)';
    CONST ELEMENT_NEWS_ITEM_META_DATE   = self::ELEMENT_NEWS_ITEM . ' .card > .meta:nth-of-type(2)';
    CONST LINK_NEWS_ITEM_VIEW           = self::ELEMENT_NEWS_ITEM . ' .card > a';
    CONST LINK_NEWS_ITEM_MODIFY         = self::ELEMENT_NEWS_ITEM . ' .icon-modify';

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
