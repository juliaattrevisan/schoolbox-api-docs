<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2024, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Iframe;

use Alaress\Web\Controller\Base;

class Controller extends Base
{
    /**
     * Controller constructor.
     */
    public function __construct() {
    }


    /**
     * Load an iframe with the specified url
     */
    public function getCivicaAttendance(): void
    {

        $this->response = $this->responseFromTemplate('@iframe/index.twig', [
            'url' => $this->requestQuery->get('url', base64_decode('aHR0cHM6Ly93d3cuc2Nob29sYm94LmVkdWNhdGlvbg=='))
        ], 'Civica Attendance');
    }
}
