<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2017, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Page\News;

use AcceptanceTester;

/**
 * News form page object
 *
 * This serves the dual purpose of a create and modify news page.
 */
class Form
{
    const URL = '/news/modify/{id}';

    // Selectors

    // TODO all of these should have better selectors
    // TODO all of these belong in the main content of a page (#content)

    const LINK_ADD_NEWS = '.icon-add';

    // No crumbtrail on this page?

    const FORM      = '#newsForm';
    // Not sure if every input should have its own selector constant
    const FORM_SAVE = self::FORM . ' input[type="submit"]';

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

    public function setHeadline($headline)
    {
        // TODO add steps for setting headline here
    }

    public function setArticle($article)
    {
        // TODO add steps for setting article content here
        // TODO will need to figure out how to set rich text content
    }
}
