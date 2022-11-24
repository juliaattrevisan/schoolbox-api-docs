<?php

/**
 * @copyright Copyright 2022, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Comms\News\StickyStatus;

use Schoolbox\Comms\News\Model;

/**
 * Filter for an article's sticky status.
 *
 * If the sticky config setting is disabled: set the sticky status to false.
 */
class FilterStickyStatus
{
    /**
     * Constructor
     *
     * @param bool $configStickyEnabled Is the sticky config setting enabled?
     */
    public function __construct(
        private bool $configStickyEnabled,
    ) {
    }

    /**
     * @param Model $article
     * @return Model
     */
    public function filter(Model $article): Model
    {
        if (!$this->configStickyEnabled) {
            return $article->setSticky(false);
        }
        return $article;
    }
}
