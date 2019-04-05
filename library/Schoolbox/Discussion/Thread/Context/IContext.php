<?php
/**
 * @copyright Copyright 2019, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Discussion\Thread\Context;

use Schoolbox\Discussion\Thread\Model;

/**
 * Interface for models that can be associated with a discussion thread.
 *
 * Any model which is associated with a thread must implement this interface.
 */
interface IContext
{
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
}
