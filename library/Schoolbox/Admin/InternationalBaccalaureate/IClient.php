<?php

/**
 * IBIS SOAP client interface
 *
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Admin\InternationalBaccalaureate;

interface IClient
{
    public function registerCandidates(RegisterCandidatesRequest $request): RegisterCandidatesResponse;
}
