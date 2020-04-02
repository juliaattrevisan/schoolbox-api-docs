<?php
/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Comms\News\Moderation;

class MockUserResolver implements IUserResolver
{
    /** @var int */
    private $defaultSet;


    /**
     * @param int $defaultSet
     */
    public function __construct(int $defaultSet = 0)
    {
        $this->defaultSet = $defaultSet;
    }

    public const ARTICLE_MODERATORS = [
        0  => [13,19],
        1  => [13,19],
        2  => [13,19,151],
        3  => [13,19,151],
        4  => [13,19,151],
        5  => [13,19,151],
        6  => [13,19],
        7  => [13,19],
        8  => [13,19],
        9  => [13,19],
        10 => [13,19,247],
    ];

    /**
     * Returns the ids of users who are capable of moderating this article
     *
     * @param \Schoolbox\Comms\News\Model $article
     * @param bool $includeFolderWriteUsers
     * @return int[]
     */
    public function findModeratorsForArticle(\Schoolbox\Comms\News\Model $article, bool $includeFolderWriteUsers = true): array
    {
        return $this->findUsers($article->getId(), self::ARTICLE_MODERATORS);
    }


    /**
     * @param int   $articleId
     * @param array $dataset
     * @return int[]
     */
    private function findUsers(int $articleId, array $dataset): array
    {
        if (\array_key_exists($articleId, $dataset)) {
            return $dataset[$articleId];
        }
        return $dataset[$this->defaultSet];

    }

    /**
     * Returns the user ids of all news moderators (users with FULL access to NEWS service)
     *
     * @return int[]
     */
    public function getGlobalNewsModerators(): array
    {
        return [13];
    }
}
