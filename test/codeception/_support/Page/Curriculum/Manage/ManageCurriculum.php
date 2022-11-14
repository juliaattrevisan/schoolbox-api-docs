<?php

namespace Page\ManageCurriculum;

use AcceptanceTester;

class ManageCurriculum
{

    public const URL = '/curriculum';

    public const NEW_CURRICULUM_NAME = '#name';
    private AcceptanceTester $tester;

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
     *
     */
    public function fillNewCurriculumName($value): void
    {
        $this->tester->fillField(self::NEW_CURRICULUM_NAME, $value);
    }


}
