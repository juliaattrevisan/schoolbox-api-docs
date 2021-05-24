<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Alaress\Async\Notification\Cloud\Google;

use Alaress\Async\Notification\Cloud\ResponseInterface;
use OutOfBoundsException;
use Psr\Http\Message\ResponseInterface as HttpResponse;
use RangeException;

/**
 * Class Response
 * TODO [SB-6620] this class is waiting to be implemented
 *
 * @package Alaress\Async\Notification\Cloud\Google
 */
class Response implements ResponseInterface
{

    /** @var HttpResponse[] */
    protected $responses = array();

    /** @var int */
    protected $successes = 0;

    /** @var int */
    protected $failures = 0;

    /** @var int */
    protected $canonicalIds = 0;

    /** @var array */
    protected $replacements = array();

    /** @var array */
    protected $errors = array();


    /**
     * @param array        $registrationIds
     * @param HttpResponse $response
     * @return void
     */
    public function append(array $registrationIds, HttpResponse $response)
    {
        // so we can recalculate all this later if need be
        $this->responses[] = array($registrationIds, $response);

        $statusCode = $response->getStatusCode();

        if ($statusCode == 200) {
            // process successful request
            $data = json_decode($response->getBody()->getContents());

            $this->successes += $data['success'];
            $this->failures += $data['failure'];
            $this->canonicalIds += $data['canonical_ids'];

            if ($data['failure'] + $data['canonical_ids'] == 0) {
                // we're done
                return;
            }

            foreach ($data['results'] as $i => $result) {
                if (isset($result['message_id'])) {
                    if (isset($result['registration_id'])) {
                        $this->replacements[$registrationIds[$i]] = $result['registration_id'];
                    }
                } elseif (isset($result['error'])) {
                    $this->errors[$registrationIds[$i]] = $result['error'];
                }
            }
        } elseif ($statusCode == 400) {
            throw new OutOfBoundsException("Bad request: " . $response->getBody()->getContents(), 400);
        } elseif ($statusCode == 401) {
            throw new OutOfBoundsException("The given GCM API key is not valid.", 401);
        } elseif ($statusCode >= 500) {
            // 5xx SERVER ERROR
            if ($response->hasHeader('Retry-After')) {
                $retryAfter = $response->getHeader('Retry-After');

                if (!is_int($retryAfter)) {
                    $retryAfter = strtotime($retryAfter) - time();
                }

                throw new RangeException("Retry after $retryAfter", $retryAfter);
            }

            // other 500 error?
        }
    }


    /**
     * @return int
     */
    public function getSuccessCount()
    {
        return $this->successes;
    }


    /**
     * @return int
     */
    public function getFailureCount()
    {
        return $this->failures;
    }


    /**
     * @return int
     */
    public function getCanonicalIdCount()
    {
        return $this->canonicalIds;
    }


    /**
     * @return array
     */
    public function getReplacements()
    {
        return $this->replacements;
    }


    /**
     * @return array
     */
    public function getErrors()
    {
        return $this->errors;
    }
}
