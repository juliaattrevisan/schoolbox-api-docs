<?php

/**
 * @copyright   Copyright 2020, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Custom\Vist\TravelLog;

use Alaress\Redis;
use Twig\Environment;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

/**
 * Class Renderer
 *
 * Render a TravelLog data set for a given student $externalId
 *
 * @package Custom\Vist\TravelLog
 */
class Renderer
{

    /**
     * Student's Travel Log should be requested and displayed only for verso school
     * Note that this is the school code, not the site location
     */
    private const VERSO_SCHOOL_CODE = 'VIST';


    /**
     * API times out after this many seconds elapse
     */
    private const DEFAULT_API_TIMEOUT = 300;


    /**
     * Icons are mapped based on event_type ['security, 'transport'] and direction ['in', 'out']
     */
    private const ICON_MAPPING = [
        'security' => [
            'in' => 'enter.svg',
            'out' => 'exit.svg'
        ],
        'transport' => [
            'in' => 'enter-bus.svg',
            'out' => 'exit-bus.svg'
        ],
    ];


    /**
     * @var string
     */
    private $schoolCode;


    /**
     * @var ?string
     */
    private $clientId;


    /**
     * @var ?string
     */
    private $clientSecret;


    /**
     * @var int
     */
    private $cachingTimeout;


    /**
     * Renderer constructor.
     *
     * @param string      $schoolCode
     * @param string|null $clientId
     * @param string|null $clientSecret
     * @param string|null $cachingTimeout
     */
    public function __construct(string $schoolCode, ?string $clientId, ?string $clientSecret, ?string $cachingTimeout)
    {
        $this->schoolCode     = $schoolCode;
        $this->clientId       = (string)$clientId;
        $this->clientSecret   = (string)$clientSecret;

        /* You can't have a cachingTimeout of zero, but ok
         */
        $this->cachingTimeout = (int)$cachingTimeout ?: self::DEFAULT_API_TIMEOUT;
    }


    /**
     * Render a twig fragment containing the TravelLog data for $externalId
     *
     * @param Environment $twig
     * @param string|null $externalId
     * @param bool        $showDate
     * @return string
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
     */
    public function render(Environment $twig, ?string $externalId, bool $showDate = false): string
    {
        $travelLogs = [];

        if ($this->isTravelLogAvailable($externalId)) {
            $travelLogs = (new TravelLog($this->clientId, $this->clientSecret, Redis::getInstance()))->getForStudentCached($externalId, $this->cachingTimeout);

            /* $travelLogs might be an array of error data when the externalId wasn't found
             * in the third party system:

               array (size=3)
                  'status' => int 404
                  'title' => string 'Not found' (length=9)
                  'message' => string 'id "135" not found.' (length=19)
             */
            if (!isset($travelLogs['data'])) {
                $travelLogs = [];
            } else {
                /* Sort chronologically
                 */
                usort($travelLogs['data'], function ($a, $b) {
                    return strtotime($a['timestamp']) > strtotime($b['timestamp']);
                });

                /* Augment the array with an icon
                 */
                foreach ($travelLogs['data'] as &$record) {
                    $record['icon'] = $this->decideIcon($record['event_type'], $record['direction']);
                }

                unset($record);
            }
        }

        return !empty($travelLogs) ? $twig->render('travelLog.twig', [
            'travelLogs' => $travelLogs['data'],
            'date' => $showDate ? $travelLogs['data'][0]['timestamp'] : ''
        ]) : '';
    }


    /**
     * Decide an icon for this record
     * event_type and direction are not case sensitive for the purposes of deciding icons
     *
     * @param string $eventType
     * @param string $direction
     * @return mixed
     */
    private function decideIcon(string $eventType, string $direction)
    {
        return self::ICON_MAPPING[strtolower($eventType)][strtolower($direction)];
    }


    /**
     * Decide if a TravelLog can be produced for user $externalId
     * Must fulfil multiple criteria
     *
     * @param null|string $externalId
     * @return bool
     */
    public function isTravelLogAvailable(?string $externalId): bool
    {

        /* 1. school_code must be set to "verso" (note that site_school_code is VIST...)
         * 2. api credentials must exist (they can be incorrect, but they must exist)
         * 3. an external id must exist
         */
        return
            ($this->schoolCode === self::VERSO_SCHOOL_CODE) &&
            ($this->clientId !== '' && $this->clientSecret !== '') &&
            ('' !== (string)$externalId);
    }
}
