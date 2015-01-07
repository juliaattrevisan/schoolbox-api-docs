<?php
/**
 * Factory
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\External\Normalise\Admin;


class Factory {

    /**
     * The selected section
     *
     * @var string
     */
    private $section = 'intro';

    /**
     * Constructor
     *
     * @param $section
     */
    public function __construct($section) {
        $this->setSection(strtolower($section));
    }

    /**
     * Produce
     *
     * @return Guardian|Intro|Staff|Student
     */
    public function produce() {
        switch($this->getSection()) {
            case 'staff': return new Staff();
            case 'student': return new Student();
            case 'parent': return new Guardian();
            default:
                $this->setSection('intro');
                return new Intro();
        }
    }

    /* Getters/Setters
     */

    /**
     * Set section
     *
     * @param string $section
     */
    private function setSection($section)
    {
        $this->section = $section;
    }

    /**
     * Get section
     *
     * @return string
     */
    public function getSection()
    {
        return $this->section;
    }
}