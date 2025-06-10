<?php

/**
 * @author      Michael Heydon <michael.heydon@schoolbox.education>
 * @copyright   Copyright 2025, Schoolbox Pty Ltd
 * @link        https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\Editor\Templates;

use Alaress\Web\Controller\Base;
use Schoolbox\Error\Mapper as ErrorMapper;

class Controller extends Base
{
    /**
     * Controller constructor.
     *
     * @param ErrorMapper $errorMapper
     * @param Mapper $mapper
     */
    public function __construct(
        private ErrorMapper $errorMapper,
        private Mapper $mapper,
    ) {
    }

    /**
     * Return a list of templates according to the category
     *
     * GET /editor/templates/{categoryId}
     */
    public function getTemplates(int $categoryId): void
    {

        // validate further...

        $templates = $this->mapper->findByColumn('category_id', $categoryId);
        $translator = new ModelToArray();

        $this->response = $this->responseAsJson($templates->map($translator));

    }

}
