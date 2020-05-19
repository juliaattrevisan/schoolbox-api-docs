<?php

/**
 * Timetable Colour Model
 *
 * Object for colours in use in timetable
 *
 * @author      James Leckie <james.leckie@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
class Timetable_Colour
{
    /**
     * @var int
     */
    protected $timetableColourId;

    /**
     * @var string
     */
    protected $hexColour;

    /**
     * @param string $hexColour
     *
     * @return $this
     */
    public function setHexColour($hexColour): self
    {
        $this->hexColour = $hexColour;
        return $this;
    }

    /**
     * @return null|string
     */
    public function getHexColour(): ?string
    {
        return $this->hexColour;
    }

    /**
     * @param int $timetableColourId
     *
     * @return $this
     */
    public function setTimetableColourId($timetableColourId): self
    {
        $this->timetableColourId = $timetableColourId;
        return $this;
    }

    /**
     * @return null|int
     */
    public function getTimetableColourId(): ?int
    {
        return $this->timetableColourId;
    }
}
