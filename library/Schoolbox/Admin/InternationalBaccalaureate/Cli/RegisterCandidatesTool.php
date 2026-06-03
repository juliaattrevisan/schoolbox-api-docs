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
use Schoolbox\Application\CliKernelAbstract;
use Schoolbox\Application\CliTool;
use Schoolbox\IB\Registrations\Sessions\Student\Mapper as RegistrationSessionStudentMapper;
use Schoolbox\IB\Registrations\Sessions\Student\Model as RegistrationSessionStudentModel;
use Schoolbox\MapperFactory;
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
        /** @var \External_UserInterface $externalUser */
        $externalUser = $this->getDi()->get('externalUser');

        $students = $studentMapper->findPendingCandidateRegistrations($batchSize);
        $this->getLogger()->info(sprintf('Found %d pending candidate(s).', count($students)));

        if (count($students) === 0) {
            return CliKernelAbstract::EXIT_STATUS_OK;
        }

        $candidateBuilder = new RegisterCandidatesCandidateBuilder();
        $candidates = [];
        $studentsByReferenceCode = [];
        $failed = 0;

        foreach ($students as $student) {
            if (!$student instanceof RegistrationSessionStudentModel) {
                continue;
            }

            try {
                $candidate = $candidateBuilder->build(
                    $student,
                    $this->findExternalStudent($externalUser, $student)
                );
                $candidates[] = $candidate;
                $studentsByReferenceCode[$candidate['reference_code']] = $student;
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

        if ($candidates === []) {
            $this->getLogger()->notice('No valid pending candidates to submit.');
            return $failed > 0 ? CliKernelAbstract::EXIT_STATUS_KERNEL_ERROR : CliKernelAbstract::EXIT_STATUS_OK;
        }

        if (!$this->perform()) {
            $this->getLogger()->notice(sprintf('Would submit %d candidate(s) to IBIS.', count($candidates)));
            return CliKernelAbstract::EXIT_STATUS_OK;
        }

        /** @var ClientFactory $clientFactory */
        $clientFactory = $this->getDi()->get('ib.clientFactory');

        try {
            $request = $clientFactory->buildRegisterCandidatesRequest($candidates);
            $response = $clientFactory->build()->registerCandidates($request);
        } catch (Throwable $e) {
            $this->getLogger()->error('IBIS candidate registration submission failed.', ['exception' => $e]);
            return CliKernelAbstract::EXIT_STATUS_KERNEL_ERROR;
        }

        if (!$response->isSuccess()) {
            $this->getLogger()->warning(sprintf(
                'IBIS rejected the candidate registration batch: %s',
                $response->getErrorSummary() ?? 'unknown fault'
            ));

            foreach ($studentsByReferenceCode as $student) {
                $student->setStatus(RegistrationSessionStudentModel::STATUS_FAILED);
                $studentMapper->save($student);
            }

            return CliKernelAbstract::EXIT_STATUS_KERNEL_ERROR;
        }

        foreach ($studentsByReferenceCode as $student) {
            $student->setStatus(RegistrationSessionStudentModel::STATUS_REGISTERED);
            $studentMapper->save($student);
        }

        $this->getLogger()->notice(sprintf(
            'Submitted %d candidate(s) to IBIS.',
            count($studentsByReferenceCode)
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
