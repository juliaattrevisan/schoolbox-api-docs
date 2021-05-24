<?php

/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Comms\News\Moderation;

use Schoolbox\Comms;

use function array_merge;
use function array_unique;

class MockUserResolver implements IUserResolver
{
    public const ARTICLE_MODERATORS = [
        0  => [],
        1  => [],
        2  => [151],
        3  => [151],
        4  => [151],
        5  => [151],
        6  => [],
        7  => [],
        8  => [],
        9  => [],
        10 => [247],
    ];


    /** @inheritDoc */
    public function findModeratorsForArticle(Comms\News\Model $article): array
    {
        return array_unique(array_merge(
            $this->getSuperuserIds(),
            $this->getGlobalNewsModerators(),
            $this->getAudienceModerators($article)
        ));
    }


    /** @inheritDoc */
    public function getGlobalNewsModerators(): array
    {
        return [13];
    }


    /** @inheritDoc */
    public function getSuperuserIds(): array
    {
        return [19];
    }


    /** @inheritDoc */
    public function getAudienceModerators(Comms\News\Model $article): array
    {
        return self::ARTICLE_MODERATORS[$article->getId()] ?? [];
    }
}
