<?php

/**
 * @author      Michael Heydon <michael.heydon@schoolbox.education>
 * @copyright   Copyright 2025, Schoolbox Pty Ltd
 * @link        https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\Editor\Templates;

use Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\UserMapper;

class Helper
{
    public function __construct(
        private ErrorMapper $errorMapper,
        private Mapper $templateMapper,
        private UserMapper $userMapper
    ) {
    }

    /**
     * Get templates for a category
     *
     * @param int $categoryId
     * @return array|\Schoolbox\Collection
     * @throws \Exception
     */
    public function getTemplatesForCategory(int $categoryId)
    {
        // Validate categoryId is positive integer
        if ($categoryId <= 0) {
            throw $this->errorMapper->generateException(ErrorMapper::ERROR_INFORMATION_MISSING);
        }
        try {
            $templates = $this->templateMapper->findByColumn('category_id', $categoryId);
            $translator = new ModelToArray(
                new \Schoolbox\User\Translator\Author\ModelToArray(),
                $this->userMapper,
            );
            return $templates->map($translator);
        } catch (\Schoolbox\Mapper\Exception $e) {
            throw $this->errorMapper->generateException(ErrorMapper::ERROR_PROCESSING);
        }
    }
}
