<?php

/**
 * Color manager - handles cycling through a list of colors, and assigning a
 * color in that list to a unique color.
 *
 * Please excuse the inconsistent spellings of the word "color"
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

class Timetable_ColourManager
{
    /**
     * The list of colors that can be displayed
     *
     * @var \Schoolbox\Collection|Timetable_Colour[]
     */
    protected $colorList;

    /**
     * A map of input values to hex colors
     *
     * @var array
     */
    protected $colorMap = [];

    /**
     * Which index within $this->colorList am I at now?
     *
     * @var int
     */
    protected $colorIndex = 0;

    /**
     * Constructor
     *
     * @param \Schoolbox\Collection|Timetable_Colour[] $colorList
     */
    public function __construct($colorList)
    {
        $this->colorList = $colorList;
    }

    /**
     * Gets a hex code color for this value.
     *
     * @param  mixed  $value
     * @return string
     */
    public function getColour($value)
    {
        if (count($this->colorList) === 0) {
            return '';
        }
        if ($value && !array_key_exists($value, $this->colorMap)) {
            // not enough colors: loop around to index 0.
            if (!$this->colorList->offsetExists($this->colorIndex)) {
                $this->colorIndex = 0;
            }
            $this->colorMap[$value] = $this->colorList->offsetGet($this->colorIndex)->getHexColour();
            $this->colorIndex++;
        }
        return $this->colorMap[$value];
    }
}
