import AcceptanceTester from "@schoolboxTest/playwright/support/AcceptanceTester";
import { Page, expect } from "@playwright/test";
import path from "path";

export default class Form {
    private tester: AcceptanceTester;

    private page: Page;

    static readonly URL = "/news/create";

    static readonly ARTICLE_TITLE_CKEDITOR = 'input[name="title"]';

    static readonly ARTICLE_BODY_CKEDITOR = 'textarea[name="body"]';

    static readonly LINK_ADD_NEWS = ".icon-add";

    static readonly FORM = "#newsForm";

    static readonly FORM_SAVE = "#news-submit";

    static readonly ARTICLE_HEADLINE = "title";

    static readonly DATA_TEST_ARTICLE_BODY = "news-article-form-body";

    static readonly DATA_TEST_ARTICLE_BLURB = "news-article-form-blurb";

    private filePath?: string;

    constructor(tester: AcceptanceTester, page: Page) {
        this.tester = tester;
        this.page = page;
    }

    /**
     * Navigates to the create route
     */
    async goto(): Promise<void> {
        await this.page.goto(Form.URL);
    }

    /**
     * submit the form
     */
    async submit(): Promise<void> {
        await this.page.locator(Form.FORM_SAVE).isEnabled({ timeout: 30000 });
        await this.page.locator(Form.FORM_SAVE).click();
    }

    /**
     * fill headline
     * @param headline - The headline to fill
     */
    async fillHeadline(headline: string): Promise<void> {
        await this.page.fill(Form.ARTICLE_TITLE_CKEDITOR, headline);
    }

    /**
     * see headline
     * @param headline - The headline to see
     */
    async seeHeadline(headline: string): Promise<void> {
        await expect(this.page.locator(Form.ARTICLE_HEADLINE)).toHaveText(headline);
    }

    /**
     * fill article
     * @param article - The article to fill
     */

    async fillArticle(articleBody: string): Promise<void> {
        const body = this.page.getByTestId(Form.DATA_TEST_ARTICLE_BODY);
        await this.tester.fillCKEditorByLocator(body, articleBody);
    }

    /**
     * see article
     * @param headline - The article to see
     */
    async seeArticle(headline: string): Promise<void> {
        await expect(this.page.locator(Form.DATA_TEST_ARTICLE_BODY)).toHaveText(headline);
    }

    /**
     * fill blurb
     * @param blurbBody - The blurb to fill
     */
    async fillBlurb(blurbBody: string): Promise<void> {
        await this.page.locator("legend.icon-down-arrow").click();
        const blurb = this.page.getByTestId(Form.DATA_TEST_ARTICLE_BLURB);
        await this.tester.fillCKEditorByLocator(blurb, blurbBody);
    }

    /**
     * see blurb
     * @param blurb - The blurb to see
     */
    async seeBlurb(blurb: string): Promise<void> {
        await this.page.locator("legend.icon-down-arrow").click();
        await expect(this.page.locator("blurb")).toHaveText(blurb);
    }

    /**
     * fill feature image
     * @param filePath - The filename to fill
     */
    async attachFeatureImage(filePath: string): Promise<void> {
        // await this.page.setInputFiles("#featureImage-zone", filename);

        const attachZone = this.page.locator(`div[id="FeatureImageZone"]`);
        await this.tester.dragAndDropFile(attachZone, filePath);
        this.filePath = filePath;
    }

    async setAttachment(filePath: string): Promise<void> {
        const attachZone = this.page.locator("#FileZone");
        await this.tester.dragAndDropFile(attachZone, filePath);
        this.filePath = filePath;
    }

    /**
     * see feature image
     * @param filename - The filename to see
     */
    async seeFeatureImage(filename: string): Promise<void> {
        await expect(this.page.locator("#featureImage-zone")).toHaveValue(filename);
    }

    /**
     * fill topic
     * @param topic - The topic to fill
     */
    async fillTopic(topic: string): Promise<void> {
        await this.page.locator("input#news-topics-selectized").click();
        await this.page.locator(`div.option:has-text("${topic}")`).click();
        // await this.page.locator("div.option").click();
    }

