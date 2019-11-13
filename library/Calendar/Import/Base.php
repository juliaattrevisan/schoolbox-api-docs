<?php
/**
 * Base
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Calendar\Import;

use Schoolbox\Calendar\EventTypeMapper;
use Schoolbox\GenericCollection;
use Schoolbox\User;

abstract class Base
{

    /**
     * The person to whom these imported events are relevant
     *
     * @var User
     */
    protected $user;

    /**
     * The person who spawned the event
     *
     * @var User
     */
    protected $author;

    /**
     * A list of selected event_folder relations
     *
     * @var array
     */
    protected $categoryList = [];

    /**
     * A list of selected event_group relations
     *
     * @var array
     */
    protected $groupList = [];

    /**
     * A list of selected event_campus relations
     *
     * @var array
     */
    protected $campusList = [];

    /**
     * Access level for each event imported
     *
     * @var int
     */
    protected $access = 0;

    /**
     * The source file from which to import
     *
     * @var string
     */
    protected $file;

    /**
     * The outcome of each event's processing
     * This is an array of name, status, and error message values
     *
     * @var array
     */
    protected $output = [];

    /**
     * All event types, from the lookup
     *
     * @var GenericCollection
     */
    protected $allEventTypes;

    /**
     * Source of import
     *
     * @var string|null
     */
    protected $source;


    /**
     * Base constructor.
     *
     * @param User $user
     * @param User $author
     * @param array $categoryList
     * @param array $groupList
     * @param array $campusList
     * @param int $access
     * @param string $file
     * @param string|null $source
     */
    public function __construct(User $user, User $author, array $categoryList, array $groupList, array $campusList, int $access, string $file, string $source = null)
    {
        $this->setUser($user);
        $this->setAuthor($author);
        $this->setCategoryList($categoryList);
        $this->setGroupList($groupList);
        $this->setCampusList($campusList);
        $this->setAccess($access);
        $this->setFile($file);
        $this->setSource($source);

        /* All the available event types
         */
        $eventTypeMapper = new EventTypeMapper();
        $this->setAllEventTypes($eventTypeMapper->findAll());
    }

    /**
     * Load events from file into schoolbox
     */
    abstract public function load();

    /* Getters/Setters
     */

    /**
     * Set author
     *
     * @param User $author
     * @return Base
     */
    protected function setAuthor(User $author): Base
    {
        $this->author = $author;
        return $this;
    }

    /**
     * Get author
     *
     * @return User
     */
    protected function getAuthor(): User
    {
        return $this->author;
    }

    /**
     * Set campus list
     *
     * @param array $campusList
     * @return Base
     */
    protected function setCampusList($campusList): Base
    {
        $this->campusList = $campusList;
        return $this;
    }

    /**
     * Get campus list
     *
     * @return array
     */
    protected function getCampusList(): array
    {
        return $this->campusList;
    }

    /**
     * Set file
     *
     * @param string $file
     * @return Base
     */
    protected function setFile($file): Base
    {
        $this->file = $file;
        return $this;
    }

    /**
     * Get file
     *
     * @return string
     */
    protected function getFile(): string
    {
        return $this->file;
    }

    /**
     * Set group list
     *
     * @param array $groupList
     * @return Base
     */
    protected function setGroupList($groupList): Base
    {
        $this->groupList = $groupList;
        return $this;
    }

    /**
     * Get group list
     *
     * @return array
     */
    protected function getGroupList(): array
    {
        return $this->groupList;
    }

    /**
     * Set user
     *
     * @param User $user
     * @return Base
     */
    protected function setUser(User $user): Base
    {
        $this->user = $user;
        return $this;
    }

    /**
     * Get user
     *
     * @return User
     */
    protected function getUser(): User
    {
        return $this->user;
    }

    /**
     * Set output
     *
     * @param array $output
     * @return Base
     */
    protected function setOutput(array $output): Base
    {
        $this->output = $output;
        return $this;
    }

    /**
     * Get output
     *
     * @return array
     */
    public function getOutput(): array
    {
        return $this->output;
    }

    /**
     * Set all event types
     *
     * @param GenericCollection $allEventTypes
     * @return Base
     */
    public function setAllEventTypes(GenericCollection $allEventTypes): Base
    {
        $this->allEventTypes = $allEventTypes;
        return $this;
    }

    /**
     * Get all event types
     *
     * @return GenericCollection
     */
    public function getAllEventTypes(): GenericCollection
    {
        return $this->allEventTypes;
    }

    /**
     * Set category list
     *
     * @param array $categoryList
     * @return Base
     */
    protected function setCategoryList(array $categoryList): Base
    {
        $this->categoryList = $categoryList;
        return $this;
    }

    /**
     * Get category list
     *
     * @return array
     */
    protected function getCategoryList(): array
    {
        return $this->categoryList;
    }

    /**
     * Set access
     *
     * @param int $access
     * @return Base
     */
    protected function setAccess(int $access): Base
    {
        $this->access = $access;
        return $this;
    }

    /**
     * Get access
     *
     * @return int
     */
    protected function getAccess(): int
    {
        return $this->access;
    }

    /**
     * @return string|null
     */
    public function getSource(): ?string
    {
        return $this->source;
    }


    /**
     * @param string|null $source
     * @return Base
     */
    public function setSource(?string $source): Base
    {
        $this->source = $source;
        return $this;
    }
}
