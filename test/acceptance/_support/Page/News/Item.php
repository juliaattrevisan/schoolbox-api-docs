<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2017, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Page\News;

use AcceptanceTester;

/**
 * News item page object
 */
class Item
{
    const URL = '/news/item/{id}';

    // Selectors

    // TODO all of these should have better selectors
    // TODO all of these belong in the main content of a page (#content)

    const LINK_MODIFY   = '.icon-modify';
    const LINK_PREVIOUS = '.icon-previous';
    const LINK_NEXT     = '.icon-next';

    const ELEMENT_BREADCRUMB = '.breadcrumb';

    const ELEMENT_META_AUTHOR   = '.card > .meta:nth-of-type(1)';
    const ELEMENT_META_DATE     = '.card > .meta:nth-of-type(2)';
    const ELEMENT_FEATURE_IMAGE = '.large-3 img';
    const ELEMENT_ARTICLE       = '.large-9 article';

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
}