    /**
     * see topic
     * @param topic - The topic to see
     */
    async seeTopic(topic: string): Promise<void> {
        await expect(this.page.locator("div.item")).toHaveText(topic);
    }

    /**
     * select audience option
     * @param audienceBrick - The audience brick
     * @param audienceType - The audience type
     */
    async selectAudienceOption(audienceBrick: string, audienceType: string): Promise<void> {
        await this.page.locator("button.button.icon-drag-drop").click();
        await expect(this.page.locator('div[class="reveal-modal no-pad open"]')).toBeVisible();
        await this.page.locator(`button.button-block:has-text("${audienceBrick}")`).click();
        await expect(this.page.locator('label[for="audience-list-input-IjEi"]')).toBeVisible();
        await this.page.locator(`label[for="audience-list-input-IjEi"]:has-text("${audienceType}")`).click();
        await this.page.locator("button.button.submit").first().click();
        await this.page.waitForTimeout(2000);
    }
}

/**
 * News form page object
 *
 * This serves the dual purpose of a create and modify news page.
 */

// <?php

// /**
//  * @author    Amri Sannang <amri.sannang@alaress.com.au>
//  * @copyright Copyright 2019, Schoolbox Pty Ltd
//  * @link      https://schoolbox.com.au/
//  */

// declare(strict_types=1);

// namespace Tests\Support\Page\News;

// use Tests\Support\AcceptanceTester;
// use Alaress_Date_Format;
// use Codeception\Util\Locator;
// use Tests\Support\Page\Common;

// /**
//  * News form page object
//  *
//  * This serves the dual purpose of a create and modify news page.
//  */
// class Form
// {
//     public const URL = '/news/create';

//     // Selectors

//     // TODO all of these should have better selectors
//     // TODO all of these belong in the main content of a page (#content)

//     public const LINK_ADD_NEWS = '.icon-add';

//     // No crumbtrail on this page?
//     public const FORM = Common::ELEMENT_CONTENT . ' #newsForm';

//     // Not sure if every input should have its own selector constant
//     public const FORM_SAVE = '#news-submit';

//     /**
//      * @var AcceptanceTester
//      */
//     protected $tester;

//     /**
//      * Constructor
//      *
//      * @param AcceptanceTester $i
//      */
//     public function __construct(AcceptanceTester $i)
//     {
//         $this->tester = $i;
//     }

//     /**
//      * Generates route for this page.
//      *
//      * @param string $id
//      * @return string
//      */
//     private function generateRoute(string $id): string
//     {
//         return str_replace('{id}', $id, self::URL);
//     }

//     /**
//      * Generates create route for this page.
//      *
//      * @return string
//      */
//     public function generateCreateRoute(): string
//     {
//         return $this->generateRoute('');
//     }

//     /**
//      * Generates modify route for this page.
//      *
//      * @param int $id
//      * @return string
//      */
//     public function generateModifyRoute(int $id): string
//     {
//         return $this->generateRoute((string)$id);
//     }

//     /**
//      * @return Form
//      */
//     public function submit(): self
//     {
//         $this->tester->waitForElementVisible(self::FORM_SAVE);
//         $this->tester->click(self::FORM_SAVE);
//         return $this;
//     }

//     /**
//      * @param string $headline
//      * @return Form
//      */
//     public function fillHeadline(string $headline): self
//     {
//         $this->tester->fillField('title', $headline);
//         return $this;
//     }

//     /**
//      * @param string $headline
//      * @return Form
//      */
//     public function seeHeadline(string $headline): self
//     {
//         $this->tester->seeInField('title', $headline);
//         return $this;
//     }

//     /**
//      * @param string $article
//      * @return Form
//      */
//     public function fillArticle(string $article): self
//     {
//         $this->tester->fillRichTextField('body', $article);
//         return $this;
//     }

//     /**
//      * @param string $headline
//      * @return Form
//      */
//     public function seeArticle(string $headline): self
//     {
//         $this->tester->seeInRichTextField('body', $headline);
//         return $this;
//     }

//     /**
//      * @param string $blurb
//      * @return Form
//      */
//     public function fillBlurb(string $blurb): self
//     {
//         // Expand blurb pane, and then enter it in
//         $this->tester->click('legend.icon-down-arrow');
//         $this->tester->fillRichTextField('blurb', $blurb);
//         return $this;
//     }

