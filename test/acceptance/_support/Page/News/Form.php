<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2017, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

declare(strict_types=1);

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
     * @param string $id
     * @return string
     */
    private function generateRoute(string $id): string
    {
        return str_replace('{id}', $id, self::URL);
    }

    /**
     * Generates create route for this page.
     *
     * @return string
     */
    public function generateCreateRoute(): string
    {
        return $this->generateRoute('');
    }

    /**
     * Generates modify route for this page.
     *
     * @param int $id
     * @return string
     */
    public function generateModifyRoute(int $id): string
    {
        return $this->generateRoute((string)$id);
    }

    /**
     * @param string $headline
     */
    public function setHeadline(string $headline): void
    {
        $this->tester->fillField('heading', $headline);
    }

    /**
     * @param string $article
     */
    public function setArticle(string $article): void
    {
        $this->tester->fillRichTextField('body', $article);
    }
}
