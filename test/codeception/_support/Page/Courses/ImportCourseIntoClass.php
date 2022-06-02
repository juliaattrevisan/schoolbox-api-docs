<?php

declare(strict_types=1);

namespace Page\Courses;

use AcceptanceTester;
use Alaress_Date_Format;

class ImportCourseIntoClass
{


    private AcceptanceTester $tester;

    public const CLASS_NAME = 'input.search-input';


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
     * type a class
     */
    public function typeClassName($className): void
    {

        $this->tester->fillField(self::CLASS_NAME, $className);
    }

    /**
     * clicking the class name
     */
    public function clickClassName(): void
    {
        $this->tester->click('button.search-result');
    }

    //UNITS
    /**
     * click a unit checkbox
     */
    public function tickUnitCheckbox($checkboxNumber): void
    {
        $this->tester->click(['css' => 'label[class=checkbox-label][for=import-' . $checkboxNumber . ']']);
    }

    /**
     * @param Alaress_Date_Format $openDate
     * @return ImportCourseIntoClass
     */
    public function fillOpenDate($openDateIdNumber, Alaress_Date_Format $openDate): self
    {
        $this->tester->fillDateField(['css' => 'input[id=open_date-' . $openDateIdNumber . ']'], $openDate->getDatabase());
        return $this;
    }

    /**
     * type a due date
     */
    public function insertDueDate($dueDateIdNumber, $dueDate): void
    {

        $this->tester->fillField(['css' => 'input[id=due_date-' . $dueDateIdNumber . ']'], $dueDate);
    }

    //COURSEWORK
    /**
     * click an assessment checkbox
     */
    public function tickAssessmentCheckbox($checkboxNumber): void
    {
        $this->tester->click(['css' => 'label[class=checkbox-label][for=a_import-' . $checkboxNumber . ']']);
    }

    /**
     * click submit or next
     */
    public function clickSubmit($buttonValue): void
    {

        $this->tester->click(['css' => 'input[type=submit][value=' . $buttonValue . ']']);
    }
}
