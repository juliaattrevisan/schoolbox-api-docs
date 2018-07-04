<?php
/**
 * ValidArticle
 *
 * Definition of a valid news article
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Comms\News;

class ValidArticle
{

    /**
     * Definition of a valid news article
     *
     * @param Model $article
     * @return bool
     */
    public function isSatisfiedBy(Model $article): bool
    {
        /* A valid article consists of title, publish and expiry dates
         */
        $hasTitle = '' !== trim($article->getTitle());
        $hasPublishDate = $article->getPublishAt() !== null && !$article->getPublishAt()->isNull();
        $hasExpiryDate = $article->getExpiresAt() !== null && !$article->getExpiresAt()->isNull();

        return $hasTitle && $hasPublishDate && $hasExpiryDate;
    }
}
