<?php

/**
 * @author      Michael Heydon <michael.heydon@schoolbox.education>
 * @copyright   Copyright 2025, Schoolbox Pty Ltd
 * @link        https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\Editor\Templates;

use Schoolbox\GenericCollection;

class Helper
{
    public function __construct(
        private Mapper $templateMapper,
        private int $categoryId
    ) {
    }

    /**
     * Get templates for a category to display within the editor
     * If nothing can be found on the id an empty array is just
     * returned so the whole page doesn't error.
     *
     * @return array|\Schoolbox\Collection
     */
    public function getTemplatesForCategory()
    {
        try {
            $templates = $this->templateMapper->findByColumn('category_id', $this->categoryId);
            return $templates->map(function (Model $template) {
                return [
                    'title' => $template->getTitle(),
                    'description' => $template->getDescription(),
                    'body' => $template->getBody(),
                ];
            });
        } catch (\Schoolbox\Mapper\Exception $e) {
            // just return an empty array if nothing found
            return new GenericCollection(Model::class);
        }
    }
}
