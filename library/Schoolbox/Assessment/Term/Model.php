<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Assessment\Term;

use Alaress_Date_Format;

/**
 * Representation of a user-defined school term.
 *
 * @package Schoolbox\Assessment\Term
 */
class Model
{
    /**
     * Primary key
     *
     * @var int
     */
    protected $id;

    /**
     * The human-readable title of this term.
     *
     * @var string
     */
    protected $title;

    /**
     * A code for this term, intended to be used in a URL.
     *
     * @var string
     */
    protected $code;

    /**
     * The date on which this term starts.
     *
     * @var Alaress_Date_Format
     */
    protected $start;

    /**
     * The display date on which this term is said to finish.
     *
     * @var Alaress_Date_Format
     */
    protected $displayFinish;

    /**
     * The date on which this term actually finishes.
     * (This date will always be one day before the next term starts)
     *
     * @var Alaress_Date_Format
     */
    protected $actualFinish;

    /**
     * If a term is archived, it should be de-emphasized in term selectors.
     *
     * @var bool
     */
    protected $archived;

    /**
     * Constructor - ensures this object is in a usable state.
     */
    public function __construct()
    {
        $this->start         = new Alaress_Date_Format();
        $this->displayFinish = new Alaress_Date_Format();
        $this->actualFinish  = new Alaress_Date_Format();
    }

    /**
     * Is this date considered as falling within this term?
     *
     * Terms actually cover all dates from their start date to the start
     * of the next term (not inclusive).
     *
     * @param  Alaress_Date_Format $date
     * @return bool
     */
    public function contains(Alaress_Date_Format $date)
    {
        // TODO make this calculation take next term's start
        return $date->between(
            $this->getStart(),
            $this->getDisplayFinish()
        );
    }

    /**
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @param  int $id
     * @return $this
     */
    public function setId($id)
    {
        $this->id = $id;
        return $this;
    }

    /**
     * @return string
     */
    public function getTitle()
    {
        return $this->title;
    }

    /**
     * @param  string $title
     * @return $this
     */
    public function setTitle($title)
    {
        $this->title = $title;
        return $this;
    }

    /**
     * @return string
     */
    public function getCode()
    {
        return $this->code;
    }

    /**
     * @param  string $code
     * @return $this
     */
    public function setCode($code)
    {
        $this->code = $code;
        return $this;
    }

    /**
     * @return Alaress_Date_Format
     */
    public function getStart()
    {
        return $this->start;
    }

    /**
     * @param  Alaress_Date_Format $start
     * @return $this
     */
    public function setStart($start)
    {
        $this->start = $start;
        return $this;
    }

    /**
     * @return Alaress_Date_Format
     */
    public function getDisplayFinish()
    {
        return $this->displayFinish;
    }

    /**
     * @param  Alaress_Date_Format $displayFinish
     * @return $this
     */
    public function setDisplayFinish($displayFinish)
    {
        $this->displayFinish = $displayFinish;
        return $this;
    }

    /**
     * @return bool
     */
    public function getArchived()
    {
        return $this->archived;
    }

    /**
     * @param  bool $archived
     * @return $this
     */
    public function setArchived($archived)
    {
        $this->archived = $archived;
        return $this;
    }
}
