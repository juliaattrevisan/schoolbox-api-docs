<?php
/**
 * @copyright   Copyright 2020, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Custom\Verso\TravelLog;

use Alaress\Redis;
use Twig\Environment;
use Twig_Error_Loader;
use Twig_Error_Runtime;
use Twig_Error_Syntax;

/**
 * Class Renderer
 *
 * Render a TravelLog data set for a given student $externalId
 *
 * @package Custom\Verso\TravelLog
 */
class Renderer
{

    /**
     * Student's Travel Log should be requested and displayed only for verso school
     * Note that this is the school code, not the site location
     */
    private const VERSO_SCHOOL_CODE = 'verso';


    /**
     * API times out after this many seconds elapse
     */
    private const DEFAULT_API_TIMEOUT = 300;


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
     * @var string
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
     * @param null|string $externalId
     * @return string
     * @throws Twig_Error_Loader
     * @throws Twig_Error_Runtime
     * @throws Twig_Error_Syntax
     */
    public function render(Environment $twig, ?string $externalId): string
    {
        $travelLogs = [];

        if ($this->isTravelLogAvailable($externalId)) {
            $travelLogs = (new TravelLog($this->clientId, $this->clientSecret, Redis::getInstance()))->getForStudentCached($externalId, $this->cachingTimeout);
        }

        return !empty($travelLogs) ? $twig->render('versoTravelLog.twig', ['travelLogs' => $travelLogs]) : '';
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

        /* 1. school_code must be set to "verso" (note that site_location is VIST...)
         * 2. api credentials must exist (they can be incorrect, but they must exist)
         * 3. an external id must exist
         */
        return
            ($this->schoolCode === self::VERSO_SCHOOL_CODE) &&
            ($this->clientId !== '' && $this->clientSecret !== '') &&
            ('' !== (string)$externalId);
    }
}
