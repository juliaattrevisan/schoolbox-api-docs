<?php

/**
 * @copyright   Copyright 2026, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\CustomisationRecord;

use Alaress\Web\Controller\Base;

class Controller extends Base
{
    /**
     * Controller constructor
     */
    public function __construct()
    {
        // Controller initialization code can go here
    }

    public function viewRecords(): void
    {
        // Logic to view customisation records goes here
        $this->responseFromTemplate('@customisationRecord/list.twig', [
            // Pass any necessary data to the template here
        ]);
    }
}
