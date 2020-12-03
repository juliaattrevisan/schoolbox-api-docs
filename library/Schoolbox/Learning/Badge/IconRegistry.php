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
     *    'files' => the list of files found in this folder, machine-sorted
     * ]
     *
     */
    private array $registry = [
        'arrow' => [
            'name' => 'Arrow',
            'files' => [],
        ],
        'avatars' => [
            'name' => 'Avatars',
            'files' => [],
        ],
        'baby' => [
            'name' => 'Baby',
            'files' => [],
        ],
        'business' => [
            'name' => 'Business',
            'files' => [],
        ],
        'christmas' => [
            'name' => 'Christmas',
            'files' => [],
        ],
        'clothes-and-accessories' => [
            'name' => 'Clothes and Accessories',
            'files' => [],
        ],
        'communication' => [
            'name' => 'Communication',
            'files' => [],
        ],
        'computer-hardware' => [
            'name' => 'Computer Hardware',
            'files' => [],
        ],
        'construction' => [
            'name' => 'Construction',
            'files' => [],
        ],
        'country' => [
            'name' => 'Country',
            'files' => [],
        ],
        'education' => [
            'name' => 'Education',
            'files' => [],
        ],
        'food' => [
            'name' => 'Food',
            'files' => [],
        ],
        'furniture' => [
            'name' => 'Furniture',
            'files' => [],
        ],
        'halloween' => [
            'name' => 'Halloween',
            'files' => [],
        ],
        'home-appliances' => [
            'name' => 'Home Appliances',
            'files' => [],
        ],
        'medical' => [
            'name' => 'Medical',
            'files' => [],
        ],
        'music' => [
            'name' => 'Music',
            'files' => [],
        ],
        'party' => [
            'name' => 'Party',
            'files' => [],
        ],
        'real-estate' => [
            'name' => 'Real Estate',
            'files' => [],
        ],
        'scene' => [
            'name' => 'Scene',
            'files' => [],
        ],
        'seo' => [
            'name' => 'SEO',
            'files' => [],
        ],
        'shopping-cart' => [
            'name' => 'Shopping Cart',
            'files' => [],
        ],
        'sport' => [
            'name' => 'Sport',
            'files' => [],
        ],
        'tools' => [
            'name' => 'Tools',
            'files' => [],
        ],
        'transport' => [
            'name' => 'Transport',
            'files' => [],
        ],
        'travel' => [
            'name' => 'Travel',
            'files' => [],
        ],
        'ui' => [
            'name' => 'User Interface',
            'files' => [],
        ],
        'valentines-day' => [
            'name' => "Valentines Day",
            'files' => [],
        ],
        'warning-signs' => [
            'name' => 'Warning Signs',
            'files' => [],
        ],
        'weather' => [
            'name' => 'Weather',
            'files' => [],
        ],
        'zodiac' => [
            'name' => 'Zodiac',
            'files' => [],
        ]
    ];


    /**
     * Number of icons to display per page
     */
    public const BADGE_ICON_MODAL_PAGELENGTH = 40;


    /**
     * Badge svgs live here
     */
    private string $root;


    /**
     * Set the root folder for traversing to get a list of files when
     * you really don't need them
     *
     * IconRegistry constructor.
     */
    public function __construct()
    {
        $this->root = __DIR__ . '/../../../../www/images/badges';
    }


    /**
     * Populate the registry with a list of known filenames
     *
     * @return $this
     */
    public function prepare(): self
    {
        foreach ($this->registry as $folder => $node) {
            $files = [];

            $path = "{$this->root}/{$folder}";

            $dirhandle = opendir($path);

            while ($file = readdir($dirhandle)) {
                if ($file === '..' || $file === '.') {
                    continue;
                }

                $files[] = $file;
            }

            sort($files);

            $this->registry[$folder]['files'] = $files;
        }

        return $this;
    }


    /**
     * Get the list of folder nodes and their respective file contents
     *
     * @return array|array[]
     */
    public function getRegistry(): array
    {
        return $this->registry;
    }


    /**
     * Check that each folder has the correct num specified
     * and that each of the files is named sequentially
     *
     * Usage: in a test.php, (new \Schoolbox\Learning\Badge\IconRegistry())->analyse();
     */
    public function analyse(): void
    {
        foreach ($this->registry as $folder => $node) {
            for ($i = 1; $i <= $node['num']; $i++) {

                $path = "{$this->root}/{$folder}/{$i}.svg";

                $exists = file_exists($path);

                new \Alaress_Probe($path, $exists ? "<strong style='color:#0d0'>Exists</strong>" : "<strong style='color:#d00'>Missing</strong>");
            }
        }
    }
}
