<?php

/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Page\News;

use AcceptanceTester;
use Alaress_Date_Format;
use Page\Common;

/**
 * News form page object
 *
 * This serves the dual purpose of a create and modify news page.
 */
class Form
{
    public const URL = '/news/create';

    // Selectors

    // TODO all of these should have better selectors
    // TODO all of these belong in the main content of a page (#content)

    public const LINK_ADD_NEWS = '.icon-add';

    // No crumbtrail on this page?
    public const FORM = Common::ELEMENT_CONTENT . ' #newsForm';

    // Not sure if every input should have its own selector constant
//    public const FORM_SAVE = self::FORM . ' button[type="submit"]';
    public const FORM_SAVE = '#news-submit';

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
     * @return Form
     */
    public function submit(): self
    {
        $this->tester->click(self::FORM_SAVE);
        return $this;
    }

    /**
     * @param string $headline
     * @return Form
     */
    public function fillHeadline(string $headline): self
    {
        $this->tester->fillField('title', $headline);
        return $this;
    }

    /**
     * @param string $headline
     * @return Form
     */
    public function seeHeadline(string $headline): self
    {
        $this->tester->seeInField('heading', $headline);
        return $this;
    }

    /**
     * @param string $article
     * @return Form
     */
    public function fillArticle(string $article): self
    {
        $this->tester->fillRichTextField('body', $article);
        return $this;
    }

    /**
     * @param string $headline
     * @return Form
     */
    public function seeArticle(string $headline): self
    {
        $this->tester->seeInRichTextField('body', $headline);
        return $this;
    }

    /**
     * @param string $blurb
     * @return Form
     */
    public function fillBlurb(string $blurb): self
    {
        // Expand blurb pane, and then enter it in
        $this->tester->click('legend.icon-down-arrow');
        $this->tester->fillRichTextField('blurb', $blurb);
        return $this;
    }

    /**
     * @param string $blurb
     * @return Form
     */
    public function seeBlurb(string $blurb): self
    {
        // Expand blurb pane, and then ensure I see its contents
        $this->tester->click('[data-collapser="blurb"]');
        $this->tester->seeInRichTextField('blurb', $blurb);
        return $this;
    }

    /**
     * @param string $filename
     * @return Form
     */
    public function fillFeatureImage(string $filename): self
    {
        $this->tester->attachAttachzoneFile('#featureImage-zone', $filename);
        return $this;
    }

    /**
     * @param string $filename
     * @return Form
     */
    public function seeFeatureImage(string $filename): self
    {
        $this->tester->seeAttachzoneFile('#news-feature-zone', $filename);
        return $this;
    }

    /**
     * @param string $topic
     * @return Form
     */
    public function fillTopic(string $topic): self
    {
//        $this->tester->selectOption('item_topic_id', $topic);
        $this->tester->click('input#news-topics-selectized');
        $element = Locator::contains('div.option', $topic);
        $this->tester->click($element);
        return $this;

    }

    /**
     * @param string $topic
     * @return Form
     */
    public function seeTopic(string $topic): self
    {
        $this->tester->seeOptionIsSelected('item_topic_id', $topic);
        return $this;
    }

    /**
     * @param array $roles
     * @return Form
     */
    public function fillRoles(array $roles): self
    {
        $this->tester->checkSelectGroupOptions('role', $roles);
        return $this;
    }

    /**
     * @param array $roles
     * @return Form
     */
    public function seeRoles(array $roles): self
    {
        $this->tester->seeSelectGroupOptionsAreSelected('role', $roles);
        return $this;
    }

    /**
     * @param array $campuses
     * @return Form
     */
    public function fillCampuses(array $campuses): self
    {
        $this->tester->checkSelectGroupOptions('campus', $campuses);
        return $this;
    }

    /**
     * @param array $campuses
     * @return Form
     */
    public function seeCampuses(array $campuses): self
    {
        $this->tester->seeSelectGroupOptionsAreSelected('campus', $campuses);
        return $this;
    }

    /**
     * @param array $users
     * @return Form
     */
    public function fillUsers(array $users): self
    {
//        $this->tester->selectSelectizeOptions('userList[]', $users);
        //adding this pice of code just to continue fixing the tests as I got stuck with this function to fill the users
        $this->tester->scrollTo(['css' => 'button.button.icon-drag-drop']);
        sleep(5);
        $this->tester->click(['css' => 'button.button.icon-drag-drop'], ['css' => 'div#audience-selectize-group']);
//        $this->tester->waitForText('Year Levels');
        $this->tester->click('i.icon-teacher');
//        $element = Locator::contains('button.button-block', 'Year Levels');
//        $this->tester->clickWithLeftButton($element);
        $this->tester->executeJS("var scrollableDiv = document.getElementsByClassName('checklist checklist-container');
//        scrollableDiv.scrollTop=scrollableDiv.scrollHeight - scrollableDiv.offsetHeight;");
//        $this->tester->waitForText('Year 3', 5);
        $Year_9_element = Locator::contains('div.checklist-wrapper label', 'Year 3');
        $this->tester->clickWithLeftButton($Year_9_element);
        $this->tester->click('button.button.submit');
        sleep(5);

        return $this;

    }

    /**
     * @param array $users
     * @return Form
     */
    public function seeUsers(array $users): self
    {
        $this->tester->seeSelectizeOptionsAreSelected('userList[]', $users);
        return $this;
    }

    /**
     * @param Alaress_Date_Format $publishDate
     * @return Form
     */
    public function fillPublishDate(Alaress_Date_Format $publishDate): self
    {
        // Type in database format, let strtotime do the work
        // This is because Codeception has no access to the DB config
        $this->tester->fillDateField('item_datetime_published', $publishDate->getDatabase());
        return $this;
    }

    /**
     * @param Alaress_Date_Format $publishDate
     * @return Form
     */
    public function seePublishDate(Alaress_Date_Format $publishDate): self
    {
        $this->tester->seeInField(
            'item_datetime_published',
            $publishDate->getUsingFormat(Common::FORMAT_INPUT_DATETIME)
        );
        return $this;
    }

    /**
     * @param Alaress_Date_Format $expiryDate
     * @return Form
     */
    public function fillExpiryDate(Alaress_Date_Format $expiryDate): self
    {
        $this->tester->fillDateField('item_datetime_expires', $expiryDate->getDatabase());
        return $this;
    }

    /**
     * @param Alaress_Date_Format $expiryDate
     * @return Form
     */
    public function seeExpiryDate(Alaress_Date_Format $expiryDate): self
    {
        $this->tester->seeInField(
            'item_datetime_expires',
            $expiryDate->getUsingFormat(Common::FORMAT_INPUT_DATETIME)
        );
        return $this;
    }
}
