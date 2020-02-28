<?php
/**
 * @copyright   Copyright 2020, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\TravelLog;

use Alaress\Redis;
use Twig\Environment;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

Class Renderer
{

    // Student's Travel Log should be requested and displayed only for verso school;
    private const VERSO_SCHOOL_CODE = 'verso';


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
    public function __construct(string $schoolCode, ?string $clientId, ?string $clientSecret, string $cachingTimeout)
    {
        $this->schoolCode             = $schoolCode;
        $this->clientId               = (string)$clientId;
        $this->clientSecret           = (string)$clientSecret;
        $this->cachingTimeout         = $cachingTimeout;
    }


    /**
     * @param Environment $twig
     * @param string      $childId
     * @return string
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
     */
    public function render(Environment $twig, string $childId): string
    {
        if ($this->isVerso() && $this->isCredentialValid()) {
            $travelLogs = (new TravelLog($this->clientId, $this->clientSecret, Redis::getInstance()))->getForStudentCached($childId, $this->cachingTimeout);

            if (isset($travelLogs['data']) && !empty($travelLogs['data'])) {
                return $twig->render('versoTravelLog.twig', ['travelLogs' => $travelLogs]);
            }
        }

        return '';

    }

    /**
     * Returns array of student travel logs for verso school
     *
     * @param string $childId
     * @return array
     */
    public function getTravelLog(string $childId): array
    {
        if ($this->isVerso() && $this->isCredentialValid()) {
            return (new TravelLog($this->clientId, $this->clientSecret, Redis::getInstance()))->getForStudentCached($childId, $this->cachingTimeout);
        }
        return [];
    }

    /**
     *
     * @return bool
     */
    private function isVerso(): bool
    {
        return $this->schoolCode === self::VERSO_SCHOOL_CODE;
    }

    /**
     *
     * Check whether credentials were provided and are not null
     * @return bool
     */
    private function isCredentialValid(): bool
    {
        return ($this->clientId !== '' && $this->clientSecret !== '');
    }

}
