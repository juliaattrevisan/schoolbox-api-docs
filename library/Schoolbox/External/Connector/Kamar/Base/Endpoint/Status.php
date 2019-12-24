<?php
/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\External\Connector\Kamar\Base\Endpoint;

use Schoolbox\External\Importer\Endpoint\Status\IStatus;

/**
 * Class Status
 *
 * @package Schoolbox\External\Connector\Isams\Base\Endpoint
 */
class Status implements IStatus
{

    /**
     * Expected xml files
     */
    private const EXPECTED_PROFILE_XML = 'profile.xml';
    private const EXPECTED_TIMETABLE_XML = 'studenttimetables.xml';

    private const EXPECTED_XML = [
        self::EXPECTED_PROFILE_XML,
        self::EXPECTED_TIMETABLE_XML
    ];


    /**
     * @var string
     */
    private $path;


    /**
     * Status constructor.
     *
     * @param string $path
     */
    public function __construct(string $path)
    {
        $this->path = $path;
    }


    /**
     * @inheritDoc
     *
     * @return bool
     */
    public function isReachable(): bool
    {

        /* We cached the file locally so if it exists, then it's reachable
         * Whether or not it contains valid data is a different question altogether
         */
        foreach (self::EXPECTED_XML as $xml) {
            $api = new Api("{$this->path}/{$xml}");

            if (!$api->isXmlFileExtant()) {
                return false;
            }
        }

        return true;
    }


    /**
     * @inheritDoc
     *
     * @return string
     */
    public function getReport(): string
    {
        $specifications = [
            new Profile\Specification(null, new Batchxml(new Api("{$this->path}/" . self::EXPECTED_PROFILE_XML))),
            new Timetable\Specification(null, new Batchxml(new Api("{$this->path}/" . self::EXPECTED_TIMETABLE_XML))),
        ];

        $result = [];

        foreach ($specifications as $specification) {
            try {
                $result[] = implode("\n", $specification->check());
            } catch (\RuntimeException $e) {
                $result[] = "Cannot access api endpoint: {$e->getMessage()}";
            }
        }

        return implode("\n", $result);
    }
}
