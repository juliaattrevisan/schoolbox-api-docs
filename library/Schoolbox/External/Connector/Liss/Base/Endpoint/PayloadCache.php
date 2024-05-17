<?php

/**
 * @copyright   Copyright 2022, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector\Liss\Base\Endpoint;

use Alaress\Database\Predicate\InvalidColumnException;
use League\Flysystem\FilesystemException;
use PhpXmlRpc\Request;
use Psr\Log\LoggerInterface;
use Schoolbox\External\Connector\Liss\Base\Endpoint\TimetableCalendar\Codec\PayloadFilename;
use Schoolbox\Resource\Storage\FilesystemProxy;
use Schoolbox\Resource\Storage\Mapper;
use Schoolbox\Resource\Storage\Model;
use Schoolbox\User;

/**
 * Cache a pushed payload locally
 */
class PayloadCache
{
    public const DEFAULT_SOURCE_NAME = 'default';


    /**
     * Part of the filename for the dailydata payload
     */
    public const PAYLOAD_DAILY_DATA = 'dailydata';
    public const PAYLOAD_BELLTIMES = 'belltimes';
    public const PAYLOAD_CLASSES = 'classes';
    public const PAYLOAD_CLASS_MEMBERSHIPS = 'classmemberships';
    public const PAYLOAD_TIMETABLE = 'timetable';


    /**
     * A shortcut to all the payloads we use
     */
    public const PAYLOAD_ALL = [
        self::PAYLOAD_DAILY_DATA,
        self::PAYLOAD_BELLTIMES,
        self::PAYLOAD_CLASSES,
        self::PAYLOAD_CLASS_MEMBERSHIPS,
        self::PAYLOAD_TIMETABLE
    ];


    /**
     * A list of payloads that would be used in scheduled import.
     * It uses the PayloadList to generate this, remembering its result to save repeatedly generating it
     *
     * @var array|null
     */
    private ?array $scheduled = null;


    /**
     * Constructor.
     *
     * @param Mapper $storageMapper
     * @param FilesystemProxy $filesystemProxy Used when loading previously-cached payloads
     * @param LoggerInterface $logger
     */
    public function __construct(
        private \Schoolbox\Resource\Storage\Mapper $storageMapper,
        private \Schoolbox\Resource\Storage\FilesystemProxy $filesystemProxy,
        private LoggerInterface $logger
    ) {
    }


    /**
     * $superuser allows the storage mapper to bypass the normal
     * file restrictions such as size, mimetype, etc.
     *
     * Use $name (an arbitrary string) to help identify filenames
     *
     * @param string $sourceName
     * @param string $lissMethodName
     * @param User $superuser
     * @param Request $message
     * @return Model
     */
    public function cache(
        string $sourceName,
        string $lissMethodName,
        \Schoolbox\User $superuser,
        Request $message
    ): \Schoolbox\Resource\Storage\Model {
        $filename = (new PayloadFilename())->encode($lissMethodName, $sourceName);
        $payload = serialize($message);
        $expiryAt = \Alaress_Date_Format::now()->addWeeks(1);

        return $this->storageMapper->acceptStream($filename, $payload, false, $expiryAt, $superuser);
    }


    /**
     * Decide if we have any storage files corresponding to a liss payload
     *
     * Mega caveat: this only checks filenames. You could potentially bust stuff by circumstantially
     * uploading a filename that matches something we look for here; we should be using the storage
     * flags 'public' and 'private' to help prevent this problem a bit, but those haven't been
     * implemented in storage yet. Or, we look for file ownership somehow; or make the filename even
     * harder to accidentally collide
     *
     * @return bool
     * @throws InvalidColumnException
     */
    public function hasPayloads(): bool
    {
        /* Potentially generate $scheduled just once and inject its result everywhere,
         * saving you rebuilding this list for each strat just to get a subset
         */
        if (null === $this->scheduled) {
            $this->scheduled = (new PayloadList($this->storageMapper))->generate()['scheduled'];
        }

        /* Files that could potentially exist in storage at any given time
         */
        foreach($this->scheduled as $lissMethodName => $data) {
            if (\count($data['sources'])) {
                return true;
            }
        }

        return false;
    }


    /**
     * Loads a previously-stored liss payload from the cache, using the same list of files
     * as Most Recent Liss Input Payloads.
     *
     * @param string $lissMethodName
     * @return array
     * @throws FilesystemException
     */
    public function load(string $lissMethodName): array
    {
        /* Potentially generate $scheduled just once and inject its result everywhere,
         * saving you rebuilding this list for each strat just to get a subset
         */
        if (null === $this->scheduled) {
            $this->scheduled = (new PayloadList($this->storageMapper))->generate()['scheduled'];
        }

        $stack = [];

        foreach ($this->scheduled[$lissMethodName]['sources'] as $sourceName => $file) {
            $this->logger->debug(sprintf(
                'Processing liss payload %s from storage, using source "%s"',
                $file->getFilename(),
                $sourceName
            ));

            /* Once we can get the saved payload, try to reconstruct it
             * into something we can feed back into the Receiver
             */
            $mockMessage = unserialize($this->filesystemProxy->read($file), [
                'allowed_classes' => true
            ]);

            $stack[$sourceName] = [
                'filename' => $file->getFilename(),
                'payload' => $mockMessage
            ];
        }

        return $stack;
    }
}
