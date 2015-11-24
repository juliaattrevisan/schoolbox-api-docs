<?php
/**
 * Type
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\External\Normalise\User\Staff;
use Schoolbox\External\Normalise\User\Staff\Category;

class Meta {

    /**
     * An instance of Category
     *
     * @var Category
     */
    private $category = null;

    /**
     * Department name
     *
     * @var string
     */
    private $department = '';

    /**
     * Job position
     *
     * @var Position
     */
    private $position = null;

    /**
     * Room
     *
     * @var string
     */
    private $room = '';

    /**
     * Building
     *
     * @var string
     */
    private $building = '';

    /**
     * Voicemail
     *
     * @var string
     */
    private $voicemail = '';

    /**
     * Phone extension
     *
     * @var string
     */
    private $extension = '';

    /**
     * Type
     *
     * @var string
     */
    private $type = '';

    /**
     * Constructor
     */
    public function __construct() {
        $this->setup();
    }

    /**
     * Hook method
     */
    protected function setup() {
        $this->setPosition(new Position());
        $this->setCategory(new Category());
    }

    /* Getters/Setters
     */

    /**
     * Set department
     *
     * @param string $department
     * @return $this
     */
    public function setDepartment($department)
    {
        $this->department = $department;
        return $this;
    }

    /**
     * Get department
     *
     * @return string
     */
    public function getDepartment()
    {
        return $this->department;
    }

    /**
     * Set position
     *
     * @param Position $position
     * @return $this
     */
    private function setPosition($position)
    {
        $this->position = $position;
        return $this;
    }

    /**
     * Get position
     *
     * @return Position
     */
    public function getPosition()
    {
        return $this->position;
    }

    /**
     * Set type
     *
     * @param string $type
     * @return $this
     */
    public function setType($type)
    {
        $this->type = $type;
        return $this;
    }

    /**
     * Get type
     *
     * @return string
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * Set extension
     *
     * @param string $extension
     * @return $this
     */
    public function setExtension($extension)
    {
        $this->extension = $extension;
        return $this;
    }

    /**
     * Get extension
     *
     * @return string
     */
    public function getExtension()
    {
        return $this->extension;
    }

    /**
     * Set building
     *
     * @param string $building
     * @return $this
     */
    public function setBuilding($building)
    {
        $this->building = $building;
        return $this;
    }

    /**
     * Get building
     *
     * @return string
     */
    public function getBuilding()
    {
        return $this->building;
    }

    /**
     * Set room
     *
     * @param string $room
     * @return $this
     */
    public function setRoom($room)
    {
        $this->room = $room;
        return $this;
    }

    /**
     * Get room
     *
     * @return string
     */
    public function getRoom()
    {
        return $this->room;
    }

    /**
     * Set voicemail
     *
     * @param string $voicemail
     * @return $this
     */
    public function setVoicemail($voicemail)
    {
        $this->voicemail = $voicemail;
        return $this;
    }

    /**
     * Get voicemail
     *
     * @return string
     */
    public function getVoicemail()
    {
        return $this->voicemail;
    }

    /**
     * Set category
     *
     * @param Category $category
     * @return $this
     */
    public function setCategory($category)
    {
        $this->category = $category;
        return $this;
    }

    /**
     * Get category
     *
     * @return Category
     */
    public function getCategory()
    {
        return $this->category;
    }
}