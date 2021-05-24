<?php

/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Page\News;

use AcceptanceTester;

/**
 * News archive page object
 */
class Archive
{
    const URL = '/news-archive/{topic}/{year}/{month}';

    // Selectors

    // TODO all of these should have better selectors
    // TODO all of these belong in the main content of a page (#content)

    const LINK_ADD_NEWS = '.icon-add';

    const ELEMENT_BREADCRUMB   = '.breadcrumb';
    const ELEMENT_FILTER_TOPIC = '.tabs';
    const ELEMENT_FILTER_YEAR  = '.sub-nav';
    // This exists only within a dialog box, currently
    const ELEMENT_FILTER_MONTH = '.reveal-modal .information-list';

    const ELEMENT_NEWS_LIST      = '.information-list';
    const ELEMENT_NEWS_ITEM      = self::ELEMENT_NEWS_LIST . ' .actions-small-1';
    // Archive doesn't have separate meta elements, unlike news index
    const ELEMENT_NEWS_ITEM_META = self::ELEMENT_NEWS_ITEM . ' .card > .meta';
    const LINK_NEWS_ITEM_VIEW    = self::ELEMENT_NEWS_ITEM . ' .card > a';
    // Archive doesn't have a modify element

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
     * @param string $year
     * @param string $month
     * @return string
     */
    public function generateRoute($topic, $year = '', $month = '')
    {
        // TODO make generic
        return preg_replace(
            '[\\/+$]',
            '/',
            str_replace(
                ['{topic}', '{year}', '{month}'],
                [$topic, $year, $month],
                self::URL
            )
        );
    }
}
