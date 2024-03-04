<?php

/**
 * @copyright   Copyright 2022, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector\Liss\Base\Endpoint;

use Alaress\Database\Predicate\InvalidColumnException;
use DebugBar\DebugBarException;
use PhpXmlRpc\Request;
use Schoolbox\Resource\Storage\Mapper;
use Schoolbox\Resource\Storage\Model;
use Schoolbox\User;

/**
 * Cache a pushed payload locally
 */
class PayloadCache
{
    public const INPUT_PAYLOAD_PREFIX = 'suds_liss';


    /**
     * Part of the filename for the dailydata payload
     */
    public const PAYLOAD_DAILY_DATA = 'dailydata';


    /**
     * Constructor.
     *
     * @param Mapper $storageMapper
     */
    public function __construct(private \Schoolbox\Resource\Storage\Mapper $storageMapper)
    {
    }


    /**
     * $superuser allows the storage mapper to bypass the normal
     * file restrictions such as size, mimetype, etc.
     *
     * Use $name (an arbitrary string) to help identify filenames
     *
     * @param string $name
     * @param User $superuser
     * @param Request $message
     * @return Model
     * @throws \JsonException
     */
    public function cache(
        string $name,
        \Schoolbox\User $superuser,
        Request $message
    ): \Schoolbox\Resource\Storage\Model {
        $payload = serialize($message);

        $now = \Alaress_Date_Format::now();
        $expiryAt = \Alaress_Date_Format::now()->addWeeks(1);

        $filename = sprintf(
            '%s_%s_%s.log',
            self:: INPUT_PAYLOAD_PREFIX,
            $name,
            $now->getUsingFormat('Ymd_His')
        );

        return $this->storageMapper->acceptStream($filename, $payload, false, $expiryAt, $superuser);
    }


    /**
     * Loads a previously-stored liss payload from the cache
     *
     * @param string $type
     * @return null|Request
     * @throws InvalidColumnException
     * @throws DebugBarException
     */
    public function load(string $type): ?Request
    {
        $filename = sprintf(
            '%s_%s_',
            self:: INPUT_PAYLOAD_PREFIX,
            $type,
        );

        $pb = $this->storageMapper->getPredicateBuilder()
            ->like('filename', "{$filename}%");

        $files = $this->storageMapper->findByPredicates($pb)->sortBy('updated_at', 'desc');

        /* No file found; no trabajo
         * Just return null and skip processing elsewhere
         */
        if (!$files->count()) {
            return null;
        }

        $file = $files->first();

        new \Alaress_Probe($file);
        die();

        /* Once we can get the saved payload, try to reconstruct it
         * into something we can feed back into the Receiver
         */
        $mockMessage = unserialize(file_get_contents($filename), [
            'allowed_classes' => true
        ]);

        return $mockMessage;
    }
}
