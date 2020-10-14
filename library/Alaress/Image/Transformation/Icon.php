<?php
/**
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Alaress\Image\Transformation;

use Alaress\Image\Transform\Crop;
use Alaress\Image\Transform\Square;
use InvalidArgumentException;
use OutOfBoundsException;
use Schoolbox\Realtime\Message\NSMapper;
use function array_key_exists;
use function in_array;

class Icon implements ITransformation
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

    /** @var int */
    private $top;
    /** @var int */
    private $bottom;
    /** @var int */
    private $size;

    /**
     * Create the list of valid transforms
     *
     * @param string $icon
     * @param int    $size
     * @throws InvalidArgumentException If icon is unrecognised or size is invalid
     */
    public function __construct(string $icon, int $size = 64)
    {
        if (!in_array($size, self::VALID_SIZES, true)) {
            throw new InvalidArgumentException("Invalid size given: $size");
        }
        $this->size = $size;

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
        $this->top    = (int)($index * $step);
        $this->bottom = (int)(($index + 1) * $step);
        $this->size   = $size;
    }

    /** @inheritDoc */
    public function retrieve(string $key): array
    {
        if ($key === 'Icon') {
            return [new Crop(0, $this->top, 100, $this->bottom), new Square($this->size)];
        }
        throw new OutOfBoundsException('Could not load transformation list.');
    }
}
