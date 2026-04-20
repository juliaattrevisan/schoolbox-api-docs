<?php

/**
 * Justin Clarke <justin.clarke@schoolbox.education>
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\Admin\DataRetention;

use Schoolbox\Application\Exception\Exception as SchoolboxException;

class RetentionPeriod
{
    private ?string $date = null;
    private ?\Alaress_Date_Format $now = null;
    private ?int $defaultDays = null;
    private ?int $days = null;

    /**
     * Supports both the production CLI construction style and a deterministic
     * unit-test style with an injected "now".
     *
     * The class exists to collapse multiple retention inputs down to one cached
     * cutoff datetime. Keeping both entry points here avoids test-only code
     * elsewhere while preserving the current runtime API.
     *
     * @param mixed    $nowOrDays
     * @param null|int $defaultDays
     * @param null|int $days
     * @return void
     */
    public function __construct(mixed $nowOrDays = null, ?int $defaultDays = null, ?int $days = null)
    {
        if ($nowOrDays instanceof \Alaress_Date_Format || $nowOrDays === null) {
            $this->now = $nowOrDays;
            $this->defaultDays = $defaultDays;
            $this->days = $days;
            return;
        }

        if (is_int($nowOrDays) && $defaultDays === null && $days === null) {
            $this->days = $nowOrDays;
            return;
        }

        throw new \TypeError(
            sprintf(
                'RetentionPeriod expects (?Alaress_Date_Format, ?int, ?int) or (?int), %s given',
                get_debug_type($nowOrDays),
            ),
        );
    }

    /**
     * Allows callers to replace the chosen retention period and clear any cached
     * cutoff so later calculations stay aligned with the new value.
     *
     * @param null|int $days
     * @return self
     */
    public function setRetentionPeriod(?int $days): self
    {
        $this->days = $days;
        $this->date = null;

        return $this;
    }

    /**
     * Resolves the retention day count once and then derives a single cutoff
     * datetime from it.
     *
     * Caching matters because deletion strategies should make all decisions in
     * one run against a stable cutoff rather than drifting across repeated calls.
     *
     * @param int $days
     * @return string
     * @throws SchoolboxException
     */
    public function calculateDate(int $days): string
    {
        if ($this->days === null) {
            $resolvedDays = $this->defaultDays ?? $days;

            if (!new ValidRetentionPeriod()->isSatisfiedBy($resolvedDays)) {
                throw new SchoolboxException('Invalid retention period value stored; cannot calculate date cutoff.');
            }

            $this->days = $resolvedDays;
        }

        if ($this->date === null) {
            $this->date = $this->getNow()
                ->addDays(0 - $this->days)
                ->getDatabase();
        }

        return $this->date;
    }

    /**
     * Returns the scalar database datetime this helper exists to provide.
     *
     * An empty string means no retention period has been resolved yet, which
     * keeps the indefinite-retention state easy for callers and tests to assert.
     *
     * @return string
     * @throws SchoolboxException
     */
    public function getRetentionDatabase(): string
    {
        if ($this->date !== null) {
            return $this->date;
        }

        if ($this->days === null && $this->defaultDays === null) {
            return '';
        }

        return $this->calculateDate(0);
    }

    /**
     * Returns the resolved retention period in days, or null when no period has
     * been chosen yet.
     *
     * @return null|int
     */
    public function getRetentionDays(): ?int
    {
        return $this->days;
    }

    /**
     * Clones the injected date so retention calculations cannot accidentally
     * mutate the caller's date object during resolution.
     *
     * @return \Alaress_Date_Format
     */
    private function getNow(): \Alaress_Date_Format
    {
        if ($this->now instanceof \Alaress_Date_Format) {
            return clone $this->now;
        }

        return \Alaress_Date_Format::now();
    }
}
