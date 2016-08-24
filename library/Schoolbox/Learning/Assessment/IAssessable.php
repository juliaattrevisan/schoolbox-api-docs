<?php
/**
 * IAssessable
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Learning\Assessment;


interface IAssessable
{

    /**
     * Return the name of the "type" of Assessable this is
     *
     * For Due Works, it'll be "Essay, Homework, etc"
     * For Quiz, it'll just be "Quiz"
     * For Task, it'll just be "Task"
     * For Project, it'll just be "Project"
     *
     * @return mixed
     */
    public function getTypeName();

    /* Getters/Setters
     */
    public function getTitle();
    public function setTitle($title);
    public function getDescription();
    public function setDescription($description);
    public function getPublishAt();
    public function setPublishAt($publishAt);
    public function getOpenAt();
    public function setOpenAt($openAt);
    public function getCloseAt();
    public function setCloseAt($closeAt);
    public function getWeight();
    public function setWeight($weight);
    public function getAllParticipants();
    public function setAllParticipants($allParticipants);
}