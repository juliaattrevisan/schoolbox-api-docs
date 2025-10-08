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
use Schoolbox\UserMapper;

class Controller extends Base
{
    /**
     * Controller constructor.
     *
     * @param ErrorMapper $errorMapper
     * @param Mapper $templateMapper
     */
    public function __construct(
        private ErrorMapper $errorMapper,
        private Mapper $templateMapper,
        private UserMapper $userMapper
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
            $templates = $this->templateMapper->findByColumn('category_id', $categoryId);
            $translator = new ModelToArray(
                new \Schoolbox\User\Translator\Author\ModelToArray(),
                $this->userMapper,
            );
            $this->response = $this->responseAsJson($templates->map($translator));
        } catch (\Schoolbox\Mapper\Exception $e) {
            throw $this->errorMapper->generateException(ErrorMapper::ERROR_PROCESSING);
        }
    }
}
