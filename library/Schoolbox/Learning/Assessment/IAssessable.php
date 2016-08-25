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

use \Schoolbox\User;

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

    /**
     * Find a reference to a particular Participant in this Assessable
     * which saves you this traversal in Controllers etc
     *
     * @param User $user
     * @return Participant
     */
    public function findParticipant(User $user);

    /* Getters/Setters
     */

    /**
     * @var string
     */
    public function getId();

    /**
     * @param  int $id
     *
     * @return $this
     */
    public function setId($id);

    /**
     * @return string
     */
    public function getTitle();

    /**
     * @param string $title
     * @return $this
     */
    public function setTitle($title);

    /**
     * @return string
     */
    public function getDescription();

    /**
     * @param string $description
     * @return $this
     */
    public function setDescription($description);

    /**
     * @return \Alaress_Date_Format
     */
    public function getPublishAt();

    /**
     * @param \Alaress_Date_Format $publishAt
     * @return $this
     */
    public function setPublishAt($publishAt);

    /**
     * @return \Alaress_Date_Format
     */
    public function getOpenAt();

    /**
     * @param \Alaress_Date_Format $openAt
     * @return $this
     */
    public function setOpenAt($openAt);

    /**
     * @return \Alaress_Date_Format
     */
    public function getCloseAt();

    /**
     * @param \Alaress_Date_Format $closeAt
     * @return $this
     */
    public function setCloseAt($closeAt);

    /**
     * @return float
     */
    public function getWeight();

    /**
     * @param float $weight
     * @return $this
     */
    public function setWeight($weight);

    /**
     * @return User
     */
    public function getCreator();

    /**
     * @param User $creator
     * @return $this
     */
    public function setCreator($creator);

    /**
     * @return mixed
     */
    public function getAllParticipants();

    /**
     * @param mixed $allParticipants
     * @return $this
     */
    public function setAllParticipants($allParticipants);

    /**
     * Created date
     *
     * @var \Alaress_Date_Format
     */
    public function getCreatedAt();

    /**
     * @param  \Alaress_Date_Format $createdAt
     *
     * @return $this
     */
    public function setCreatedAt($createdAt);

    /**
     * @return \Alaress_Date_Format
     */
    public function getUpdatedAt();

    /**
     * @param  \Alaress_Date_Format $updatedAt
     *
     * @return $this
     */
    public function setUpdatedAt($updatedAt);

    /**
     * @return \Alaress_Date_Format
     */
    public function getDeletedAt();

    /**
     * @param  \Alaress_Date_Format $deletedAt
     * @return $this
     */
    public function setDeletedAt($deletedAt);
}