<?php
/**
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Comms\News\Audience\Segment\Strategy;

class ResHouse extends Base
{

    public const ENTITY_TYPE = 'res_house';

    /**
     * @inheritdoc
     */
    public function getSearchSql(string $searchPlaceholder = null): string
    {
        return 'TODO'; // TODO
    }


    /**
     * @inheritdoc
     */
    public function getTranslateSql(string $segmentIdsPlaceholder): string
    {
        return 'TODO'; // TODO
    }

    /**
     * @inheritdoc
     */
    public function canTargetNullEntityValue(): bool
    {
        // Users have 0:1 residential houses
        return true;
    }
}
