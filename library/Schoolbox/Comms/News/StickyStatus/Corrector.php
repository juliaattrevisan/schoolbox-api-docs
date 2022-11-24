<?php

/**
 * @copyright Copyright 2022, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Comms\News\StickyStatus;

use Schoolbox\Comms\News\Model;

/**
 * Corrector for an article's sticky status.
 *
 * If the sticky config setting is disabled: set the sticky status to false.
 */
class Corrector
{
    /**
     * Constructor
     *
     * @param bool $configStickyEnabled Is the sticky config setting enabled?
     */
    public function __construct(private bool $configStickyEnabled)
    {
    }

    /**
     * Correct the article's sticky status.
     *
     * @param Model $article
     * @return Model `$article`, with the sticky status set to false if the
     *               config is disabled.
     */
    public function __invoke(Model $article): Model
    {
        if (!$this->configStickyEnabled) {
            return $article->setSticky(false);
        }
        return $article;
    }
}
