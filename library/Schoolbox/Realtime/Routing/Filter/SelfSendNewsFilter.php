<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Realtime\Routing\Filter;

use Alaress\Async\Notification\Instance\Instance;
use Schoolbox\Realtime\Message\NSMapper;
use Schoolbox\User;

class SelfSendNewsFilter extends SelfSendFilter
{

    /**
     * This is to appease JPC
     *
     * @param Instance $message
     * @param User     $user
     * @throws FilterException
     * @return bool
     */
    public function validate(Instance $message, User $user)
    {
        try {
            $result = parent::validate($message, $user);
        } catch (FilterException $e) {
            // was a self send, but check this isn't news
            $result = false;
        }

        foreach ($message->getTopics() as $topic) {
            $result = $result || (strpos($topic, NSMapper::NS_NEWS) === 0);
        }

        if ($result) {
            return true;
        }

        throw new FilterException("User effected this message, and this isn't a news article");
    }
}