//     /**
//      * @param string $blurb
//      * @return Form
//      */
//     public function seeBlurb(string $blurb): self
//     {
//         // Expand blurb pane, and then ensure I see its contents
//         $this->tester->click('legend.icon-down-arrow');
//         $this->tester->seeInRichTextField('blurb', $blurb);
//         return $this;
//     }

//     /**
//      * @param string $filename
//      * @return Form
//      */
//     public function fillFeatureImage(string $filename): self
//     {
//         $this->tester->attachAttachzoneFile('#featureImage-zone', $filename);
//         return $this;
//     }

//     /**
//      * @param string $filename
//      * @return Form
//      */
//     public function seeFeatureImage(string $filename): self
//     {
//         $this->tester->seeAttachzoneFile('#featureImage-zone', $filename);
//         return $this;
//     }

//     /**
//      * @param string $topic
//      * @return Form
//      */
//     public function fillTopic(string $topic): self
//     {
//         $this->tester->click('input#news-topics-selectized');
//         $this->tester->executeJS("var scrollableDiv = document.getElementsByClassName('selectize-dropdown multi plugin-remove_button');
//         scrollableDiv.scrollTop=scrollableDiv.scrollHeight - scrollableDiv.offsetHeight;");
//         $element = Locator::contains('div.option', $topic);
//         $this->tester->click($element);
//         return $this;
//     }

//     /**
//      * @param string $topic
//      * @return Form
//      */
//     public function seeTopic(string $topic): self
//     {
//         $this->tester->canSee($topic, 'div.item');
//         return $this;
//     }

//     /**
//      * this is to click an audience type: Campus, Teams, Classes, Roles etc
//      * @param string $audienceBrick
//      * @param string $audienceType
//      * @param array $checkboxOption
//      * @return Form
//      */
//     public function selectAudienceOption(string $audienceBrick, string $audienceType, array $checkboxOption): self
//     {
//             $this->tester->scrollTo(['css' => 'button.button.icon-drag-drop']);
//             $this->tester->click(['css' => 'button.button.icon-drag-drop'], ['css' => 'div#audience-selectize-group']);
//             $this->tester->waitForText('Roles');
//             $element = Locator::contains('button.button-block', $audienceBrick);
//             $this->tester->clickWithLeftButton($element);
//             $this->tester->executeJS(
//                 "var scrollableDiv = document.getElementsByClassName('checklist checklist-container');
//             scrollableDiv.scrollTop=scrollableDiv.scrollHeight - scrollableDiv.offsetHeight;"
//             );
//             $this->tester->waitForText($audienceType, 5);
//             $audience_element = Locator::contains('div.checklist-wrapper', $audienceType);
//             $this->tester->clickWithLeftButton($audience_element);
//             $this->tickCheckboxes($checkboxOption);
//             $this->tester->click('button.button.submit');
//             sleep(2);
//              return $this;
//     }

//     /**
//      * @param array $roles
//      * @return Form
//      */
//     public function fillRoles(array $roles): self
//     {
//         foreach ($roles as &$role) {
//             $this->tester->scrollTo(['css' => 'button.button.icon-drag-drop']);
//             $this->tester->click(['css' => 'button.button.icon-drag-drop'], ['css' => 'div#audience-selectize-group']);
//             $this->tester->waitForText('Roles');
//             $element = Locator::contains('button.button-block', 'Roles');
//             $this->tester->clickWithLeftButton($element);
//             $this->tester->executeJS(
//                 "var scrollableDiv = document.getElementsByClassName('checklist checklist-container');
//             scrollableDiv.scrollTop=scrollableDiv.scrollHeight - scrollableDiv.offsetHeight;"
//             );
//             $this->tester->waitForText($role, 5);
//             $teacher_element = Locator::contains('div.checklist-wrapper', $role);
//             $this->tester->clickWithLeftButton($teacher_element);
//             $this->tester->click('button.button.submit');
//             sleep(2);
//         }
//         return $this;
//     }

//     /**
//      * @param array $roles
//      * @return Form
//      */
//     public function seeRoles(array $roles): self
//     {
//         foreach ($roles as &$role) {
//             $this->tester->canSee($role);
//         }
//         return $this;
//     }

