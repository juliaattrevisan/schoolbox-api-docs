<?php

/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Assessment\Term;

use Alaress_Date_Format;
use Schoolbox\GenericCollection;
use Schoolbox\IModel;
use Schoolbox\Mapper\Traits\Model\CreateCollection;
use Schoolbox\Mapper\Traits\Model\Id;
use Schoolbox\User;
use Schoolbox\YearLevel;

/**
 * Representation of a user-defined school term.
 *
 * @package Schoolbox\Assessment\Term
 */
class Model implements \IComparable, IModel
{
    use Id;
    use CreateCollection;

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
     * @var \Schoolbox\YearLevel\Model[]|null
     */
    private $yearLevels;


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
        // TODO make ADF immutable already
        $start  = clone $this->getStart();
        $finish = clone $this->getActualFinish();

        // No bounds on this term?
        if ($start->isNull() && $finish->isNull()) {
            return true;
        }

        // No start? accept any date before finish
        if ($start->isNull()) {
            return $date->lte($finish->endOfDay());
        }

        // No end: accept any date after start
        if ($finish->isNull()) {
            return $date->gte($start->startOfDay());
        }

        // Both start and end
        return $date->between($start->startOfDay(), $finish->endOfDay());
    }

    /**
     * Can this user mark work in this term?
     *
     * @param  User $user
     * @return bool
     */
    public function isMarkableBy(User $user)
    {
        return !$this->getArchived() || $user->isSuperuser();
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
     * @return number of working (timetable) days in a term
     */
    public function getDuration($date = null)
    {
        //Return a number of working days until the given date
        if ($date) {
            return $this->getStart()->diffInWeekdays($date);
        }
        //Return a number of working days until the end of term.
        return $this->getStart()->diffInWeekdays($this->getDisplayFinish());
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
     * @return Alaress_Date_Format
     */
    public function getActualFinish()
    {
        return $this->actualFinish;
    }

    /**
     * @param  Alaress_Date_Format $actualFinish
     * @return $this
     */
    public function setActualFinish($actualFinish)
    {
        $this->actualFinish = $actualFinish;
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


    /**
     * @return bool
     */
    public function getExtraordinary(): bool
    {
        /* Unspecified year level relations: not definitively extraordinary
         */
        if (null === $this->getYearLevels()) {
            return false;
        }

        /* Otherwise, find out how many year level relations were given to this Term
         * Any quantity of year level relations makes an Extraordinary Term
         */
        return $this->getYearLevels()->count() > 0;
    }


    /**
     * @return GenericCollection|YearLevel\Model[]|null
     */
    public function getYearLevels(): ?GenericCollection
    {
        return $this->yearLevels;
    }


    /**
     * @param GenericCollection|YearLevel\Model[] $yearLevels
     * @return Model
     */
    public function setYearLevels(GenericCollection $yearLevels): self
    {
        $yearLevels->assertCollectionOf(YearLevel\Model::class);

        $this->yearLevels = $yearLevels;

        return $this;
    }


    /**
     * Orders Terms by:
     * * Start
     * * Display finish
     * * Actual finish
     * @param Model $other
     * @return int
     */
    public function compare($other)
    {
        if (!$other instanceof Model) {
            throw new \DomainException('Can only compare to other Term objects. Found: ' . get_class($other));
        }

        $startCompare = $this->start->compare($other->start);
        if (0 != $startCompare) {
            return $startCompare;
        }
        $displayFinishCompare = $this->displayFinish->compare($other->displayFinish);
        if (0 != $displayFinishCompare) {
            return $displayFinishCompare;
        }

        // Interpret NULL date as infinitely in future

        if ($this->actualFinish->isNull() && $other->actualFinish->isNull()) {
            return 0;
        }
        if ($other->actualFinish->isNull()) {
            return -1;
        }
        if ($this->actualFinish->isNull()) {
            return 1;
        }
        return $this->actualFinish->compare($other->actualFinish);
    }
}
