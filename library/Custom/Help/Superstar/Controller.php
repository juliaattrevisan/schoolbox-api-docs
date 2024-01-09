<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Custom\Help\Superstar;

use Alaress\Web\Controller\Base;
use Twig\Error\Error;

class Controller extends Base
{
    /**
     * The name of this report which appears when you bookmark it
     * in yer browser
     */
    private const REPORT_TITLE = 'OLC Superstar Report';


    /**
     * Controller constructor.
     */
    public function __construct(
    ) {
    }


    /**
     * Show a list of people who could potentially be awarded Star or Superstar
     * badges for accomplishing 99% and 100% respectively, in their Units
     *
     * @throws Error
     */
    public function getCandidates(): void
    {
        $this->response = $this->responseFromTemplate('@superstar/index.twig', [
            'report' => [
                'title' => self::REPORT_TITLE,
            ]
        ], self::REPORT_TITLE);
    }


    /**
     * Award a badge to a particular person
     */
    public function postAward(): void
    {
    }
}
