import { Page } from "@playwright/test";

export default class SurveyForm {
    private page: Page;

    static readonly URL = "/survey/create/{class_id}";

    static readonly FORM = "#createAssess";

    static readonly SELECTOR_TITLE = `${SurveyForm.FORM} input[name="title"]`;

    static readonly SELECTOR_OPEN_DATE = `${SurveyForm.FORM} input[name="openDate"]`;

    static readonly SELECTOR_DUE_DATE = `${SurveyForm.FORM} input[name="dueDate"]`;

    static readonly SELECTOR_BUTTON_CREATE = `input[type=submit][value="Create"]`;

    static readonly SELECTOR_BUTTON_UPDATE = `${SurveyForm.FORM} [type=submit][value="Update"]`;

    constructor(page: Page) {
        this.page = page;
    }

    /**
     * Generates route for this page.
     * @param classId - The ID of the page
     */
    async goto(classId: number): Promise<void> {
        await this.page.goto(SurveyForm.generateRoute(classId));
    }

    /**
     * Generates route for this page
     * @param classId - The ID of the page
     * @returns - the URL for the page
     */
    public static generateRoute(classId: number): string {
        return SurveyForm.URL.replace("{class_id}", classId.toString());
    }

    /**
     * Sets title
     * @param title - The title of the survey
     */
    async setTitle(title: string): Promise<void> {
        await this.page.locator(`h1:has-text("CREATE SURVEY")`).isVisible();
        await this.page.fill(SurveyForm.SELECTOR_TITLE, title);
    }

    /**
     * Sets open date
     * @param openDate - The open date of the survey
     */
    async setOpenDate(openDate: string): Promise<void> {
        await this.page.fill(SurveyForm.SELECTOR_OPEN_DATE, openDate);
    }

    /**
     * Submits this form to create a survey
     */
    async create(): Promise<void> {
        await this.page.click(SurveyForm.SELECTOR_BUTTON_CREATE);
    }

    /**
     * Submits this form to update a survey
     */
    async update(): Promise<void> {
        await this.page.click(SurveyForm.SELECTOR_BUTTON_UPDATE);
    }
}
// <?php

// /**
//  * @copyright Copyright 2019, Schoolbox Pty Ltd
//  * @link      https://schoolbox.com.au/
//  */

// declare(strict_types=1);

// namespace Tests\Support\Page\Survey;

// use Tests\Support\AcceptanceTester;
// use Alaress_Date_Format;
// use Codeception\Util\Locator;

// /**
//  * Survey form page object
//  */
// class SurveyForm
// {
//     public const URL = '/survey/create/{class_id}';

//     public const FORM = '#createAssess';

//     // Not sure if every input should have its own selector constant
//     public const SELECTOR_TITLE = self::FORM . ' input[name="title"]';
//     public const SELECTOR_OPEN_DATE = self::FORM . ' input[name="openDate"]';
//     public const SELECTOR_DUE_DATE = self::FORM . ' input[name="dueDate"]';
//     public const SELECTOR_BUTTON_CREATE = self::FORM . ' input[type=submit][value="Create"]';
//     public const SELECTOR_BUTTON_UPDATE = self::FORM . ' [type=submit][value="Update"]';

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
//      * @param string $classId
//      * @return string
//      */
//     public function generateRoute(string $classId): string
//     {
//         return str_replace('{class_id}', $classId, self::URL);
//     }

//     /**
//      * Navigates to create survey form
//      * @param string $classId
//      * @return SurveyForm
//      */
//     public function openCreate(string $classId): self
//     {
//         $i = $this->tester;
//         $i->amOnPage(self::generateRoute($classId));
//         return $this;
//     }

//     /**
//      * Submits this form to create a survey
//      * @return SectionForm
//      */
//     public function create(): SectionForm
//     {
//         $i = $this->tester;
//         $i->waitForElement(self::SELECTOR_BUTTON_CREATE);
//         $i->scrollTo(['css' => self::SELECTOR_BUTTON_CREATE]);
//         $i->waitForElementVisible(['css' => self::SELECTOR_BUTTON_CREATE], 5);
//         //$i->click(['css' => 'input[type=submit][value="Create"]']);
//         $i->click(['css' => self::SELECTOR_BUTTON_CREATE]);
//         $i->waitForElement('input[name="assess_id"]');
//         return new SectionForm($i);
//     }

//     /**
//      * Submits this form to update a quiz
//      * @return SurveyForm
//      */
//     public function update(): self
//     {
//         $this->tester->click(self::SELECTOR_BUTTON_UPDATE);
//         return $this;
//     }

//     /**
//      * Sets title
//      * @param string $title
//      * @return SurveyForm
//      */
//     public function setTitle(string $title): self
//     {
//         $this->tester->fillField(self::SELECTOR_TITLE, $title);
//         return $this;
//     }

//     /**
//      * Sets open date
//      * @param Alaress_Date_Format $openDate
//      * @return SurveyForm
//      */
//     public function setOpenDate(Alaress_Date_Format $openDate): self
//     {
//         // Type in database format, let strtotime do the work
//         // This is because Codeception has no access to the DB config
//         $this->tester->fillDateField(self::SELECTOR_OPEN_DATE, $openDate->getDatabase());
//         return $this;
//     }
// }
// import { Page } from "@playwright/test";

// export default class QuestionBankPage {
//     private page: Page;

//     static readonly URL = "/survey/questionbank/contents/{category_id}/{assess_id}/{section_id}";

//     static readonly CATEGORY_ELECTIVE_SELECTION = 7;

//     static readonly CATEGORY_MEDICAL = 9;

//     constructor(page: Page) {
//         this.page = page;
//     }

//     /**
//      * Generates route for this page.
//      * @param assessId - The ID of the page
//      */
//     async goto(assessId: number): Promise<void> {
//         await this.page.goto(QuestionBankPage.generateRoute(assessId));
//     }

//     /**
//      * Generates route for this page
//      * @param assessId - The ID of the page
//      * @returns - the URL for the page
//      */
//     public static generateRoute(assessId: number): string {
//         return QuestionBankPage.URL.replace("{assessId}", assessId.toString());
//     }

//     /**
//      * Selects question bank category
//      * @param categoryId - The ID of the category
//      */
//     async clickCategory(categoryId: number): Promise<void> {
//         await this.page.click(`#node_${categoryId}_anchor`);
//         await this.page.locator(`h2.subheader:has-text("ELECTIVE SELECTION")`).isVisible();
//     }

//     /**
//      * Clicks modify button of a question in question bank
//      * @param question - The question to modify
//      */
//     async clickModify(question: string): Promise<void> {
//         const questionListItem = this.page
//             .locator("#questionBankForm > section > ul > li")
//             .filter({ hasText: question });
//         await questionListItem.locator("nav > a.icon-modify").click();
//     }
// }
