<?php
/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\External\Importer\Endpoint;

use Psr\Log\LoggerInterface;
use Schoolbox\Assessment\Term;
use Schoolbox\CampusMapper;
use Schoolbox\Config\Loader;
use Schoolbox\External\ClassEntryMapper;
use Schoolbox\External\ClassInvolvementMapper;
use Schoolbox\External\ClassPeriodMapper;
use Schoolbox\External\Connector\Isams\Base\Endpoint as IsamsEndpoint;
use Schoolbox\External\Connector\Kamar\Base\Endpoint as KamarEndpoint;
use Schoolbox\External\Profile as UserProfile;
use Schoolbox\External\TimetableCampusMapper;
use Schoolbox\External\TimetableCycleMapper;
use Schoolbox\External\TimetablePeriodMapper;
use Schoolbox\GenericCollection;
use Schoolbox\UserMapper;
use UnexpectedValueException;

/**
 * Class Factory
 *
 * Create a set of Composites which import Profile and Timetable data
 * from third-party endpoints into schoolbox
 *
 * @package Schoolbox\External\Importer\Endpoint
 */
class Factory
{

    /**
     * The family of importers that we can spawn
     */
    public const CSV = 'csv';
    public const ISAMS = 'isams';
    public const KAMAR = 'kamar';
    public const SENTRAL = 'sentral';


    /**
     * API Endpoint url
     *
     * @var string
     */
    private $host;


    /**
     * API Endpoint access token
     *
     * @var string
     */
    private $key;


    /**
     * @var bool
     */
    private $perform;


    /**
     * @var LoggerInterface
     */
    private $logger;


    /**
     * Factory constructor.
     *
     * @param string          $host
     * @param string          $key
     * @param bool            $perform
     * @param LoggerInterface $logger
     */
    public function __construct(string $host, string $key, bool $perform, LoggerInterface $logger)
    {
        $this->host = $host;
        $this->key = $key;
        $this->perform = $perform;
        $this->logger = $logger;
    }


    /**
     * @param string|null $file
     * @param string      $type
     *
     * @return null|GenericCollection
     * @throws UnexpectedValueException
     */
    public function produce(?string $file, string $type): ?GenericCollection
    {
        switch ($type) {
            case self::ISAMS:
                return $this->produceIsams($file);

            case self::KAMAR:
                return $this->produceKamar();

            case self::SENTRAL:
            default:
                // Use manual csv imports
                return null;
        }
    }


    /**
     * Produce the composites with optional Mock api connection
     * (use a locally-stored file path)
     *
     * @param string|null $file
     * @return GenericCollection
     * @throws UnexpectedValueException
     */
    private function produceIsams(?string $file): GenericCollection
    {
        if ($file) {
            $this->logger->info("Loading from locally-cached xml file: {$file}");
            $api = new IsamsEndpoint\MockApi($file);
        } else {
            $this->logger->info('Accessing api endpoint');
            $api = new IsamsEndpoint\Api($this->host, $this->key);
        }

        /* Connect and shank data
         */
        $batchxml = new IsamsEndpoint\Batchxml($api);

        /* Import each piece of the puzzle: Profiles and Timetable
         *
         * They're distinct pieces so instead of making a super-composite, I just
         * do them in sequence, which gives me more dexterity later
         */
        return (new GenericCollection(IStrategy::class))
            ->add(new IsamsEndpoint\Profile\Composite(
                $this->logger,
                $this->perform,
                $batchxml,
                UserProfile\Mapper::getInstance(),
                UserProfile\Student\Mapper::getInstance(),
                UserProfile\Parent\Mapper::getInstance(),
                UserProfile\Staff\Mapper::getInstance(),
                UserProfile\Relationship\Mapper::getInstance()
            ))
            ->add(new IsamsEndpoint\Timetable\Composite(
                $this->logger,
                $this->perform,
                $batchxml,
                CampusMapper::getInstance(),
                UserMapper::getInstance(),
                TimetableCampusMapper::getInstance(),
                TimetableCycleMapper::getInstance(),
                TimetablePeriodMapper::getInstance(),
                ClassEntryMapper::getInstance(),
                ClassInvolvementMapper::getInstance(),
                ClassPeriodMapper::getInstance()
            ));
    }


