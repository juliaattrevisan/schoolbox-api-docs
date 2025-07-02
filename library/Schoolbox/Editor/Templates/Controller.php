<?php

/**
 * @author      Michael Heydon <michael.heydon@schoolbox.education>
 * @copyright   Copyright 2025, Schoolbox Pty Ltd
 * @link        https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\Editor\Templates;

use Alaress\Web\Controller\Base;

class Controller extends Base
{
    /**
     * Controller constructor.
     *
     * @param Helper $helper
     */
    public function __construct(
        private Helper $helper
    ) {
    }

    /**
     * Return a list of templates according to the category
     *
     * GET /editor/templates/{categoryId}
     */
    public function getTemplates(int $categoryId): void
    {
        try {
            $templatesArray = $this->helper->getTemplatesForCategory($categoryId);
            $this->response = $this->responseAsJson($templatesArray);
        } catch (\Exception $e) {
            throw $e;
        }
    }
}
