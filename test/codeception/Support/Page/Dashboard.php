<?php

/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Tests\Support\Page;

use Tests\Support\AcceptanceTester;

/**
 * Dashboard page object
 *
 * Not much to see here just yet, as the dashboard is completely user-configurable.
 */
class Dashboard
{
    const URL = '/';

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
}
