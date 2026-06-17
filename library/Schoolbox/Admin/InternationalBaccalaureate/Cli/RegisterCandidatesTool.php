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
use Schoolbox\Admin\InternationalBaccalaureate\IClient;
use Schoolbox\Admin\InternationalBaccalaureate\RegisterCandidatesCandidateBuilder;
use Schoolbox\Admin\InternationalBaccalaureate\RegisterSessionsSessionBuilder;
use Schoolbox\Application\CliKernelAbstract;
use Schoolbox\Application\CliTool;
use Schoolbox\IB\Registrations\Sessions\Student\Mapper as RegistrationSessionStudentMapper;
use Schoolbox\IB\Registrations\Sessions\Student\Model as RegistrationSessionStudentModel;
use Schoolbox\MapperFactory;
use Schoolbox\User;
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
        $schoolCode = $clientFactory->getSchoolCode();
        $failed = 0;

        /* Initialise the client
         */
        try {
            $client = $clientFactory->build();
        } catch (Throwable $e) {
            $this->getLogger()->error('IBIS candidate registration client setup failed.', ['exception' => $e]);
            return CliKernelAbstract::EXIT_STATUS_KERNEL_ERROR;
        }

        foreach ($students as $student) {
            if (!$student instanceof RegistrationSessionStudentModel) {
                continue;
            }
            $user = $student->getUser();
            try {
                if ($user === null) {
                    throw new InvalidArgumentException('Selected candidate is missing required IBIS data.');
                }

                $candidateId = trim((string) ($user->getIbCandidateNumber() ?? ''));

                if ('' === $candidateId) {
                    try {
                        $candidate = $candidateBuilder->build(
                            $student,
                            $this->findExternalStudent($externalUser, $student)
                        );
                    } catch (InvalidArgumentException $e) {
                        $failed++;

                        $this->getLogger()->warning(
                            sprintf(
                                'Candidate #%s is missing required IBIS data: %s',
                                $student->getId() ?? 'unknown',
                                $e->getMessage()
                            )
                        );

                        if ($this->perform()) {
                            $this->markCandidateRegistrationFailed($student, $studentMapper);
                        }
                        continue;
                    }

                    if (!$this->perform()) {
                        $this->getLogger()->notice(
                            sprintf(
                                'Planning mode: would have submitted candidate #%s %s to IBIS for candidate number registration.',
                                $student->getId(),
                                $user->getFullName(),
                            )
                        );
                        continue;
                    }

                    if (
                        !$this->registerCandidateNumber(
                            $student,
                            $user,
                            $candidate,
                            $clientFactory,
                            $client,
                            $studentMapper,
                            $userMapper
                        )
                    ) {
                        $failed++;
                        continue;
                    }
                }
            } catch (Throwable $e) {
                $failed++;
                $this->getLogger()->error(
                    sprintf(
                        'IBIS candidate registration failed for candidate #%s %s.',
                        $student->getId() ?? 'unknown',
                        $student->getUser()?->getFullName() ?? 'unknown',
                    ),
                    ['exception' => $e]
                );
            }

            if (!$this->perform()) {
                $this->getLogger()->notice(
                    sprintf(
                        'Planning mode: would have submitted candidate #%s %s to IBIS for Session registration.',
                        $student->getId() ?? 'unknown',
                        $student->getUser()?->getFullName() ?? 'unknown',
                    )
                );
                continue;
            }

            $this->getLogger()->info(
                sprintf(
                    'Submitting candidate #%s %s to IBIS for %s %s Session registration.',
                    $student->getId() ?? 'unknown',
                    $student->getUser()?->getFullName() ?? 'unknown',
                    $student->getRegistrationSession()->getExamSession(),
                    $student->getRegistrationSession()->getAcademicYear(),
                )
            );

            try {
                $session = $sessionBuilder->build($student, $user->getIbCandidateNumber(), $schoolCode);
                $sessionRequest = $clientFactory->buildRegisterSessionsRequest([$session]);
                $sessionResponse = $client->registerSessions($sessionRequest);
            } catch (Throwable $e) {
                $failed++;
                $this->getLogger()->error(
                    sprintf(
                        'IBIS session registration submission failed for candidate #%s %s.',
                        $student->getId() ?? 'unknown',
                        $student->getUser()?->getFullName() ?? 'unknown',
                    ),
                    ['exception' => $e]
                );
                $this->markCandidateRegistrationFailed($student, $studentMapper);
                continue;
            }

            if (!$sessionResponse->isSuccess()) {
                $this->getLogger()->warning(
                    sprintf(
                        'IBIS did not accept candidate #%s %s\'s session registration: %s',
                        $student->getId() ?? 'unknown',
                        $student->getUser()?->getFullName() ?? 'unknown',
                        $sessionResponse->getErrorSummary() ?? 'unknown fault'
                    )
                );
            }

            $sessionResponses = $sessionResponse->getSessionResponses();
            foreach ($sessionResponses as $sessionResponseItem) {
                $success = $sessionResponseItem['successful'];
                $student->setStatus(
                    $success
                        ? RegistrationSessionStudentModel::STATUS_REGISTERED
                        : RegistrationSessionStudentModel::STATUS_FAILED
                );
                $studentMapper->save($student);

                if (!$success) {
                    $failed++;
                    $this->getLogger()->warning(
                        sprintf(
                            'IBIS session registration failed for candidate #%s %s in %s %s IB session.',
                            $student->getId() ?? 'unknown',
                            $student->getUser()?->getFullName() ?? 'unknown',
                            $student->getRegistrationSession()->getExamSession(),
                            $student->getRegistrationSession()->getAcademicYear(),
                        )
                    );
                } else {
                    $this->getLogger()->notice(
                        sprintf(
                            'Successfully registered candidate #%s %s for %s %s IB session in IBIS.',
                            $student->getId() ?? 'unknown',
                            $student->getUser()?->getFullName() ?? 'unknown',
                            $student->getRegistrationSession()->getExamSession(),
                            $student->getRegistrationSession()->getAcademicYear(),
                        )
                    );
                }
            }
        }

        return $failed > 0 ? CliKernelAbstract::EXIT_STATUS_KERNEL_ERROR : CliKernelAbstract::EXIT_STATUS_OK;
    }

    /**
     * @param array<string, string|null> $candidate
     *
     * @throws \Schoolbox\Mapper\Exception
     */
    private function registerCandidateNumber(
        RegistrationSessionStudentModel $student,
        User $user,
        array $candidate,
        ClientFactory $clientFactory,
        IClient $client,
        RegistrationSessionStudentMapper $studentMapper,
        UserMapper $userMapper
    ): bool {
        $this->getLogger()->info(
            sprintf(
                'Submitting candidate #%s %s to IBIS for candidate number registration.',
                $student->getId(),
                $user->getFullName(),
            )
        );

        try {
            $request  = $clientFactory->buildRegisterCandidatesRequest([$candidate]);
            $response = $client->registerCandidates($request);
        } catch (Throwable $e) {
            $this->getLogger()->error(
                sprintf(
                    'IBIS candidate registration submission failed for candidate #%s.',
                    $student->getId() ?? 'unknown'
                ),
                ['exception' => $e]
            );
            $this->markCandidateRegistrationFailed($student, $studentMapper);

            return false;
        }

        if (!$response->isSuccess()) {
            $this->getLogger()->warning(
                sprintf(
                    'IBIS did not accept candidate #%s %s\'s candidate number registration: %s',
                    $student->getId() ?? 'unknown',
                    $user->getFullName() ?? 'unknown',
                    $response->getErrorSummary() ?? 'unknown fault'
                )
            );
        }

        $successful = true;

        foreach ($response->getCandidateResponses() as $candidateResponse) {
            $candidateId = $candidateResponse['candidateId'];
            $candidateRegistrationSuccessful = $candidateResponse['successful']
                && $candidateId !== null
                && $candidateId !== '';

            $student->setStatus(
                $candidateRegistrationSuccessful
                    ? RegistrationSessionStudentModel::STATUS_REGISTERED
                    : RegistrationSessionStudentModel::STATUS_FAILED
            );
            $studentMapper->save($student);

            if (!$candidateRegistrationSuccessful) {
                $successful = false;
                continue;
            }

            try {
                $user->setIbCandidateNumber($candidateId);
                $userMapper->save($user);
            } catch (Throwable $e) {
                $successful = false;
                $this->getLogger()->error(
                    sprintf(
                        'Saving candidate number for candidate #%s failed. Candidate id: %s',
                        $student->getId() ?? 'unknown',
                        $candidateId
                    ),
                    ['exception' => $e]
                );
                $this->markCandidateRegistrationFailed($student, $studentMapper);
            }
        }

        return $successful;
    }

    /**
     * @throws \Schoolbox\Mapper\Exception
     */
    private function markCandidateRegistrationFailed(
        RegistrationSessionStudentModel $student,
        RegistrationSessionStudentMapper $studentMapper
    ): void {
        $student->setStatus(RegistrationSessionStudentModel::STATUS_FAILED);
        $studentMapper->save($student);
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
