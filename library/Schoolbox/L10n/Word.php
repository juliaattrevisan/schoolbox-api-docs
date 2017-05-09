<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2017, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\L10n;

/**
 * A tokenized word
 */
class Word
{
    /**
     * The term to render
     *
     * @var string
     */
    private $term;

    /**
     * The stone upon which localisations are engraved
     *
     * @var RosettaStone
     */
    private $stone;

    /**
     * Is this word to be pluralized when it's output?
     *
     * @var bool
     */
    private $plural = false;

    /**
     * Is this word to be capitalized when it's output?
     *
     * @var bool
     */
    private $capital = false;

    /**
     * Constructor
     *
     * @param string       $term
     * @param RosettaStone $stone
     */
    public function __construct($term, RosettaStone $stone)
    {
        $this->term  = $term;
        $this->stone = $stone;
    }

    /**
     * @param bool $plural
     * @return static
     */
    public function setPlural($plural)
    {
        $this->plural = $plural;
        return $this;
    }

    /**
     * @param bool $capital
     * @return static
     */
    public function setCapital($capital)
    {
        $this->capital = $capital;
        return $this;
    }

    /**
     * @return string
     */
    public function __toString()
    {
        return $this->stone->translate($this->term, $this->plural, $this->capital);
    }
}