//     /**
//      * @param array $campuses
//      * @return Form
//      */
//     public function fillCampuses(array $campuses): self
//     {
//         foreach ($campuses as &$campus) {
//             $this->tester->scrollTo(['css' => 'button.button.icon-drag-drop']);
//             $this->tester->click(['css' => 'button.button.icon-drag-drop'], ['css' => 'div#audience-selectize-group']);
//             $this->tester->waitForText('Year Levels');
//             $element = Locator::contains('button.button-block', 'Campuses');
//             $this->tester->clickWithLeftButton($element);
//             $this->tester->executeJS("var scrollableDiv = document.getElementsByClassName('checklist checklist-container');
//             scrollableDiv.scrollTop=scrollableDiv.scrollHeight - scrollableDiv.offsetHeight;");
//             $this->tester->waitForText('Secondary', 5);
//             $campus_element = Locator::contains('div.checklist-wrapper', $campus);
//             $this->tester->clickWithLeftButton($campus_element);
//             $this->tester->click('button.button.submit');
//             sleep(2);
//         }

//         return $this;
//     }

//     /**
//      * @param array $campuses
//      * @return Form
//      */
//     public function seeCampuses(array $campuses): self
//     {
//         foreach ($campuses as &$campus) {
//             $this->tester->canSee($campus);
//         }
//         return $this;
//     }

//     /**
//      * @param array $users
//      * @return Form
//      */
//     public function fillUsers(array $users): self
//     {
//         foreach ($users as &$user) {
//             $this->tester->click('input#audience-search-selectized');
//             $user_chars = str_split($user);
//             foreach ($user_chars as &$user_char) {
//                 $this->tester->pressKey('input#audience-search-selectized', $user_char);
//             }
//             $this->tester->pressKey('input#audience-search-selectized', \Facebook\WebDriver\WebDriverKeys::ENTER);
//         }
//         return $this;
//     }

//     /**
//      * @param array $users
//      * @return Form
//      */
//     public function seeUsers(array $users): self
//     {
//         foreach ($users as &$user) {
//             $this->tester->canSee($user);
//         }
//         return $this;
//     }

//     /**
//      * @param string $audienceType1
//      * @param string $audienceType2
//      * @param array $checkboxes
//      * @return Form
//      */
//     public function createCompositeAudience(string $audienceType1, string $audienceType2, array $checkboxes): self
//     {
//             $this->tester->scrollTo(['css' => 'button.button.icon-drag-drop']);
//             $this->tester->click(['css' => 'button.button.icon-drag-drop'], ['css' => 'div#audience-selectize-group']);
//             $this->tester->waitForText('Roles');
//             $this->tester->click('Create Composite Audience');
//             $this->tester->waitForText("Choose audience types and select the appropriate tickboxes to create a composite audience. E.g. Parents and Students only in the Senior Campus.");
//             $this->tester->selectOption('//*[@id="audience-browse"]/div[3]/div[2]/div[1]/legend/select', $audienceType1);
//             $this->tester->selectOption('//*[@id="audience-browse"]/div[3]/div[2]/div[2]/legend/select', $audienceType2);
//             $this->tickCheckboxes($checkboxes);
//             $this->tester->click(Locator::lastElement('button.button.submit'));
//             sleep(2);
//             return $this;
//     }

//     /**
//      * tick the checkboxes
//      * @param array $checkboxes
//      */
//     public function tickCheckboxes(array $checkboxes): void
//     {
//         foreach ($checkboxes as &$checkbox) {
//             $this->tester->click('label[for="' . $checkbox . '"]');
//         }
//     }

//     /**
//      * @param Alaress_Date_Format $publishDate
//      * @return Form
//      */
//     public function fillPublishDate(Alaress_Date_Format $publishDate): self
//     {
//         // Type in database format, let strtotime do the work
//         // This is because Codeception has no access to the DB config
//         $this->tester->fillDateField('input#publishAt', $publishDate->getDatabase());
//         return $this;
//     }

