<?php

/**
 * Reroute filter: throws away all messages if messaging is not enabled.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Realtime\Routing\Filter;

use Alaress\Async\Notification\Instance\Instance;
use Schoolbox\IConfigLoader;
use Schoolbox\User;

class MessagingEnabledFilter implements FilterInterface
{
    /**
     * @var IConfigLoader
     */
    protected $config;

    /**
     * @param IConfigLoader $config
     */
    public function __construct(IConfigLoader $config)
    {
        $this->config = $config;
    }

    /**
     * Throws away message if messaging is disabled globally.
     *
     * @param Instance $message
     * @param User     $user
     *
     * @throws FilterException
     * @return bool
     */
    public function validate(Instance $message, User $user)
    {
        if (!$this->config->get('messaging_enabled')) {
            throw new FilterException("Messaging has been disabled globally");
        }
        return true;
    }
}
