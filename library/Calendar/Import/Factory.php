<?php
/**
 * Factory
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Calendar\Import;

use Schoolbox\User;

class Factory
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
     * Source of import
     *
     * @var string|null
     */
    protected $source;

    /**
     * Factory constructor.
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
    }

    /**
     * Spawn strategy
     *
     * @return mixed
     */
    public function build()
    {
        $classname = '\Calendar\Import\Csv';

        if (stristr(file_get_contents($this->getFile(), null, null, 0, 20), 'BEGIN:VCALENDAR')) {
            $classname = '\Calendar\Import\Vcal';
        }

        return new $classname($this->getUser(), $this->getAuthor(), $this->getCategoryList(), $this->getGroupList(), $this->getCampusList(), $this->getAccess(), $this->getFile(), $this->getSource());
    }

    /* Getters/Setters
     */

    /**
     * Set author
     *
     * @param User $author
     * @return Factory
     */
    protected function setAuthor(User $author): Factory
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
     * @return Factory
     */
    protected function setCampusList($campusList): Factory
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
     * @return Factory
     */
    protected function setFile($file): Factory
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
     * @return Factory
     */
    protected function setGroupList($groupList): Factory
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
     * @return Factory
     */
    protected function setUser(User $user): Factory
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
     * Set category list
     *
     * @param array $categoryList
     * @return Factory
     */
    protected function setCategoryList(array $categoryList): Factory
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
     * @return Factory
     */
    protected function setAccess(int $access): Factory
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
     * @return Factory
     */
    public function setSource(?string $source): Factory
    {
        $this->source = $source;
        return $this;
    }
}