    /**
     * We never reach out to kamar so we technically don't need the $file option
     *
     * @return GenericCollection
     * @throws UnexpectedValueException
     */
    private function produceKamar(): GenericCollection
    {
        $this->logger->info('Accessing local xml endpoints');

        /* Set up composite bus - everybody's xml gets on board and shuttled off
         * into schoolbox town
         */
        $bus = new GenericCollection(IStrategy::class);

        /* Profile and student timetable xmls come with a datetime suffix
         *
         * Timetable Composite requires access across multiple xml files cross-referencing nodes
         * in profile xml so you can identify who is in a class ($container)
         */
        $dirhandle = opendir($this->host);

        $xml = [
            KamarEndpoint\Receiver::XML_FULL => [],
            KamarEndpoint\Receiver::XML_STUDENTTIMETABLES => []
        ];

        /* Make a stack of profile xmls (full_ ... .xml)
         * Make a stack oo student timetable xmls (studenttimetables_ ... .xml)
         */
        $numFiles = 0;

        while ($xmlFile = readdir($dirhandle)) {
            if ($xmlFile !== '.' && $xmlFile !== '..') {
                if (strpos($xmlFile, KamarEndpoint\Receiver::XML_FULL) !== false) {
                    $xml[KamarEndpoint\Receiver::XML_FULL][] = $xmlFile;
                    $numFiles++;
                } elseif (strpos($xmlFile, KamarEndpoint\Receiver::XML_STUDENTTIMETABLES) !== false) {
                    $xml[KamarEndpoint\Receiver::XML_STUDENTTIMETABLES][] = $xmlFile;
                    $numFiles++;
                }
            }
        }

        /* If there aren't any files to process, do nothing
         */
        if (!$numFiles) {
            $this->logger->info('No xml files here; noop');

            return $bus;
        }

        /* Set up the kamar directory which is where all their xml files go
         */
        $bus->add(new KamarEndpoint\Setup(
            $this->logger,
            Loader::getInstance()->get('stream_dir')
        ));

        /* Purge previously-imported profile relationships and classes
         */
        $bus->add(new KamarEndpoint\Prepare(
            $this->logger,
            $this->perform,
            UserProfile\Relationship\Mapper::getInstance(),
            ClassEntryMapper::getInstance(),
            ClassInvolvementMapper::getInstance(),
            ClassPeriodMapper::getInstance()
        ));

        /* Load all the Profile xmls
         */
        $container = new KamarEndpoint\Profile\Container();

        foreach ($xml[KamarEndpoint\Receiver::XML_FULL] as $xmlFile) {
            $bus->add(new KamarEndpoint\Profile\Composite(
                $this->logger,
                $this->perform,
                new KamarEndpoint\Batchxml(new KamarEndpoint\Api($this->host . '/' . $xmlFile)),
                $container,
                UserProfile\Mapper::getInstance(),
                UserProfile\Student\Mapper::getInstance(),
                UserProfile\Parent\Mapper::getInstance(),
                UserProfile\Staff\Mapper::getInstance(),
                UserProfile\Relationship\Mapper::getInstance()
            ));
        }

        /* Set up the Timetable definition separately from the masses of timetable-related xml files
         */
        $bus->add(new KamarEndpoint\Timetable\Definition(
            $this->logger,
            $this->perform,
            CampusMapper::getInstance(),
            TimetableCampusMapper::getInstance(),
            TimetableCycleMapper::getInstance(),
            TimetablePeriodMapper::getInstance()
        ));

        /* Cache a list of known users and periods
         * Every student timetable processing uses info stored there
         * Recycle this instance in every iteration
         */
        $lookup = new KamarEndpoint\Timetable\Lookup(TimetablePeriodMapper::getInstance(), UserMapper::getInstance());

        /* Load all the Student Timetable xmls
         */
        foreach ($xml[KamarEndpoint\Receiver::XML_STUDENTTIMETABLES] as $xmlFile) {
            $bus->add(new KamarEndpoint\Timetable\Composite(
                $this->logger,
                $this->perform,
                $container,
                new KamarEndpoint\Batchxml(new KamarEndpoint\Api($this->host . '/' . $xmlFile)),
                $lookup,
                ClassEntryMapper::getInstance(),
                ClassInvolvementMapper::getInstance(),
                ClassPeriodMapper::getInstance(),
                Term\Mapper::getInstance()
            ));
        }

        /* Archive the xmls into a folder when we're done processing
         * We could delete them at any time, but if we just deleted it at the end of every
         * processing we'd have no idea how the schoolbox instance was populated - we'd
         * have destroyed the source. Instead, chuck them into a folder which we can remove
         * whenever we need
         */
        $bus->add(new KamarEndpoint\Cleanup($this->logger, $this->perform, Loader::getInstance()->get('stream_dir')));

        return $bus;
    }
}
