<?php

/**
 * @copyright Copyright 2025, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\Realtime\Pipe;

use Ratchet\Session\Serialize\HandlerInterface;

/**
 * Session serialize handler implementation which works with the `php_serialize`
 * session serialize handler.
 *
 * This handler simply uses `serialize` and `unserialize`.
 *
 * @see https://www.php.net/manual/en/session.configuration.php#ini.session.serialize-handler
 */
class PhpSerializeSessionHandler implements HandlerInterface
{
    /**
     * @param array $data
     * @return string
     */
    public function serialize(array $data): string
    {
        return serialize($data);
    }

    /**
     * @param string $raw
     * @return mixed
     */
    public function unserialize($raw): mixed
    {
        return unserialize($raw);
    }
}
