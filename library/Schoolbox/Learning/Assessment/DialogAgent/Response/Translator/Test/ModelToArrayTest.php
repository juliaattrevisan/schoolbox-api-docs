<?php

/**
 * ModelToArrayTest
 *
 * See SD-10 for usages
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2026, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

// All new classes should use strict typing.
declare(strict_types=1);

namespace Schoolbox\Learning\Assessment\DialogAgent\Response\Translator\Test;

use Alaress\Authorizer\Authorizer;
use Aura\Router\Generator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Schoolbox\Learning\Assessment\DialogAgent;
use Schoolbox\Learning\Assessment\DialogAgent\Response;
use Schoolbox\Learning\Assessment\DialogAgent\Response\Status;
use Schoolbox\User;

/**
 * NextRoutes test
 */
class ModelToArrayTest extends TestCase
{
    /**
     * @param mixed $input
     * @param int   $expected
     */
    #[DataProvider('providerModelToArray')]
    public function testModelToArray($input, $expected)
    {
        /* Spawn a mock route generator
         */
        $routeGenerator = $this->createStub(Generator::class);
        $routeGenerator->method('generate')
            ->willReturnCallback(function ($route, $params) {
                return match ($route) {
                    'learningDialogAgent.getStatus' => sprintf(
                        '/learning/assessment/%s/status',
                        $params['assessmentId']
                    ),
                    'learningDialogAgent.getParticularResponse' => sprintf(
                        '/learning/discussion/%s/%s/view/%s',
                        $params['assessmentId'],
                        $params['studentId'],
                        $params['responseId']
                    ),
                    'learningDialogAgent.postMessage' => sprintf(
                        '/learning/discussion/%s/%s/message/%s',
                        $params['assessmentId'],
                        $params['studentId'],
                        $params['responseId']
                    ),
                    'learningDialogAgent.postSubmit' => sprintf(
                        '/learning/discussion/%s/%s/submit/%s',
                        $params['assessmentId'],
                        $params['studentId'],
                        $params['responseId']
                    ),
                    'learningDialogAgent.postReopen' => sprintf(
                        '/learning/discussion/%s/%s/reopen/%s',
                        $params['assessmentId'],
                        $params['studentId'],
                        $params['responseId']
                    ),
                    'learningDialogAgent.postAssistanceRequest' => sprintf(
                        '/learning/discussion/%s/%s/assistance/%s',
                        $params['assessmentId'],
                        $params['studentId'],
                        $params['responseId']
                    ),
                    'learningDialogAgent.postAssistanceResolve' => sprintf(
                        '/learning/discussion/%s/%s/resolve/%s',
                        $params['assessmentId'],
                        $params['studentId'],
                        $params['responseId']
                    ),
                    'learningDialogAgent.postReset' => sprintf(
                        '/learning/discussion/%s/%s/reset/%s',
                        $params['assessmentId'],
                        $params['studentId'],
                        $params['responseId']
                    ),
                    default => '',
                };
            });

        /* Spawn a mock authorizer
         */
        $authorizer = $this->createStub(Authorizer::class);
        $authorizer->method('isPathAuthorizedForUser')
            ->willReturn(true);

        /* Spawn a mock user
         */
        $currentUser = new User();

        $linkTranslator = new Response\Translator\Links($routeGenerator, $authorizer, $currentUser);
        $translator = (new Response\Translator\ModelToArray($linkTranslator));

        $outcome = $translator->__invoke($input);

        $this->assertEquals($expected, $outcome);
    }

    /**
     * Data provider for testModelToArray
     *
     * @return array
     */
    public static function providerModelToArray(): array
    {
        $assessmentId = 995;
        $responseId = 117;
        $duration = 123;
        $studentId = 230;

        $response = (new Response\Model())
            ->setId($responseId)
            ->setAssessable((new DialogAgent\Model())->setId($assessmentId)->setDuration($duration))
            ->setAuthoritative(true)
            ->setStartedAt(new \Alaress_Date_Format('2026-01-20 01:02:03', \Alaress_Date_Format::DATABASE_FORMAT))
            ->setSessionId('abcdefgh123456789')
            ->setAssistanceRequestedAt(new \Alaress_Date_Format('2026-01-20 01:05:05', \Alaress_Date_Format::DATABASE_FORMAT))
            ->setSubmittedAt(new \Alaress_Date_Format('2026-01-20 01:07:03', \Alaress_Date_Format::DATABASE_FORMAT))
            ->setSubmittedOnBehalfOf((new \Schoolbox\User()->setId($studentId)->setFirstname('Jimmy')->setLastname('Rings')));

        return [
            'generic usage' => [
                $response,
                [
                    'id' => $responseId,
                    'authoritative' => true,
                    'status' => Status::SUBMITTED,
                    'assistanceRequestedAt' => '2026-01-20T01:05:05+00:00',
                    'submittedAt' => '2026-01-20T01:07:03+00:00',
                    'startedAt' => '2026-01-20T01:02:03+00:00',
                    '_routes' => [
                        'status' => sprintf(
                            '/learning/assessment/%s/status',
                            $assessmentId
                        ),
                        'view' => sprintf(
                            '/learning/discussion/%s/%s/view/%s',
                            $assessmentId,
                            $studentId,
                            $responseId
                        ),
                        'message' => sprintf(
                            '/learning/discussion/%s/%s/message/%s',
                            $assessmentId,
                            $studentId,
                            $responseId
                        ),
                        'submit' => sprintf(
                            '/learning/discussion/%s/%s/submit/%s',
                            $assessmentId,
                            $studentId,
                            $responseId
                        ),
                        'reopen' => sprintf(
                            '/learning/discussion/%s/%s/reopen/%s',
                            $assessmentId,
                            $studentId,
                            $responseId
                        ),
                        'assistanceRequest' => sprintf(
                            '/learning/discussion/%s/%s/assistance/%s',
                            $assessmentId,
                            $studentId,
                            $responseId
                        ),
                        'assistanceResolve' => sprintf(
                            '/learning/discussion/%s/%s/resolve/%s',
                            $assessmentId,
                            $studentId,
                            $responseId
                        ),
                        'reset' => sprintf(
                            '/learning/discussion/%s/%s/reset/%s',
                            $assessmentId,
                            $studentId,
                            $responseId
                        ),
                    ]
                ]
            ]
        ];
    }
}
