<?php

/**
 * IBIS register candidates command line tool
 *
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Admin\InternationalBaccalaureate\Cli;

use Exception;
use InvalidArgumentException;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Schoolbox\Admin\InternationalBaccalaureate\ClientFactory;
use Schoolbox\Admin\InternationalBaccalaureate\RegisterCandidatesCandidateBuilder;
use Schoolbox\Admin\InternationalBaccalaureate\RegisterSessionsSessionBuilder;
use Schoolbox\Application\CliKernelAbstract;
use Schoolbox\Application\CliTool;
use Schoolbox\IB\Registrations\Sessions\Student\Mapper as RegistrationSessionStudentMapper;
use Schoolbox\IB\Registrations\Sessions\Student\Model as RegistrationSessionStudentModel;
use Schoolbox\MapperFactory;
use Schoolbox\UserMapper;
use Throwable;

class RegisterCandidatesTool extends CliTool\App
{
    public const string LOCK_NAME = 'ib_register_candidates';

    private const string OPT_LONG_HELP = 'help';
    private const string OPT_LONG_BATCH_SIZE = 'batch-size:';
    private const string OPT_LONG_TIMEOUT = 'timeout:';

    private const int DEFAULT_BATCH_SIZE = 50;
    private const int DEFAULT_TIMEOUT = 180;

    public function getLongOpts(): array
    {
        return [
            self::OPT_LONG_HELP,
            self::OPT_LONG_BATCH_SIZE,
            self::OPT_LONG_TIMEOUT,
        ];
    }

    public function getRequiredProcessUser(): ?string
    {
        return self::WEB_PROCESS_USER;
    }

    public function run(): void
    {
        if ($this->getOpt(self::OPT_LONG_HELP)) {
            $this->displayHelp();
            exit(CliKernelAbstract::EXIT_STATUS_OK);
        }

        $batchSize = $this->getOptAsInt(self::OPT_LONG_BATCH_SIZE, self::DEFAULT_BATCH_SIZE);
        $timeout = $this->getOptAsInt(self::OPT_LONG_TIMEOUT, self::DEFAULT_TIMEOUT);

        if ($batchSize === null || $batchSize <= 0) {
            throw new InvalidArgumentException('Batch size must be a positive integer.');
        }
        if ($timeout === null || $timeout <= 0) {
            throw new InvalidArgumentException('Timeout must be a positive integer.');
        }

        $this->perform()
            ? $this->getLogger()->notice('Running in perform mode: pending IB candidates will be submitted.')
            : $this->getLogger()->notice('Running in planning mode: no candidates will be submitted or saved.');
        $this->getLogger()->info(sprintf('Batch size: %d', $batchSize));

        try {
            $lock = $this->acquireLock(self::LOCK_NAME, $timeout);
        } catch (\RuntimeException) {
            exit(CliKernelAbstract::EXIT_STATUS_KERNEL_ERROR);
        }

        $this->setMaxExecutionTime($timeout);

        try {
            $exitStatus = $this->processPendingCandidates($batchSize);
        } finally {
            $lock->release();
        }

        exit($exitStatus);
    }

    /**
     * @throws NotFoundExceptionInterface
     * @throws ContainerExceptionInterface
     * @throws \Schoolbox\Mapper\Exception
     */
    private function processPendingCandidates(int $batchSize): int
    {
        /** @var MapperFactory $mapperFactory */
        $mapperFactory = $this->getDi()->get('mapperFactory');
        /** @var RegistrationSessionStudentMapper $studentMapper */
        $studentMapper = $mapperFactory->get(RegistrationSessionStudentMapper::class);
        /** @var UserMapper $userMapper */
        $userMapper = $mapperFactory->get(UserMapper::class);
        /** @var \External_UserInterface $externalUser */
        $externalUser = $this->getDi()->get('externalUser');

        $students = $studentMapper->findPendingCandidateRegistrations($batchSize);
        $this->getLogger()->info(sprintf('Found %d pending candidate(s).', count($students)));

        if (count($students) === 0) {
            return CliKernelAbstract::EXIT_STATUS_OK;
        }

        /** @var ClientFactory $clientFactory */
        $clientFactory = $this->getDi()->get('ib.clientFactory');

        $candidateBuilder = new RegisterCandidatesCandidateBuilder();
        $sessionBuilder = new RegisterSessionsSessionBuilder();
        $candidates = [];
        $sessions = [];
        $studentsByCandidateId = [];
        $studentsByUserId = [];
        $schoolCode = $clientFactory->getSchoolCode();
        $failed = 0;

        foreach ($students as $student) {
            if (!$student instanceof RegistrationSessionStudentModel) {
                continue;
            }

            try {
                $user = $student->getUser();
                if ($user === null) {
                    throw new InvalidArgumentException('Selected candidate is missing required IBIS data.');
                }

                $candidateId = trim((string) ($user->getIbCandidateNumber() ?? ''));
                if ($candidateId !== '') {
                    $session = $sessionBuilder->build($student, $candidateId, $schoolCode);
                    $sessions[] = $session;
                    $studentsByCandidateId[$candidateId] = $student;
                    continue;
                }

                $candidate = $candidateBuilder->build(
                    $student,
                    $this->findExternalStudent($externalUser, $student)
                );
                $candidates[] = $candidate;
                $studentsByUserId[(string) $user->getId()] = $student;
            } catch (InvalidArgumentException $e) {
                $failed++;
                $this->getLogger()->warning(sprintf(
                    'Candidate #%s is missing required IBIS data: %s',
                    $student->getId() ?? 'unknown',
                    $e->getMessage()
                ));

                if ($this->perform()) {
                    $student->setStatus(RegistrationSessionStudentModel::STATUS_FAILED);
                    $studentMapper->save($student);
                }
            }
        }

        if ($candidates === [] && $sessions === []) {
            $this->getLogger()->notice('No valid pending candidates or sessions to submit.');
            return $failed > 0 ? CliKernelAbstract::EXIT_STATUS_KERNEL_ERROR : CliKernelAbstract::EXIT_STATUS_OK;
        }

        if (!$this->perform()) {
            $this->getLogger()->notice(sprintf(
                'Would submit %d candidate(s) and %d candidate session registration(s) to IBIS.',
                count($candidates),
                count($sessions)
            ));
            return CliKernelAbstract::EXIT_STATUS_OK;
        }

        if ($candidates !== []) {
            try {
                $request = $clientFactory->buildRegisterCandidatesRequest($candidates);
                $response = $clientFactory->build()->registerCandidates($request);
            } catch (Throwable $e) {
                $this->getLogger()->error('IBIS candidate registration submission failed.', ['exception' => $e]);
                return CliKernelAbstract::EXIT_STATUS_KERNEL_ERROR;
            }

            if (!$response->isSuccess()) {
                $this->getLogger()->warning(sprintf(
                    'IBIS did not accept all candidate registrations: %s',
                    $response->getErrorSummary() ?? 'unknown fault'
                ));
            }

            $candidateResponses = $response->getCandidateResponses();

            if ($candidateResponses === []) {
                foreach ($studentsByUserId as $student) {
                    $student->setStatus(RegistrationSessionStudentModel::STATUS_FAILED);
                    $studentMapper->save($student);
                }

                return CliKernelAbstract::EXIT_STATUS_KERNEL_ERROR;
            }

            foreach ($candidateResponses as $candidateResponse) {
                $referenceId = $candidateResponse['referenceId'];
                $candidateId = $candidateResponse['candidateId'];
                $userId = $this->normaliseReferenceIdToUserId($referenceId);
                if ($userId === null || !isset($studentsByUserId[$userId])) {
                    $failed++;
                    continue;
                }

                $student = $studentsByUserId[$userId];

                if (!$candidateResponse['successful'] || $candidateId === null || $candidateId === '') {
                    $failed++;
                    $student->setStatus(RegistrationSessionStudentModel::STATUS_FAILED);
                    $studentMapper->save($student);
                    continue;
                }

                try {
                    $user = $student->getUser();
                    if ($user !== null) {
                        $user->setIbCandidateNumber($candidateId);
                        $userMapper->save($user);
                    }
                    $session = $sessionBuilder->build($student, $candidateId, $schoolCode);
                    $sessions[] = $session;
                    $studentsByCandidateId[$candidateId] = $student;
                } catch (InvalidArgumentException $e) {
                    $failed++;
                    $this->getLogger()->warning(sprintf(
                        'Candidate #%s is missing required IBIS session data: %s',
                        $student->getId() ?? 'unknown',
                        $e->getMessage()
                    ));
                    $student->setStatus(RegistrationSessionStudentModel::STATUS_FAILED);
                    $studentMapper->save($student);
                }
            }
        }

        if ($sessions === []) {
            $this->getLogger()->notice('No valid registered candidates to submit to IBIS sessions.');
            return CliKernelAbstract::EXIT_STATUS_KERNEL_ERROR;
        }

        try {
            $sessionRequest = $clientFactory->buildRegisterSessionsRequest($sessions);
            $sessionResponse = $clientFactory->build()->registerSessions($sessionRequest);
        } catch (Throwable $e) {
            $this->getLogger()->error('IBIS session registration submission failed.', ['exception' => $e]);

            foreach ($studentsByCandidateId as $student) {
                $student->setStatus(RegistrationSessionStudentModel::STATUS_FAILED);
                $studentMapper->save($student);
            }

            return CliKernelAbstract::EXIT_STATUS_KERNEL_ERROR;
        }

        if (!$sessionResponse->isSuccess()) {
            $this->getLogger()->warning(sprintf(
                'IBIS did not accept all session registrations: %s',
                $sessionResponse->getErrorSummary() ?? 'unknown fault'
            ));
        }

        $handledCandidateIds = [];
        $sessionResponses = $sessionResponse->getSessionResponses();

        if ($sessionResponses === []) {
            foreach ($studentsByCandidateId as $student) {
                $failed++;
                $student->setStatus(RegistrationSessionStudentModel::STATUS_FAILED);
                $studentMapper->save($student);
            }

            return CliKernelAbstract::EXIT_STATUS_KERNEL_ERROR;
        }

        foreach ($sessionResponses as $sessionResponseItem) {
            $candidateId = $sessionResponseItem['candidateId'];
            if ($candidateId === null || !isset($studentsByCandidateId[$candidateId])) {
                $failed++;
                continue;
            }

            $handledCandidateIds[$candidateId] = $candidateId;
            $student = $studentsByCandidateId[$candidateId];
            $student->setStatus(
                $sessionResponseItem['successful']
                    ? RegistrationSessionStudentModel::STATUS_REGISTERED
                    : RegistrationSessionStudentModel::STATUS_FAILED
            );
            $studentMapper->save($student);

            if (!$sessionResponseItem['successful']) {
                $failed++;
            }
        }

        foreach ($studentsByCandidateId as $candidateId => $student) {
            if (isset($handledCandidateIds[$candidateId])) {
                continue;
            }

            $failed++;
            $student->setStatus(RegistrationSessionStudentModel::STATUS_FAILED);
            $studentMapper->save($student);
        }

        $this->getLogger()->notice(sprintf(
            'Submitted %d candidate session registration(s) to IBIS.',
            count($studentsByCandidateId)
        ));

        return $failed > 0 ? CliKernelAbstract::EXIT_STATUS_KERNEL_ERROR : CliKernelAbstract::EXIT_STATUS_OK;
    }

    /**
     * @return array<string, mixed>
     */
    private function findExternalStudent(
        \External_UserInterface $externalUser,
        RegistrationSessionStudentModel $student
    ): array {
        $externalId = trim((string) ($student->getUser()?->getExternalId() ?? ''));

        if ($externalId === '') {
            return [];
        }

        return $externalUser->getStudent($externalId);
    }

    private function normaliseReferenceIdToUserId(?string $referenceId): ?string
    {
        if ($referenceId === null) {
            return null;
        }

        $userId = ltrim($referenceId, '0');

        return $userId === '' ? '0' : $userId;
    }

    private function displayHelp(): void
    {
        $batchSize = self::DEFAULT_BATCH_SIZE;
        $timeout = self::DEFAULT_TIMEOUT;

        echo <<<HELP
Submit pending IB candidates to IBIS.

Usage: register-candidates.php -v[vv] [--perform] [--batch-size={$batchSize}] [--timeout={$timeout}]

    --perform:           Submit candidates to IBIS and update registration statuses.
                         Without this, the tool runs in planning mode.
    --batch-size:        Maximum number of pending candidates to process per run.
                         Defaults to {$batchSize}.
    --timeout:           Lock and max execution timeout, in seconds.
                         Defaults to {$timeout}.

HELP;
    }
}
