<?php
/**
 * @copyright   Copyright 2020, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Custom\Verso\TravelLog;

use Alaress\Redis;
use Twig\Environment;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;
use Twig_Error_Loader;
use Twig_Error_Runtime;
use Twig_Error_Syntax;

Class Renderer
{

    // Student's Travel Log should be requested and displayed only for verso school;
    private const VERSO_SCHOOL_CODE = 'verso';

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
     * @param string                                      $schoolCode
     * @param string|null                                 $clientId
     * @param string|null                                 $clientSecret
     * @param string                                      $cachingTimeout
     */
    public function __construct(string $schoolCode, ?string $clientId, ?string $clientSecret, ?string $cachingTimeout)
    {
        $this->schoolCode             = $schoolCode;
        $this->clientId               = (string)$clientId;
        $this->clientSecret           = (string)$clientSecret;
        $this->cachingTimeout         = $cachingTimeout ?? self::DEFAULT_API_TIMEOUT;
    }


    /**
     * Render a twig fragment containing the TravelLog data for $childId
     *
     * @param Environment $twig
     * @param string      $childId
     * @return string
     * @throws Twig_Error_Loader
     * @throws Twig_Error_Runtime
     * @throws Twig_Error_Syntax
     */
    public function render(Environment $twig, string $childId): string
    {
        $travelLogs = $this->getTravelLog($childId);

        return !empty($travelLogs) ? $twig->render('versoTravelLog.twig', ['travelLogs' => $travelLogs]) : '';
    }


    /**
     * Returns array of student travel logs for verso school
     *
     * @param null|string $childId
     * @return array
     */
    public function getTravelLog(?string $childId): array
    {
        if ($this->isTravelLogAvailable($childId)) {
            return (new TravelLog($this->clientId, $this->clientSecret, Redis::getInstance()))->getForStudentCached($childId, $this->cachingTimeout);
        }

        return [];
    }


    /**
     * Decide if a TravelLog can be produced for user $externalId
     * Must fulfil multiple criteria
     *
     * @param string|null $externalId
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
            (null !== $externalId);
    }
}
