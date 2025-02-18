<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2025, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Admin\BulkAssessmentResult;

use Alaress\Web\Controller\Base;
use Schoolbox\Admin\Navigation\Subnav;

class Controller extends Base
{
    /**
     * Constructor.
     */
    public function __construct(private \Schoolbox\Learning\Assessment\WorkType\Mapper $worktypeMapper) {
    }


    /**
     * Provide input form for configuration and async upload for the csv file
     * Also link to a csv template that users can download and follow
     */
    public function getIndex(): void
    {
        $this->response = $this->responseFromTemplate(
            '@adminBulkAssessmentResult/index.twig',
            [
                'pageCurrent' => $this->routeGenerator->generate('adminBulkAssessmentResult.getIndex'),
                'allBreadcrumbs' => (new Subnav($this->getConfigLoader(), $this->getUser()))->generateBreadcrumbs($this->routeGenerator->generate('adminBulkAssessmentResult.getIndex')),
                'worktypes' => $this->worktypeMapper->findAll()->sortNaturally('sequence')
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
