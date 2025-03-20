<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2025, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Admin\BulkAssessmentResult;

use Alaress\Web\Controller\Base;

class Controller extends Base
{
    /**
     * Constructor.
     */
    public function __construct(
    ) {
    }


    /**
     * Provide input form for configuration and async upload for the csv file
     * Also link to a csv template that users can download and follow
     */
    public function getImport(): void
    {
        $this->response = $this->responseFromTemplate(
            '@adminBulkAssessmentResult/index.twig',
            [
            ],
            'Bulk Assessment Result'
        );
    }


    /**
     * Process in planning mode
     */
    public function postPreview(): void
    {
        $this->response = $this->responseFromTemplate(
            '@adminBulkAssessmentResult/preview.twig',
            [
            ],
            'Bulk Assessment Result Preview',
        );
    }


    /**
     * Process in perform mode
     */
    public function postPerform(): void
    {
        $this->response = $this->responseFromTemplate(
            '@adminBulkAssessmentResult/perform.twig',
            [
            ],
            'Bulk Assessment Result',
        );
    }
}
