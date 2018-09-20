<?php

/**
 * TemplateSelector
 *
 * Select respective twig template for news
 */

namespace Schoolbox\Comms\News;

class TemplateSelector
{

    private const ARTICLE_LISTS_TEMPLATE = 'article-lists';

    private const EMPTY_STATE_TEMPLATE = 'empty-state';

    private const TEMPLATE_FILE_EXTENSION = 'twig';

    private const TEMPLATE_PREFIX = '@commsNews';


    public function getArticleListsTemplate($articles, $emptyStateTemplate = 'default') :string
    {
        $templateFile = self::ARTICLE_LISTS_TEMPLATE;

        if (\count($articles) < 1) {
            $templateFile = self::EMPTY_STATE_TEMPLATE . '-' . $emptyStateTemplate;
        }

        return self::TEMPLATE_PREFIX . '/' . $templateFile . '.' . self::TEMPLATE_FILE_EXTENSION;
    }
}
