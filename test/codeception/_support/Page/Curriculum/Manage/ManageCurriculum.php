<?php

namespace Page\Curriculum\Manage;

use AcceptanceTester;

class ManageCurriculum
{

    public const URL = '/curriculum';
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

}
