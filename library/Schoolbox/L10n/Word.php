<?php

/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
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
     * Should only the indefinite article (a or an) be rendered?
     *
     * @var bool
     */
    private $indef = false;

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
     * @param bool $indef
     * @return static
     */
    public function setIndefiniteArticle($indef)
    {
        $this->indef = $indef;
        return $this;
    }

    /**
     * @return string
     */
    public function __toString()
    {
        if ($this->indef) {
            return $this->stone->indefiniteArticle($this->term, $this->capital);
        }
        return $this->stone->translate($this->term, $this->plural, $this->capital);
    }
}
