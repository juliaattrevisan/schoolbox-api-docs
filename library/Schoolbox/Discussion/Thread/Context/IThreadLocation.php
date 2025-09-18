<?php

/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Discussion\Thread\Context;

use Schoolbox\Discussion\Thread\Model;
use Schoolbox\User;

/**
 * Interface for models that can be associated with a discussion thread.
 *
 * Any model which is associated with a thread must implement this interface.
 */
interface IThreadLocation
{
    /**
     * Get an identifier for this object.
     *
     * @return mixed
     */
    public function getId();


    /**
     * Which thread is associated with this object, if any?
     *
     * @return Model|null
     */
    public function getDiscussionThread(): ?Model;


    /**
     * Set the thread that is associated with this object, or set to null to
     * dissociate it.
     *
     * @param Model|null $discussionThread
     * @return mixed Will generally be this object, but that isn't mandated by
     *               this interface
     */
    public function setDiscussionThread(?Model $discussionThread);


    /**
     * Gets notifier class name for this thread comments
     *
     * @return string|null The fully qualified class name, or null if this has
     *                     no notifier.
     */
    public function getCommentNotifier(): ?string;

    /**
     * Who is the recipient for this thread context?
     *
     * @return User
     */
    public function getRecipient(): User;
}
