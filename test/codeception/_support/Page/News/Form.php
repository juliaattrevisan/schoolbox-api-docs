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
use Codeception\Util\Locator;
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
        $this->tester->seeInField('title', $headline);
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
        $this->tester->click('legend.icon-down-arrow');
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
        $this->tester->seeAttachzoneFile('#featureImage-zone', $filename);
        return $this;
    }

    /**
     * @param string $topic
     * @return Form
     */
    public function fillTopic(string $topic): self
    {
        $this->tester->click('input#news-topics-selectized');
        $this->tester->executeJS("var scrollableDiv = document.getElementsByClassName('selectize-dropdown multi plugin-remove_button');
        scrollableDiv.scrollTop=scrollableDiv.scrollHeight - scrollableDiv.offsetHeight;");
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
        $this->tester->canSee($topic, 'div.item');
        return $this;
    }
    /**
     * @param array $roles
     * @return Form
     */
    public function fillRoles(array $roles): self
    {
        foreach ($roles as &$role) {
            $this->tester->scrollTo(['css' => 'button.button.icon-drag-drop']);
            $this->tester->click(['css' => 'button.button.icon-drag-drop'], ['css' => 'div#audience-selectize-group']);
            $this->tester->waitForText('Roles');
            $element = Locator::contains('button.button-block', 'Roles');
            $this->tester->clickWithLeftButton($element);
            $this->tester->executeJS(
                "var scrollableDiv = document.getElementsByClassName('checklist checklist-container');
            scrollableDiv.scrollTop=scrollableDiv.scrollHeight - scrollableDiv.offsetHeight;"
            );
            $this->tester->waitForText($role, 5);
            $teacher_element = Locator::contains('div.checklist-wrapper', $role);
            $this->tester->clickWithLeftButton($teacher_element);
            $this->tester->click('button.button.submit');
            sleep(2);
        }
        return $this;
    }

    /**
     * @param array $roles
     * @return Form
     */
    public function seeRoles(array $roles): self
    {
        foreach ($roles as &$role) {
            $this->tester->canSee($role);
        }
        return $this;
    }

    /**
     * @param array $campuses
     * @return Form
     */
    public function fillCampuses(array $campuses): self
    {

        foreach ($campuses as &$campus) {
            $this->tester->scrollTo(['css' => 'button.button.icon-drag-drop']);
            $this->tester->click(['css' => 'button.button.icon-drag-drop'], ['css' => 'div#audience-selectize-group']);
            $this->tester->waitForText('Year Levels');
            $element = Locator::contains('button.button-block', 'Campuses');
            $this->tester->clickWithLeftButton($element);
            $this->tester->executeJS("var scrollableDiv = document.getElementsByClassName('checklist checklist-container');
            scrollableDiv.scrollTop=scrollableDiv.scrollHeight - scrollableDiv.offsetHeight;");
            $this->tester->waitForText('Secondary', 5);
            $campus_element = Locator::contains('div.checklist-wrapper', $campus);
            $this->tester->clickWithLeftButton($campus_element);
            $this->tester->click('button.button.submit');
            sleep(2);
        }

        return $this;
    }

    /**
     * @param array $campuses
     * @return Form
     */
    public function seeCampuses(array $campuses): self
    {
        foreach ($campuses as &$campus) {
            $this->tester->canSee($campus);
        }
        return $this;
    }

    /**
     * @param array $users
     * @return Form
     */
    public function fillUsers(array $users): self
    {
        foreach ($users as &$user) {
            $this->tester->click('input#audience-search-selectized');
            $user_chars = str_split($user);
            foreach ($user_chars as &$user_char) {
                $this->tester->pressKey('input#audience-search-selectized', $user_char);
            }
            $this->tester->pressKey('input#audience-search-selectized', \Facebook\WebDriver\WebDriverKeys::ENTER);
        }
        return $this;
    }

    /**
     * @param array $users
     * @return Form
     */
    public function seeUsers(array $users): self
    {
        foreach ($users as &$user) {
            $this->tester->canSee($user);
        }
        return $this;
    }

    /**
     * @param string $audienceType1
     * @param string $audienceType2
     * @param array $checkboxes
     * @return Form
     */
    public function createCompositeAudience(string $audienceType1, string $audienceType2, array $checkboxes): self
    {
            $this->tester->scrollTo(['css' => 'button.button.icon-drag-drop']);
            $this->tester->click(['css' => 'button.button.icon-drag-drop'], ['css' => 'div#audience-selectize-group']);
            $this->tester->waitForText('Roles');
            $this->tester->click('Create Composite Audience');
            $this->tester->waitForText("Choose audience types and select the appropriate tickboxes to create a composite audience. E.g. Parents and Students only in the Senior Campus.");
            $this->tester->selectOption('//*[@id="audience-browse"]/div[3]/div[2]/div[1]/legend/select', $audienceType1);
            $this->tester->selectOption('//*[@id="audience-browse"]/div[3]/div[2]/div[2]/legend/select', $audienceType2);
            $this->tickCheckboxes($checkboxes);
            $this->tester->click(Locator::lastElement('button.button.submit'));
            sleep(2);
            return $this;
    }

    /**
     * tick the checkboxes
     * @param array $checkboxes
     */
    public function tickCheckboxes(array $checkboxes): void
    {
        foreach ($checkboxes as &$checkbox) {
            $this->tester->click('label[for="' . $checkbox . '"]');
        }
    }

    /**
     * @param Alaress_Date_Format $publishDate
     * @return Form
     */
    public function fillPublishDate(Alaress_Date_Format $publishDate): self
    {
        // Type in database format, let strtotime do the work
        // This is because Codeception has no access to the DB config
        $this->tester->fillDateField('input#publishAt', $publishDate->getDatabase());
        return $this;
    }

    /**
     * @param Alaress_Date_Format $publishDate
     * @return Form
     */
    public function seePublishDate(Alaress_Date_Format $publishDate): self
    {
        $this->tester->seeInField(
            'input#publishAt',
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
        $this->tester->fillDateField('input#expiresAt', $expiryDate->getDatabase());
        return $this;
    }

    /**
     * @param Alaress_Date_Format $expiryDate
     * @return Form
     */
    public function seeExpiryDate(Alaress_Date_Format $expiryDate): self
    {
        $this->tester->seeInField(
            'input#expiresAt',
            $expiryDate->getUsingFormat(Common::FORMAT_INPUT_DATETIME)
        );
        return $this;
    }
}
