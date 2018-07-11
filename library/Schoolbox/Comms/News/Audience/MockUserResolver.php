<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Comms\News\Audience;

class MockUserResolver implements IUserResolver
{
    // Maps article ids to user ids
    // 1 => [ [role:3]^s && [year:10]^s ]^s
    // 2 => [user:3]^s || [user:10]^s || [user:151]^s || [user:230]^s
    // 3 => [user:230]^p
    // 4 => [user:230]^t
    // 5 => [super:1]^s
    // 6 => [ [role_type:staff]^s && [campus:1]^s ]^s
    // 7 => [school_house:3]^p
    // 8 => [school_house:3]^t
    // 9 => [ [class:ENG34]^s && [role:teachers]^s ]^s
    //10 => [ [school_house:3]^s && [year:10]^s ]^p
    public const ARTICLE_AUDIENCES = [
        1 => [10,15,17,31,222,223,224,225,226,227,228],
        2 => [3,10,151,230],
        3 => [74,75],
        4 => [3,24,154,158,248,19,151],
        5 => [14,19],
        6 => [3,13,14,19,24,96,144,145,146,147,148,149,150,151,152,153,154,155,156,157,158,159,160,246],
        7 => [20,36,37,38,41,42,43,44,47,55,56,58,59,60,62,63,64,65,66,67,68,69,70,76,77,84,85,88,89,92,93,97,98,109,110,116,123,124,128,132,135,136,137,138,139,140],
        8 => [3,13,19,24,144,145,146,148,150,151,152,154,155,156,158,160,247,248],
        9 => [3,24,248],
        10 => [88,89,20,36,123,124,128],
    ];


    /**
     * @inheritdoc
     */
    public function findUsersForArticle(int $articleId): array
    {
        if (\array_key_exists($articleId, self::ARTICLE_AUDIENCES)) {
            return self::ARTICLE_AUDIENCES[$articleId];
        }
        return [];
    }
}
