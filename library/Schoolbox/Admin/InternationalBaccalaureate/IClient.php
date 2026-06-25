<?php

/**
 * IBIS SOAP client interface
 *
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Admin\InternationalBaccalaureate;

/**
 * Contract for the small portion of the IBIS SOAP API used by registration
 * workflows.
 */
interface IClient
{
    /**
     * Submit candidate identity/profile data to IBIS and return the parsed
     * candidate-number response.
     * @param RegisterCandidatesRequest $request
     * @return RegisterCandidatesResponse
     */
    public function registerCandidates(RegisterCandidatesRequest $request): RegisterCandidatesResponse;

    /**
     * Submit session and subject enrolment data for candidates who already
     * have an IB candidate number.
     * @param RegisterSessionsRequest $request
     * @return RegisterSessionsResponse
     */
    public function registerSessions(RegisterSessionsRequest $request): RegisterSessionsResponse;
}