//     /**
//      * @param Alaress_Date_Format $publishDate
//      * @return Form
//      */
//     public function seePublishDate(Alaress_Date_Format $publishDate): self
//     {
//         $this->tester->seeInField(
//             'input#publishAt',
//             $publishDate->getUsingFormat(Common::FORMAT_INPUT_DATETIME)
//         );
//         return $this;
//     }

//     /**
//      * @param Alaress_Date_Format $expiryDate
//      * @return Form
//      */
//     public function fillExpiryDate(Alaress_Date_Format $expiryDate): self
//     {
//         $this->tester->fillDateField('input#expiresAt', $expiryDate->getDatabase());
//         return $this;
//     }

//     /**
//      * @param Alaress_Date_Format $expiryDate
//      * @return Form
//      */
//     public function seeExpiryDate(Alaress_Date_Format $expiryDate): self
//     {
//         $this->tester->seeInField(
//             'input#expiresAt',
//             $expiryDate->getUsingFormat(Common::FORMAT_INPUT_DATETIME)
//         );
//         return $this;
//     }

//     /**
//      * tick the checkboxes
//      * @param array $titles
//      */
//     public function removeAudience(array $titles): void
//     {
//         foreach ($titles as &$title) {
//             $this->tester->click('a[title="Remove ' . $title . ' from audience"]');
//         }
//     }
// }
// import AcceptanceTester from "@schoolboxTest/playwright/support/AcceptanceTester";
// import { Page } from "@playwright/test";

// export default class PastoralProfile {
//     private tester: AcceptanceTester;

//     private page: Page;

//     static readonly URL = "/pastoral/student/{studentId}";

//     static readonly DELETE_COMMENT_ICON = 'a[href="#delete-comment"]';

//     static readonly PASTORAL_POST_COMMENT = "#submit-for-comment-public";

//     static readonly CREATE_PASTORAL_BUTTON = "a[title='Create Pastoral Record']";

//     static readonly PASTORAL_RECORD = "a[href='/pastoral/student/{studentId}/record/{recordId}']";

//     static readonly PASTORAL_RECORD_UPDATE = "a[href='/pastoral/student/{studentId}/record/{recordId}/update']";

//     constructor(tester: AcceptanceTester, page: Page) {
//         this.tester = tester;
//         this.page = page;
//     }

//     /**
//      * Navigates to the profile page
//      * @param id - The user id
//      */
//     async goto(id: string): Promise<void> {
//         await this.page.goto(PastoralProfile.URL.replace("{studentId}", id.toString()));
//     }

//     /**
//      * click Create Pastoral Record button
//      */
//     async clickCreatePastoralRecord(): Promise<void> {
//         await this.page.click(PastoralProfile.CREATE_PASTORAL_BUTTON);
//     }

//     /**
//      * click Modify Pastoral Record icon
//      * @param studentId - The student id
//      * @param recordId - The record id
//      */
//     async clickModifyIcon(studentId: string, recordId: number): Promise<void> {
//         await this.page.click(
//             PastoralProfile.PASTORAL_RECORD_UPDATE.replace("{studentId}", studentId.toString()).replace(
//                 "{recordId}",
//                 recordId.toString(),
//             ),
//         );
//     }

//     /**
//      * click Delete Pastoral Record icon
//      * @param recordId - The record id
//      */
//     async clickDeleteIcon(recordId: number): Promise<void> {
//         await this.page.click(`a[id="${recordId}"]`);
//     }

//     /**
//      * click a Pastoral Record
//      * @param studentId - The student id
//      * @param recordId - The record id
//      */
//     async clickPastoralRecord(studentId: string, recordId: number): Promise<void> {
//         await this.page.click(
//             PastoralProfile.PASTORAL_RECORD.replace("{studentId}", studentId.toString()).replace(
//                 "{recordId}",
//                 recordId.toString(),
//             ),
//         );
//     }

//     /**
//      * click delete comment icon for learning moments, news, pastoral care records
//      */
//     async clickDeleteCommentIcon(): Promise<void> {
//         await this.page.click(PastoralProfile.DELETE_COMMENT_ICON);
//     }

//     /**
//      * click post comment button
//      */
//     async clickPostComment(): Promise<void> {
//         await this.page.click(PastoralProfile.PASTORAL_POST_COMMENT);
//     }
// }
