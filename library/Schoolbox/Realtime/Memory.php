<?php

/**
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\Realtime;

/**
 * Class for identifying memory usage of a process.
 *
 * Required because PHP `memory_get_usage` reports only variables managed by the
 * PHP process itself. A PHP process' real memory usage also includes:
 * * The PHP interpreter itself
 * * PHP extensions
 * * Shared libraries
 */
class Memory
{
    /**
     * The operating system page size (how many bytes are allocated at once by
     * the OS).
     *
     * Required because `/proc/$pid/statm` reports a process' memory allocation
     * in pages rather than bytes.
     */
    private static ?int $pageSize = null;
    private static bool $pageSizeInitialized = false;

    /**
     * Report a process' memory usage, in bytes.
     *
     * The resident set size of the process (i.e. the memory actually currently
     * allocated to the process) will be reported, rather than its virtual
     * memory size (the total memory the process can access, including unused):
     * this is the most useful memory usage metric.
     *
     * @param int $pid A process ID
     * @return int|null The process' memory usage, or null if it can't be
     *                  determined.
     */
    public static function getUsage(int $pid): ?int
    {
        // Suppressed: the process may have died between being listed and
        // being queried here, which is an expected race, not an error.
        // Callers that care should log the null return themselves: raising a
        // warning here would, in daemon contexts, be escalated by the
        // daemon's error handler into a fatal exception (see
        // Schoolbox\Application\Daemon\Kernel::onWarning), which would abort
        // the whole daemon over one worker's disappearance.
        $usage = @file_get_contents("/proc/$pid/statm");
        if (!$usage) {
            return null;
        }

        // statm values:
        // first value is vmSize (all allocated memory)
        // second value is vmRss (currently used memory)
        $usages = preg_split('/\s+/', $usage, flags: PREG_SPLIT_NO_EMPTY);
        $vmRss  = filter_var($usages[1] ?? null, FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE);
        if (!isset($vmRss)) {
            return null;
        }

        $pageSize = self::getPageSize();
        if (!isset($pageSize)) {
            return null;
        }

        return $vmRss * $pageSize;
    }

    /**
     * Get OS page size, loading from `getconf PAGESIZE` if it isn't already.
     *
     * @return int|null Page size, or null if for whatever reason it could not
     *                  be loaded.
     */
    private static function getPageSize(): ?int
    {
        if (!self::$pageSizeInitialized) {
            self::$pageSizeInitialized = true;
            self::$pageSize = filter_var(exec('getconf PAGESIZE'), FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE);
        }
        return self::$pageSize;
    }
}
