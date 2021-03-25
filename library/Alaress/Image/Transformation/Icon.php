<?php

/**
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Alaress\Image\Transformation;

use Alaress\Image\ITransform;
use Alaress\Image\Transform\Crop;
use Alaress\Image\Transform\Sequence;
use Alaress\Image\Transform\Square;
use InvalidArgumentException;
use Schoolbox\Realtime\Message\NSMapper;

/**
 * Produces 'icon' transforms
 */
class Icon implements ITransformFactory
{
    // map namespaces to appropriate icons
    private const NAMESPACE_MAP = [
        NSMapper::NS_PASTORAL                => 'news',
        NSMapper::NS_PASTORAL_COMMENT        => 'comment',
        NSMapper::NS_PASTORAL_LOG            => 'list',
        NSMapper::NS_DUEWORK                 => 'import',
        NSMapper::NS_OLDDUEWORK              => 'import',
        NSMapper::NS_ONLINE_ASSESS           => 'import',
        NSMapper::NS_HOMEPAGE_BLOG           => 'comment',
        NSMapper::NS_EPORTFOLIO_BLOG         => 'comment',
        NSMapper::NS_FORUM                   => 'forum',
        NSMapper::NS_NEWS                    => 'news',
        NSMapper::NS_CALENDAR                => 'calendar',
        NSMapper::NS_BOARDER_LEAVE           => 'tick',
        NSMapper::NS_WIKI                    => 'wiki',
        NSMapper::NS_GROUP                   => 'group',
        NSMapper::NS_CMS                     => 'link',
        NSMapper::NS_SSTREAM                 => 'sstream',
        NSMapper::NS_EOTC                    => 'eotc',
        NSMapper::NS_RESOURCE_BOOKING        => 'resource',
        NSMapper::NS_REPORT_CARD             => 'report',
        NSMapper::NS_SPORTS_FIXTURES         => 'sports',
        NSMapper::NS_SPORTS_FIXTURES_IMPORT  => 'import',
        NSMapper::NS_MESSAGE                 => 'comment',
        NSMapper::NS_TIMETABLE               => 'calendar',
        NSMapper::NS_UPS                     => 'import',
        NSMapper::NS_NEWS_DISCUSSION_COMMENT => 'comment',
        NSMapper::NS_EVIDENCE                => 'resource',
        NSMapper::NS_EVIDENCE_COMMENT        => 'resource',
        NSMapper::NS_BADGE                   => 'list',
    ];

    private const VALID_SIZES = [16, 32, 48, 64];
    private const VALID_CROPS = [
        'news',
        'calendar',
        'tick',
        'link',
        'list',
        'comment',
        'forum',
        'users',
        'import',
        'wiki',
        'group',
        'sstream',
        'eotc',
        'resource',
        'report',
        'sports',
    ];


    /**
     * @param string $icon
     * @param int    $size
     * @return ITransform
     * @throws InvalidArgumentException
     */
    public function retrieve(string $icon, int $size = 64): ITransform
    {
        if (!in_array($size, self::VALID_SIZES, true)) {
            throw new InvalidArgumentException("Invalid size given: $size");
        }

        // ensure this list accurately reflects the sprite file!
        $crops = array_flip(self::VALID_CROPS);

        if (array_key_exists($icon, $crops)) {
            $index = $crops[$icon];
        } elseif (array_key_exists($icon, self::NAMESPACE_MAP)) {
            $index = $crops[self::NAMESPACE_MAP[$icon]];
        } else {
            throw new InvalidArgumentException("Invalid icon given: $icon");
        }

        $count = count($crops);
        $step  = 100 / $count;

        // Coords are percentages
        $top    = ($index * $step);
        $bottom = (($index + 1) * $step);

        return new Sequence(new Crop(0, $top, 100, $bottom), new Square($size));
    }


    /**
     * Returns a cache key for a transformation
     *
     * @param string $icon
     * @param int    $size
     * @return string
     */
    public function getCacheKey(string $icon, int $size): string
    {
        return "$icon-$size";
    }

    /**
     * Returns whether this class generated the specified cache key
     *
     * @param string $cacheKey
     * @return bool
     */
    public function recognisesCacheKey(string $cacheKey): bool
    {
        if (!preg_match('/([a-z]+)-(\d+)/', $cacheKey, $matches)) {
            return false;
        }
        array_shift($matches);
        [$icon, $size] = $matches;

        return
            (in_array($icon, self::VALID_CROPS, true) || array_key_exists($icon, self::NAMESPACE_MAP)) &&
            in_array($size, self::VALID_SIZES, false);
    }

    /**
     * Returns a Transform for the given cache key
     *
     * @param string $cacheKey
     * @return ITransform
     * @throws InvalidArgumentException
     */
    public function retrieveForCacheKey(string $cacheKey): ITransform
    {
        [$icon, $size] = explode('-', $cacheKey);
        return $this->retrieve($icon, (int)$size);
    }


    /** @inheritDoc */
    public static function getSlug(): string
    {
        return 'icon';
    }
}
