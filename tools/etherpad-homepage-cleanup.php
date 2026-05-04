#!/usr/bin/env php
<?php

/**
 * Operator-facing tool for homepage-scoped Etherpad cleanup.
 *
 * This tool is intentionally independent of the DataRetention system.
 * It queries the local etherpad_pad table for a homepage, deletes the pads
 * through the Etherpad API, and hard-deletes the local metadata rows.
 *
 * Usage:
 *   php tools/etherpad-homepage-cleanup.php --homepage-id=123 [--perform] [--verbose]
 */

declare(strict_types=1);

use EtherpadLite\Client as EtherpadClient;
use Psr\Log\LoggerInterface;
use Schoolbox\Application\CliKernelAbstract;
use Schoolbox\Application\CliTool;
use Schoolbox\Config\ConfigMapper;

require __DIR__ . '/../conf/core/require.php';

$app = new class extends CliTool\App
{
    private const OPT_LONG_HELP = 'help';
    private const OPT_LONG_HOMEPAGE_ID = 'homepage-id:';
    private const OPT_LONG_VERBOSE = 'verbose';

    /**
     * Return the long options that this CLI tool accepts.
     *
     * @return string[]
     */
    public function getLongOpts(): array
    {
        return [
            self::OPT_LONG_HELP,
            self::OPT_LONG_HOMEPAGE_ID,
            self::OPT_LONG_VERBOSE,
        ];
    }

    /**
     * Perform the homepage-scoped Etherpad cleanup.
     */
    public function run(): void
    {
        $logger = $this->getLogger();

        if ($this->getOpt(self::OPT_LONG_HELP)) {
            echo
            <<<HELP
Usage: php tools/etherpad-homepage-cleanup.php --homepage-id=123 [--perform] [--verbose]

Options:
  --homepage-id=ID         Required homepage id to target for Etherpad cleanup
  --perform                Persist changes instead of dry-run
  --verbose                Print additional progress output
  --help                   Show this help message

HELP;

            exit(0);
        }

        try {
            $homepageId = $this->getOptAsInt(self::OPT_LONG_HOMEPAGE_ID);
        } catch (\UnexpectedValueException $e) {
            $logger->error($e->getMessage());
            $logger->error('Invalid --homepage-id value; it must be a whole number.');
            exit(1);
        }

        if ($homepageId === null || $homepageId <= 0) {
            $logger->error('Missing required --homepage-id');
            exit(1);
        }

        $dryRun = !$this->perform();

        $logger->info(sprintf('Starting Etherpad homepage cleanup for homepage %d', $homepageId));
        $logger->notice(sprintf('Mode: %s', $dryRun ? 'dry-run' : 'perform'));

        $etherpadClient = $this->createEtherpadClient($logger);
        if (!$dryRun && $etherpadClient === null) {
            $logger->error('Etherpad API is not configured; cannot perform deletions.');
            exit(1);
        }

        $pads = $this->fetchPads($homepageId);
        $padCount = count($pads);
        $logger->info(sprintf('Found %d active Etherpad pad(s) for homepage %d', $padCount, $homepageId));

        if ($padCount === 0) {
            $logger->notice(sprintf('No active Etherpad pads found for homepage %d.', $homepageId));
            exit(0);
        }

        $summary = [
            'padsFound' => $padCount,
            'padsDeleted' => 0,
            'metaRowsDeleted' => 0,
            'padDeleteFailures' => 0,
        ];

        foreach ($pads as $index => $padRow) {
            $padId = (string)($padRow['pad_id'] ?? '');
            $padRecordId = isset($padRow['id']) ? (int)$padRow['id'] : 0;

            if ($padId === '' || $padRecordId <= 0) {
                $logger->warning(sprintf('Skipping malformed etherpad_pad record: %s', json_encode($padRow, JSON_THROW_ON_ERROR)));
                continue;
            }

            $logger->info(sprintf('[%d/%d] Processing pad %s', $index + 1, $padCount, $padId));

            $padDeleted = $this->deleteEtherpadPad($etherpadClient, $padId, $logger);
            if ($padDeleted) {
                $summary['padsDeleted']++;
                if (!$dryRun) {
                    $summary['metaRowsDeleted'] += $this->hardDeletePadMeta($padRecordId);
                }
            } else {
                $summary['padDeleteFailures']++;
            }
        }

        $logger->notice(sprintf(
            'Summary: padsFound=%d padsDeleted=%d padDeleteFailures=%d metaRowsDeleted=%d',
            $summary['padsFound'],
            $summary['padsDeleted'],
            $summary['padDeleteFailures'],
            $summary['metaRowsDeleted'],
        ));

        exit($summary['padDeleteFailures'] > 0 ? 1 : 0);
    }

    /**
     * Fetch Etherpad's (from the db) associated with the given homepage ID.
     * @param int $homepageId
     * @return array<int, array<string, mixed>>
     */
    private function fetchPads(int $homepageId): array
    {
        $query = "SELECT id, pad_id FROM etherpad_pad WHERE homepage_id = :homepageId AND deleted_at IS NULL";
        $statement = $this->getDatabaseInternal()->prepare($query);
        $statement->execute(['homepageId' => $homepageId]);

        return $statement->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Build and return an Etherpad API client if configuration exists.
     *
     * @param LoggerInterface $logger
     * @return EtherpadClient|null
     */
    private function createEtherpadClient(LoggerInterface $logger): ?EtherpadClient
    {
        $apiToken = (string)$this->getConfig()->get('etherpad_api_token');
        $etherpadHost = (string)$this->getConfig()->get(ConfigMapper::NAME_ETHERPAD_HOST);

        if ($apiToken === '' || $etherpadHost === '') {
            $logger->warning('Etherpad API credentials are not configured');
            return null;
        }

        try {
            return new EtherpadClient($apiToken, $etherpadHost);
        } catch (\Throwable $e) {
            $logger->error(sprintf('Failed to create Etherpad client: %s', $e->getMessage()));
            return null;
        }
    }

    /**
     * Delete a pad from Etherpad using the EtherpadLite API.
     *
     * @param EtherpadClient $client
     * @param string $padId
     * @param LoggerInterface $logger
     * @return bool True when the pad deletion was successful.
     */
    private function deleteEtherpadPad(EtherpadClient $client, string $padId, LoggerInterface $logger): bool
    {
        try {
            if ($this->perform()) {
                $result = $client->deletePad($padId);
            } else {
                $result = $client->getRevisionsCount($padId);
            }
        } catch (\Throwable $e) {
            $logger->warning(sprintf("Failed to delete Etherpad pad '%s': %s", $padId, $e->getMessage()));
            return false;
        }

        $code = method_exists($result, 'getCode') ? $result->getCode() : null;
        $message = method_exists($result, 'getMessage') ? $result->getMessage() : null;

        if ($code !== null && (string)$code === '0') {
            $logger->notice(sprintf("Deleted Etherpad pad '%s'", $padId));
            return true;
        }

        $logger->warning(sprintf("Etherpad API did not report success for pad '%s' (code=%s, message=%s)", $padId, $code, $message));
        return false;
    }

    /**
     * Permanently delete a local etherpad_pad metadata row.
     *
     * @param int $id
     * @return int Number of rows deleted.
     */
    private function hardDeletePadMeta(int $id): int
    {
        $statement = $this->getDatabaseInternal()->prepare('DELETE FROM etherpad_pad WHERE id = :id');
        $statement->execute(['id' => $id]);

        return $statement->rowCount();
    }
};

$kernel = new CliTool\Kernel($app, [CliKernelAbstract::OPTION_ERRORS => E_ALL]);
$kernel->runApp();
