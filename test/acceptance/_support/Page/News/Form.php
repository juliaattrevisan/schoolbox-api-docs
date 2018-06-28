<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2017, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

declare(strict_types=1);

namespace Page\News;

use AcceptanceTester;
use Alaress_Date_Format;

/**
 * News form page object
 *
 * This serves the dual purpose of a create and modify news page.
 */
class Form
{
    public const URL = '/news/modify/{id}';

    // Selectors

    // TODO all of these should have better selectors
    // TODO all of these belong in the main content of a page (#content)

    public const LINK_ADD_NEWS = '.icon-add';

    // No crumbtrail on this page?
    public const FORM = '#newsForm';

    // Not sure if every input should have its own selector constant
    public const FORM_SAVE = self::FORM . ' input[type="submit"]';

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
     * @return Form
     */
    public function setHeadline(string $headline): self
    {
        $this->tester->fillField('heading', $headline);
        return $this;
    }

    /**
     * @param string $article
     * @return Form
     */
    public function setArticle(string $article): self
    {
        $this->tester->fillRichTextField('body', $article);
        return $this;
    }

    /**
     * @param string $blurb
     * @return Form
     */
    public function setBlurb(string $blurb): self
    {
        // Expand blurb pane, and then enter it in
        $this->tester->click('[data-collapser="blurb"]');
        $this->tester->fillRichTextField('blurb', $blurb);
        return $this;
    }

    /**
     * @param string $filename
     * @return Form
     */
    public function setFeatureImage(string $filename): self
    {
        // TODO upload files via Codeception
        return $this;
    }

    /**
     * @param string $topic
     * @return Form
     */
    public function setTopic(string $topic): self
    {
        return $this;
    }

    /**
     * @param array $roles
     * @return Form
     */
    public function setRoles(array $roles): self
    {
        return $this;
    }

    /**
     * @param array $campuses
     * @return Form
     */
    public function setCampuses(array $campuses): self
    {
        return $this;
    }

    /**
     * @param array $users
     * @return Form
     */
    public function setUsers(array $users): self
    {
        return $this;
    }

    /**
     * @param Alaress_Date_Format $publishDate
     * @return Form
     */
    public function setPublishDate(Alaress_Date_Format $publishDate): self
    {
        return $this;
    }

    /**
     * @param Alaress_Date_Format $expiryDate
     * @return Form
     */
    public function setExpiryDate(Alaress_Date_Format $expiryDate): self
    {
        return $this;
    }
}
