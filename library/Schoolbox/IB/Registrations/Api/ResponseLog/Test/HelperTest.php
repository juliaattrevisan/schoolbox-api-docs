<?php

/**
 * IB registration API response log helper test
 *
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\IB\Registrations\Api\ResponseLog\Test;

use PHPUnit\Framework\TestCase;
use Schoolbox\GenericCollection;
use Schoolbox\IB\Registrations\Api\ResponseLog\Helper;
use Schoolbox\IB\Registrations\Api\ResponseLog\Mapper;
use Schoolbox\IB\Registrations\Api\ResponseLog\Model;
use Schoolbox\IB\Registrations\Sessions\Model as RegistrationSessionModel;
use Schoolbox\IB\Registrations\Sessions\Student\Model as RegistrationSessionStudentModel;

class HelperTest extends TestCase
{
    public function testSavesCandidateResponseAsErrorLog(): void
    {
        $saved = null;
        $mapper = $this->mapperSavingTo($saved);
        $student = $this->student();

        (new Helper($mapper))->saveCandidateResponse(
            $student,
            str_repeat('A', 96),
            [
                'candidateId' => null,
                'referenceId' => '456',
                'text' => 'Candidate rejected',
                'successful' => false,
            ]
        );

        $this->assertInstanceOf(Model::class, $saved);
        $this->assertSame(12, $saved->getRegistrationSession()?->getId());
        $this->assertSame(456, $saved->getRegistrationSessionStudent()?->getId());
        $this->assertSame(str_repeat('A', 96), $saved->getMsgId());
        $this->assertSame(Model::REQUEST_TYPE_REGISTER_CANDIDATES, $saved->getRequestType());
        $this->assertSame(Model::STATUS_ERROR, $saved->getStatus());
        $this->assertSame('Candidate rejected', $saved->getMessage());
    }

    public function testSavesSessionResponseAsSuccessLog(): void
    {
        $saved = null;
        $mapper = $this->mapperSavingTo($saved);

        (new Helper($mapper))->saveSessionResponse(
            $this->student(),
            str_repeat('B', 96),
            [
                'candidateId' => 'abc123',
                'month' => 'May',
                'year' => '2026',
                'text' => 'Success',
                'successful' => true,
            ]
        );

        $this->assertInstanceOf(Model::class, $saved);
        $this->assertSame(Model::REQUEST_TYPE_REGISTER_SESSIONS, $saved->getRequestType());
        $this->assertSame(Model::STATUS_SUCCESS, $saved->getStatus());
        $this->assertSame('Success', $saved->getMessage());
    }

    public function testSavesRequestError(): void
    {
        $saved = null;
        $mapper = $this->mapperSavingTo($saved);

        (new Helper($mapper))->saveRequestError(
            12,
            $this->student(),
            str_repeat('C', 96),
            Model::REQUEST_TYPE_REGISTER_CANDIDATES,
            new \RuntimeException('IBIS unavailable')
        );

        $this->assertInstanceOf(Model::class, $saved);
        $this->assertSame(12, $saved->getRegistrationSession()?->getId());
        $this->assertSame(456, $saved->getRegistrationSessionStudent()?->getId());
        $this->assertSame(Model::STATUS_ERROR, $saved->getStatus());
        $this->assertSame('IBIS unavailable', $saved->getMessage());
        $this->assertSame(Model::SOURCE_IBIS_API, $saved->getSource());
    }

    public function testSavesValidationErrorWithoutMsgId(): void
    {
        $saved = null;
        $mapper = $this->mapperSavingTo($saved);

        (new Helper($mapper))->saveValidationError(
            $this->student(),
            Model::REQUEST_TYPE_REGISTER_CANDIDATES,
            new \InvalidArgumentException('Selected candidate is missing required IBIS data: email.')
        );

        $this->assertInstanceOf(Model::class, $saved);
        $this->assertSame(12, $saved->getRegistrationSession()?->getId());
        $this->assertSame(456, $saved->getRegistrationSessionStudent()?->getId());
        $this->assertNull($saved->getMsgId());
        $this->assertSame(Model::REQUEST_TYPE_REGISTER_CANDIDATES, $saved->getRequestType());
        $this->assertSame(Model::STATUS_ERROR, $saved->getStatus());
        $this->assertSame(Model::SOURCE_LOCAL_VALIDATION, $saved->getSource());
        $this->assertSame('Selected candidate is missing required IBIS data: email.', $saved->getMessage());
    }

    public function testReturnsLatestErrorMessagesByStudentId(): void
    {
        $log = (new Model())
            ->setRegistrationSessionStudent((new RegistrationSessionStudentModel())->setId(456))
            ->setMessage('Latest error');

        $mapper = $this->createMock(Mapper::class);
        $mapper
            ->expects($this->once())
            ->method('findLatestErrorsForSession')
            ->with(12)
            ->willReturn(new GenericCollection(Model::class, [$log]));

        $this->assertSame(
            [456 => 'Latest error'],
            (new Helper($mapper))->getLatestErrorMessagesByStudentId(12)
        );
    }

    private function mapperSavingTo(?Model &$saved): Mapper
    {
        $mapper = $this->createMock(Mapper::class);
        $mapper
            ->method('createModel')
            ->willReturnCallback(static fn(): Model => new Model());
        $mapper
            ->expects($this->once())
            ->method('save')
            ->willReturnCallback(static function (Model $model) use (&$saved): Model {
                $saved = $model;
                return $model;
            });

        return $mapper;
    }

    private function student(): RegistrationSessionStudentModel
    {
        return (new RegistrationSessionStudentModel())
            ->setId(456)
            ->setRegistrationSession((new RegistrationSessionModel())->setId(12));
    }
}
