<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Comms\News\Validation;

use Schoolbox\Comms\News;
use Alaress\Specification;

/**
 * Ensures an article has:
 * * a title
 * * publish date
 * * expiry data
 * * publish <= expiry
 */
class ValidArticleAttributes extends Specification\Leaf
{

    /**
     * Definition of a valid news article
     *
     * @param News\Model $article
     * @return bool
     */
    public function isSatisfiedBy($article): bool
    {
        /* A valid article consists of title, publish and expiry dates
         * Publish must occur before, or equal to, expiry
         */
        $hasTitle =  '' !== trim($article->getTitle() ?? '');
        $hasPublishDate = $article->getPublishAt() !== null && !$article->getPublishAt()->isNull();
        $hasExpiryDate = $article->getExpiresAt() !== null && !$article->getExpiresAt()->isNull();

        return $hasTitle && $hasPublishDate && $hasExpiryDate && $article->getPublishAt()->lte($article->getExpiresAt());
    }
}
