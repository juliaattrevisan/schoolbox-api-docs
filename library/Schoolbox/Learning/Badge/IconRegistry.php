<?php
/**
 * @copyright   Copyright 2020, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Learning\Badge;

/**
 * Class IconRegistry
 *
 * This is a set of facts about our badge icons. It knows the folders and the number
 * of icons in each folder. Each icon is named numerically and sequentially. This
 * allows us to bypass a bunch of useless and wasteful disk io and async requests
 * simply by requesting an image directly from within loops
 *
 * Illustrating the basic premise:
 *
 * for (i = 0; i < n; i++) {
 *    img.attr('src') = i + '.svg'
 *
 * Instead of making an async request, opening a file pointer in php, reading each file
 * into an array, using array_splice to take a subset, discard the rest, async it back
 * to the ui...only for it to make those img.src requests anyway
 *
 * So long as we adhere to the established idea (conferred with stuart prior to implementing
 * this idea: updating icons is foreseen as a rare circumstance) we save a lot of
 * backend hankypanky and make the user experience as snappy as it gets.
 *
 * (svgs didn't come in a sprite sheet if that is even possible, they are individual files
 * so you're going to incur this cost at the minimum...)
 *
 * @package Schoolbox\Learning\Badge
 */
class IconRegistry
{

    /**
     * Each struct represents this info:
     *
     * folder name => [
     *    'name' => this appears in the modal as a list of icon categories to choose
     *    'num' => the number of icon files in this folder, named numerically and sequentially
     * ]
     *
     */
    public const BADGE_ICON_REGISTRY = [
        'arrow' => [
            'name' => 'Arrow',
            'num' => 88
        ],
        'avatars' => [
            'name' => 'Avatars',
            'num' => 100
        ],
        'baby' => [
            'name' => 'Baby',
            'num' => 40
        ],
        'business' => [
            'name' => 'Business',
            'num' => 112,
        ],
        'christmas' => [
            'name' => 'Christmas',
            'num' => 17,
        ],
        'clothes-and-accessories' => [
            'name' => 'Clothes and Accessories',
            'num' => 72,
        ],
        'communication' => [
            'name' => 'Communication',
            'num' => 32,
        ],
        'computer-hardware' => [
            'name' => 'Computer Hardware',
            'num' => 40,
        ],
        'construction' => [
            'name' => 'Construction',
            'num' => 35,
        ],
        'country' => [
            'name' => 'Country',
            'num' => 35,
        ],
        'education' => [
            'name' => 'Education',
            'num' => 80,
        ],
        'food' => [
            'name' => 'Food',
            'num' => 27,
        ],
        'furniture' => [
            'name' => 'Furniture',
            'num' => 26,
        ],
        'halloween' => [
            'name' => 'Halloween',
            'num' => 40,
        ],
        'home-appliances' => [
            'name' => 'Home Appliances',
            'num' => 50,
        ],
        'medical' => [
            'name' => 'Medical',
            'num' => 38,
        ],
        'music' => [
            'name' => 'Music',
            'num' => 32,
        ],
        'party' => [
            'name' => 'Party',
            'num' => 24,
        ],
        'real-estate' => [
            'name' => 'Real Estate',
            'num' => 24,
        ],
        'scene' => [
            'name' => 'Scene',
            'num' => 30,
        ],
        'seo' => [
            'name' => 'SEO',
            'num' => 100,
        ],
        'shopping-cart' => [
            'name' => 'Shopping Cart',
            'num' => 24,
        ],
        'sport' => [
            'name' => 'Sport',
            'num' => 50,
        ],
        'tools' => [
            'name' => 'Tools',
            'num' => 25,
        ],
        'transport' => [
            'name' => 'Transport',
            'num' => 24,
        ],
        'travel' => [
            'name' => 'Travel',
            'num' => 40,
        ],
        'ui' => [
            'name' => 'User Interface',
            'num' => 210,
        ],
        'valentines-day' => [
            'name' => "Valentines Day",
            'num' => 23,
        ],
        'warning-signs' => [
            'name' => 'Warning Signs',
            'num' => 24,
        ],
        'weather' => [
            'name' => 'Weather',
            'num' => 32,
        ],
        'zodiac' => [
            'name' => 'Zodiac',
            'num' => 12,
        ]
    ];


    /**
     * Number of icons to display per page
     */
    public const BADGE_ICON_MODAL_PAGELENGTH = 40;


    /**
     * Check that each folder has the correct num specified
     * and that each of the files is named sequentially
     */
    public function analyse(): void
    {
    }
}
