<?php

/**
 * IB registration API response log helper
 *
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\IB\Registrations\Api\ResponseLog;

use Schoolbox\IB\Registrations\Sessions\Model as RegistrationSessionModel;
use Schoolbox\IB\Registrations\Sessions\Student\Model as RegistrationSessionStudentModel;
use Throwable;

/**
 * Persists and reads IBIS registration API response logs.
 */
class Helper
{
    public function __construct(private Mapper $mapper)
    {
    }

    /**
     * Save one IBIS register-candidates response for a registration session student.
     *
     * @param array{
     *     candidateId?: string|null,
     *     referenceId?: string|null,
     *     text?: string|null,
     *     successful: bool
     * } $candidateResponse
     */
    public function saveCandidateResponse(
        RegistrationSessionStudentModel $student,
        string $msgId,
        array $candidateResponse
    ): void {
        $this->saveStudentResponse(
            $student,
            $msgId,
            Model::REQUEST_TYPE_REGISTER_CANDIDATES,
            (bool)$candidateResponse['successful'],
            $candidateResponse['text'] ?? null
        );
    }

    /**
     * Save one IBIS register-sessions response for a registration session student.
     *
     * @param array{
     *     candidateId?: string|null,
     *     month?: string|null,
     *     year?: string|null,
     *     text?: string|null,
     *     successful: bool
     * } $sessionResponse
     */
    public function saveSessionResponse(
        RegistrationSessionStudentModel $student,
        string $msgId,
        array $sessionResponse
    ): void {
        $this->saveStudentResponse(
            $student,
            $msgId,
            Model::REQUEST_TYPE_REGISTER_SESSIONS,
            (bool)$sessionResponse['successful'],
            $sessionResponse['text'] ?? null
        );
    }

    /**
     * Save a failed IBIS request when no structured response row was returned.
     *
     * @param RegistrationSessionStudentModel|null $student Candidate row related to the failure, if known.
     * @param Throwable|string $error Exception or raw error message to store.
     */
    public function saveRequestError(
        int $registrationSessionId,
        ?RegistrationSessionStudentModel $student,
        string $msgId,
        string $requestType,
        Throwable|string $error
    ): void {
        $message = $error instanceof Throwable ? $error->getMessage() : $error;
        $registrationSession = $student?->getRegistrationSession()
            ?? (new RegistrationSessionModel())->setId($registrationSessionId);

        $log = $this->mapper->createModel()
            ->setRegistrationSession($registrationSession)
            ->setRegistrationSessionStudent($student)
            ->setMsgId($msgId)
            ->setRequestType($requestType)
            ->setStatus(Model::STATUS_ERROR)
            ->setMessage($message !== '' ? $message : null);

        $this->mapper->save($log);
    }

    /**
     * Return the latest non-empty error message for each student in a registration session.
     *
     * @return array<int, string>
     */
    public function getLatestErrorMessagesByStudentId(int $registrationSessionId): array
    {
        $messages = [];

        foreach ($this->mapper->findLatestErrorsForSession($registrationSessionId) as $log) {
            if (!$log instanceof Model) {
                continue;
            }

            $studentId = $log->getRegistrationSessionStudent()?->getId();
            $message = trim((string)($log->getMessage() ?? ''));

            if ($studentId !== null && $message !== '') {
                $messages[(int)$studentId] = $message;
            }
        }

        return $messages;
    }

    /**
     * Build and persist a response log row shared by candidate and session responses.
     *
     * @throws \InvalidArgumentException When the student or its registration session has not been persisted.
     */
    private function saveStudentResponse(
        RegistrationSessionStudentModel $student,
        string $msgId,
        string $requestType,
        bool $successful,
        ?string $message
    ): void {
        $registrationSession = $student->getRegistrationSession();

        if ($registrationSession === null || $registrationSession->getId() === null || $student->getId() === null) {
            throw new \InvalidArgumentException('Registration session and student IDs are required to log IBIS responses.');
        }

        $log = $this->mapper->createModel()
            ->setRegistrationSession($registrationSession)
            ->setRegistrationSessionStudent($student)
            ->setMsgId($msgId)
            ->setRequestType($requestType)
            ->setStatus($successful ? Model::STATUS_SUCCESS : Model::STATUS_ERROR)
            ->setMessage($message !== null && trim($message) !== '' ? trim($message) : null);

        $this->mapper->save($log);
    }
}
