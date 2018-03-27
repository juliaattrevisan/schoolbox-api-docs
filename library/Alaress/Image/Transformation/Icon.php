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
    public function __construct($icon, $size = 72)
    {

        $validSizes = array(16,24,32,48,64,72);
        $validCrops = array(
            // ensure this list accurately reflects the sprite file!
            'pin','doc','clock','tick','link','bolt','grade','poll','chat','comment','cross','reload','work','alert','upload','wiki'
        );

        // map namespaces to appropriate icons
        $namespaceMap = array(
            NSMapper::NS_DUEWORK            => 'work',
            NSMapper::NS_ONLINE_ASSESS      => 'work',
            NSMapper::NS_HOMEPAGE_BLOG      => 'chat',
            NSMapper::NS_FORUM              => 'chat',
            NSMapper::NS_NEWS               => 'pin',
            NSMapper::NS_CALENDAR           => 'clock',
            NSMapper::NS_BOARDER_LEAVE      => 'tick',
            NSMapper::NS_WIKI               => 'wiki',
            NSMapper::NS_GROUP              => 'chat',
            NSMapper::NS_CMS                => 'link',
            NSMapper::NS_SSTREAM            => 'chat',
            NSMapper::NS_EOTC               => 'clock',
            NSMapper::NS_RESOURCE_BOOKING   => 'clock',
            NSMapper::NS_REPORT_CARD        => 'chat',
            NSMapper::NS_EPORTFOLIO_BLOG    => 'comment',
            NSMapper::NS_PASTORAL           => 'comment',
            NSMapper::NS_PASTORAL_COMMENT   => 'chat',
            NSMapper::NS_SPORTS_FIXTURES    => 'bolt',
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
