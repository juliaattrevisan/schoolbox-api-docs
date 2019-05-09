<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Alaress\Image\Transformation;

use InvalidArgumentException;
use Schoolbox\Realtime\Message\NSMapper;

class Icon extends BaseTransformation
{

    /**
    * Create the list of valid transforms
    */
    public function __construct($icon, $size = 64)
    {

        $validSizes = array(16,32,48,64);
        $validCrops = array(
            // ensure this list accurately reflects the sprite file!
            'news','calendar','tick','link','list','comment','forum','users','import','wiki','group','sstream','eotc','resource','report','sports'
        );

        // map namespaces to appropriate icons
        $namespaceMap = array(
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
        );

        if (!in_array($size, $validSizes)) {
            throw new InvalidArgumentException("Invalid size given: $size");
        }

        $crops = array_flip($validCrops);

        if (isset($crops[$icon])) {
            $index = $crops[$icon];
        } elseif (isset($namespaceMap[$icon])) {
            $index = $crops[$namespaceMap[$icon]];
        } else {
            throw new InvalidArgumentException("Invalid icon given: $icon");
        }

        $count = count($crops);
        $step  = 100 / $count;

        $top = ($index * $step);
        $bottom = (($index + 1) * $step);


        // PERCENDAGES!
        $coords = array(
            // always take full width
            'x0' => 0, 'x1' => 100,
            'y0' => $top, 'y1' => $bottom,
        );

        $this->allTransformations = array(
            'Icon' => array(
                array(
                    'method' => 'Crop',
                    'args' => array(
                        'coordinates' => $coords
                    )
                ),
                array(
                    'method' => 'Square',
                    'args' => array(
                        'width' => $size,
                    )
                ),
            ),
        );
    }
}
