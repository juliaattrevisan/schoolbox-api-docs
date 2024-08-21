<?php

/**
 * @copyright Copyright 2021, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Alaress\Image\Transformation\Test;

use Alaress\Image\Transformation\Icon;
use Alaress\Image\Transformation\MyImage;
use Alaress\Image\Transformation\Portrait;
use Alaress\Image\Transformation\Storage;
use InvalidArgumentException;
use PHPUnit\Framework\ExpectationFailedException;
use PHPUnit\Framework\TestCase;
use Schoolbox\Realtime\Message\NSMapper;

/**
 * Tests that:
 * 1. System can match a cache key to a Transformation, and
 * 2. System does not incorrectly match a cache key to the wrong Transformation
 *
 * @codeCoverageIgnore
 * @covers ITransformFactory::recognisesCacheKey
 */
class RecogniseCacheKeyTest extends TestCase
{
    /**
     * @param string $cacheKey
     * @throws ExpectationFailedException
     * @throws InvalidArgumentException
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerIconCacheKeys')]
    public function testIcon(string $cacheKey): void
    {
        self::assertTrue((new Icon())->recognisesCacheKey($cacheKey), "'$cacheKey' not recognised");
    }

    /**
     * @param string $cacheKey
     * @throws ExpectationFailedException
     * @throws InvalidArgumentException
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerNotIconCacheKeys')]
    public function testNotIcon(string $cacheKey): void
    {
        self::assertFalse((new Icon())->recognisesCacheKey($cacheKey), "'$cacheKey' incorrectly recognised");
    }

    /**
     * @param string $cacheKey
     * @throws ExpectationFailedException
     * @throws InvalidArgumentException
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerMyImageCacheKeys')]
    public function testMyImage(string $cacheKey): void
    {
        self::assertTrue((new MyImage())->recognisesCacheKey($cacheKey), "'$cacheKey' not recognised");
    }

    /**
     * @param string $cacheKey
     * @throws ExpectationFailedException
     * @throws InvalidArgumentException
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerNotMyImageCacheKeys')]
    public function testNotMyImage(string $cacheKey): void
    {
        self::assertFalse((new MyImage())->recognisesCacheKey($cacheKey), "'$cacheKey' incorrectly recognised");
    }

    /**
     * @param string $cacheKey
     * @throws ExpectationFailedException
     * @throws InvalidArgumentException
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerPortraitCacheKeys')]
    public function testPortrait(string $cacheKey): void
    {
        self::assertTrue((new Portrait())->recognisesCacheKey($cacheKey), "'$cacheKey' not recognised");
    }

    /**
     * @param string $cacheKey
     * @throws ExpectationFailedException
     * @throws InvalidArgumentException
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerNotPortraitCacheKeys')]
    public function testNotPortrait(string $cacheKey): void
    {
        self::assertFalse((new Portrait())->recognisesCacheKey($cacheKey), "'$cacheKey' incorrectly recognised");
    }

    /**
     * @param string $cacheKey
     * @throws ExpectationFailedException
     * @throws InvalidArgumentException
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerStorageCacheKeys')]
    public function testStorage(string $cacheKey): void
    {
        self::assertTrue((new Storage())->recognisesCacheKey($cacheKey), "'$cacheKey' not recognised");
    }

    /**
     * @param string $cacheKey
     * @throws ExpectationFailedException
     * @throws InvalidArgumentException
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerNotStorageCacheKeys')]
    public function testNotStorage(string $cacheKey): void
    {
        self::assertFalse((new Storage())->recognisesCacheKey($cacheKey), "'$cacheKey' incorrectly recognised");
    }


    //============================ Data Providers =============================

    /**
     * Provides all valid Icon cache keys
     *
     * @return array
     */
    public static function providerIconCacheKeys(): array
    {
        $crops = [
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

        $nameSpaces = [
            NSMapper::NS_PASTORAL,
            NSMapper::NS_PASTORAL_COMMENT,
            NSMapper::NS_PASTORAL_LOG,
            NSMapper::NS_DUEWORK,
            NSMapper::NS_OLDDUEWORK,
            NSMapper::NS_ONLINE_ASSESS,
            NSMapper::NS_HOMEPAGE_BLOG,
            NSMapper::NS_EPORTFOLIO_BLOG,
            NSMapper::NS_FORUM,
            NSMapper::NS_NEWS,
            NSMapper::NS_CALENDAR,
            NSMapper::NS_WIKI,
            NSMapper::NS_GROUP,
            NSMapper::NS_CMS,
            NSMapper::NS_SSTREAM,
            NSMapper::NS_EOTC,
            NSMapper::NS_RESOURCE_BOOKING,
            NSMapper::NS_REPORT_CARD,
            NSMapper::NS_SPORTS_FIXTURES,
            NSMapper::NS_SPORTS_FIXTURES_IMPORT,
            NSMapper::NS_MESSAGE,
            NSMapper::NS_TIMETABLE,
            NSMapper::NS_UPS,
            NSMapper::NS_NEWS_DISCUSSION_COMMENT,
            NSMapper::NS_EVIDENCE,
            NSMapper::NS_BADGE,
        ];


        $sizes = [16, 32, 48, 64];
        $data  = [];
        foreach ($sizes as $size) {
            foreach (array_merge($crops, $nameSpaces) as $icon) {
                $data[] = ["$icon-$size"];
            }
        }
        return $data;
    }

    /**
     * Provides all valid MyImage cache keys
     *
     * @return array
     */
    public static function providerMyImageCacheKeys(): array
    {
        $sizes = [1, 16, 32, 48, 64, 100, 999];
        $data  = [];
        foreach ($sizes as $size1) {
            foreach ($sizes as $size2) {
                $data[] = ["{$size1}x{$size2}"];
            }
        }
        return $data;
    }

    /**
     * Provides all valid Portrait cache keys
     *
     * @return array
     */
    public static function providerPortraitCacheKeys(): array
    {
        $sizes = [
            'square32',
            'square48',
            'square64',
            'square72',
            'square80',
            'square200',
            'constrain64',
            'constrain128',
            'constrain200',
            'constrain500',
            'rectangle',
        ];
        $data  = [];
        foreach ($sizes as $size) {
            $data[] = [Portrait::getSlug() . "-$size"];
        }
        return $data;
    }

    /**
     * Provides all valid Storage cache keys
     *
     * @return array
     */
    public static function providerStorageCacheKeys(): array
    {
        $sizes = [
            'square32',
            'square64',
            'square128',
            'square200',
            'constrain200',
            'constrain300',
            'rectangle',
            'maxsize',
            'eportfolioImageGalleryLarge',
            'coverPhoto',
            'eportfolioCoverPhoto',
            'eportfolioRecentPhoto',
            'eportfolioImageGalleryThumbnail',
            'cardImageLarge',
            'cacheTest',
        ];
        $data  = [];
        foreach ($sizes as $size) {
            $data[] = [Storage::getSlug() . "-$size"];
        }
        return $data;
    }

    public static function providerNotIconCacheKeys(): array
    {
        return array_merge(
            self::providerMyImageCacheKeys(),
            self::providerPortraitCacheKeys(),
            self::providerStorageCacheKeys(),
        );
    }

    public static function providerNotMyImageCacheKeys(): array
    {
        return array_merge(
            self::providerIconCacheKeys(),
            self::providerPortraitCacheKeys(),
            self::providerStorageCacheKeys(),
        );
    }

    public static function providerNotPortraitCacheKeys(): array
    {
        return array_merge(
            self::providerIconCacheKeys(),
            self::providerMyImageCacheKeys(),
            self::providerStorageCacheKeys(),
        );
    }

    public static function providerNotStorageCacheKeys(): array
    {
        return array_merge(
            self::providerIconCacheKeys(),
            self::providerMyImageCacheKeys(),
            self::providerPortraitCacheKeys(),
        );
    }
}
