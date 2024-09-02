<?php

/**
 * @author      Justin Clarke <justin.clarke@schoolbox.education>
 * @copyright   Copyright 2024, Schoolbox Pty Ltd
 * @link        https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\Curriculum\Reports;

use Alaress\Web\Controller\Base;
use Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\Curriculum\Node\Mapper as CurriculumMapper;

class Controller extends Base
{
    /**
     * Controller constructor.
     *
     * @param ErrorMapper $errorMapper
     * @param CurriculumMapper  $curriculumMapper
     */
    public function __construct(
        private ErrorMapper $errorMapper,
        private CurriculumMapper $curriculumMapper
    ) {
    }


    /**
     * Get the coverage report
     */
    public function getCoverage(): void
    {
        $this->response = $this->responseFromTemplate('@curriculumreports/index.twig', ['var' => 'GET']);
    }


    /**
     * fetch the coverage report
     */
    public function postCoverage(): void
    {
        $this->response = $this->responseFromTemplate('@curriculumreports/index.twig', ['var' => 'POST']);
    }
}
