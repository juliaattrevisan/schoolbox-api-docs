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
        $this->getLogger()->info(sprintf('Found %d pending candidate(s) in batch of %d.', count($students), $batchSize));

        if (count($students) === 0) {
            return CliKernelAbstract::EXIT_STATUS_OK;
        }

        /** @var ClientFactory $clientFactory */
        $clientFactory = $this->getDi()->get('ib.clientFactory');

        $candidateBuilder = new RegisterCandidatesCandidateBuilder();
        $sessionBuilder = new RegisterSessionsSessionBuilder();
        $candidates = [];
        $sessionsByCandidateId = [];
        $candidatesByUserId = [];
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
                    $sessionsByCandidateId[$candidateId] = [
                        'session' => $session,
                        'student' => $student,
                    ];
                    continue;
                }

                $candidate = $candidateBuilder->build(
                    $student,
                    $this->findExternalStudent($externalUser, $student)
                );
                $candidates[] = $candidate;
                $candidatesByUserId[(string) $user->getId()] = [
                    'candidate' => $candidate,
                    'student' => $student,
                ];
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

        if ($candidates === [] && $sessionsByCandidateId === []) {
            $this->getLogger()->notice('No valid pending candidates or sessions to submit.');
            return $failed > 0 ? CliKernelAbstract::EXIT_STATUS_KERNEL_ERROR : CliKernelAbstract::EXIT_STATUS_OK;
        }

        if (!$this->perform()) {
            $this->getLogger()->notice(sprintf(
                'Would submit %d candidate(s) and %d candidate session registration(s) to IBIS.',
                count($candidates),
                count($sessionsByCandidateId)
            ));
            return CliKernelAbstract::EXIT_STATUS_OK;
        }

        if ($candidatesByUserId !== []) {
            try {
                $client = $clientFactory->build();
            } catch (Throwable $e) {
                $this->getLogger()->error('IBIS candidate registration client setup failed.', ['exception' => $e]);
                return CliKernelAbstract::EXIT_STATUS_KERNEL_ERROR;
            }

            foreach ($candidatesByUserId as $expectedUserId => $candidateDetails) {
                /** @var array<string, string|null> $candidate */
                $candidate = $candidateDetails['candidate'];
                /** @var RegistrationSessionStudentModel $student */
                $student = $candidateDetails['student'];

                try {
                    $this->getLogger()->debug(sprintf(
                        'Submitting candidate #%s registration to IBIS.',
                        $student->getId() ?? 'unknown',
                    ));
                    $request = $clientFactory->buildRegisterCandidatesRequest([$candidate]);
                    $response = $client->registerCandidates($request);
                } catch (Throwable $e) {
                    $failed++;
                    $this->getLogger()->error(sprintf(
                        'IBIS candidate registration submission failed for candidate #%s.',
                        $student->getId() ?? 'unknown'
                    ), ['exception' => $e]);
                    $student->setStatus(RegistrationSessionStudentModel::STATUS_FAILED);
                    $studentMapper->save($student);
                    continue;
                }

                if (!$response->isSuccess()) {
                    $this->getLogger()->warning(sprintf(
                        'IBIS did not accept candidate #%s registration: %s',
                        $student->getId() ?? 'unknown',
                        $response->getErrorSummary() ?? 'unknown fault'
                    ));
                }

                $candidateResponses = $response->getCandidateResponses();

                if ($candidateResponses === []) {
                    $failed++;
                    $student->setStatus(RegistrationSessionStudentModel::STATUS_FAILED);
                    $studentMapper->save($student);
                    continue;
                }

                $candidateHandled = false;

                foreach ($candidateResponses as $candidateResponse) {
                    $referenceId = $candidateResponse['referenceId'];
                    $candidateId = $candidateResponse['candidateId'];
                    $userId = $this->normaliseReferenceIdToUserId($referenceId);
                    if ($userId !== $expectedUserId) {
                        $failed++;
                        $this->getLogger()->warning(sprintf(
                            'IBIS candidate registration response for candidate #%s had unexpected reference ID: %s',
                            $student->getId() ?? 'unknown',
                            $referenceId ?? 'none'
                        ));
                        continue;
                    }

                    $candidateHandled = true;

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
                        $sessionsByCandidateId[$candidateId] = [
                            'session' => $session,
                            'student' => $student,
                        ];
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

                if (!$candidateHandled) {
                    $failed++;
                    $student->setStatus(RegistrationSessionStudentModel::STATUS_FAILED);
                    $studentMapper->save($student);
                }
            }
        }

        if ($sessionsByCandidateId === []) {
            $this->getLogger()->notice('No valid registered candidates to submit to IBIS sessions.');
            return CliKernelAbstract::EXIT_STATUS_KERNEL_ERROR;
        }

        try {
            $client = $clientFactory->build();
        } catch (Throwable $e) {
            $this->getLogger()->error('IBIS session registration client setup failed.', ['exception' => $e]);
            return CliKernelAbstract::EXIT_STATUS_KERNEL_ERROR;
        }

        foreach ($sessionsByCandidateId as $expectedCandidateId => $sessionDetails) {
            /** @var array<string, mixed> $session */
            $session = $sessionDetails['session'];
            /** @var RegistrationSessionStudentModel $student */
            $student = $sessionDetails['student'];

            try {
                $this->getLogger()->debug(sprintf(
                    'Submitting candidate #%s session registration to IBIS.',
                    $student->getId() ?? 'unknown',
                ));
                $sessionRequest = $clientFactory->buildRegisterSessionsRequest([$session]);
                $sessionResponse = $client->registerSessions($sessionRequest);
            } catch (Throwable $e) {
                $failed++;
                $this->getLogger()->error(sprintf(
                    'IBIS session registration submission failed for candidate #%s.',
                    $student->getId() ?? 'unknown'
                ), ['exception' => $e]);
                $student->setStatus(RegistrationSessionStudentModel::STATUS_FAILED);
                $studentMapper->save($student);
                continue;
            }

            if (!$sessionResponse->isSuccess()) {
                $this->getLogger()->warning(sprintf(
                    'IBIS did not accept candidate #%s session registration: %s',
                    $student->getId() ?? 'unknown',
                    $sessionResponse->getErrorSummary() ?? 'unknown fault'
                ));
            }

            $sessionResponses = $sessionResponse->getSessionResponses();

            if ($sessionResponses === []) {
                $failed++;
                $student->setStatus(RegistrationSessionStudentModel::STATUS_FAILED);
                $studentMapper->save($student);
                continue;
            }

            $sessionHandled = false;

            foreach ($sessionResponses as $sessionResponseItem) {
                $candidateId = $sessionResponseItem['candidateId'];
                if ($candidateId !== $expectedCandidateId) {
                    $failed++;
                    $this->getLogger()->warning(sprintf(
                        'IBIS session registration response for candidate #%s had unexpected candidate ID: %s',
                        $student->getId() ?? 'unknown',
                        $candidateId ?? 'none'
                    ));
                    continue;
                }

                $sessionHandled = true;
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

            if (!$sessionHandled) {
                $failed++;
                $student->setStatus(RegistrationSessionStudentModel::STATUS_FAILED);
                $studentMapper->save($student);
            }
        }

        $this->getLogger()->notice(sprintf(
            'Submitted %d candidate session registration(s) to IBIS.',
            count($sessionsByCandidateId)
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
