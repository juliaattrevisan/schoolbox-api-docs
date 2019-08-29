<?php
/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Notif;

use Monolog\Handler\AbstractHandler;

/**
 * This handler never handles the record, ensuring it always bubbles.
 *
 * This is unlike the Null Handler which may consume the record and not bubble the record.
 *
 * @see \Monolog\Handler\NullHandler
 */
class SkipHandler extends AbstractHandler
{
    /** @inheritDoc */
    public function isHandling(array $record): bool
    {
        // This class does not handle any records
        return false;
    }

    /** @inheritDoc */
    public function handle(array $record): bool
    {
        // This class never consumed the record, it should always bubble
        return false;
    }
}
