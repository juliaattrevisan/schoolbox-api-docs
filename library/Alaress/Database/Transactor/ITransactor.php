<?php

/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Alaress\Database\Transactor;

use Alaress\Database\Transactor\Strategy\IStrategy;
use RuntimeException;
use Throwable;

/**
 * Interface ITransactor
 *
 * @package Alaress\Database\Transactor
 */
interface ITransactor
{
    /**
     * Wraps $callable in a transaction.
     *
     * If $callable throws an exception, rollback and rethrow exception
     * If $callable() === false, rollback
     * Otherwise, commit
     *
     * @param callable $callback
     * @return bool
     * @throws Throwable        This will rethrow anything that $callback throws
     * @throws RuntimeException If `commit` or `rollback` throw an exception due
     *                          to the transaction unexpectedly terminating,
     *                          this will throw that exception
     */
    public function transact(callable $callback): bool;

    /**
     * Begin transaction.
     *
     * This may be called whilst within another transaction: this will nest this
     * transaction level within the current transaction.
     * * If the nested transaction is committed, this nesting level will only be
     *   committed if the outermost level is committed
     * * If the nested transaction is rolled back, anything outside this nesting
     *   level can still be committed (if the outermost level is committed)
     *
     * @return bool Did it begin?
     */
    public function beginTransaction(): bool;

    /**
     * Am I in the middle of a transaction?
     *
     * @return bool
     */
    public function inTransaction(): bool;

    /**
     * Commit current transaction.
     *
     * @return bool Did it commit?
     * @throws RuntimeException if not within a transaction.
     *                          This can happen if this is called before opening
     *                          a transaction, or an implicit commit occurs.
     *                          (e.g. MySQL implicitly commits on schema change)
     */
    public function commit(): bool;

    /**
     * Rollback current transaction.
     *
     * @return bool Did it roll back?
     * @throws RuntimeException if not within a transaction.
     *                          This can happen if this is called before opening
     *                          a transaction, or an implicit commit occurs.
     *                          (e.g. MySQL implicitly commits on schema change)
     */
    public function rollback(): bool;

    /**
     * Add a callback that is called once (and only if) the outermost nesting
     * level is committed.
     *
     * @param callable $callback
     * @return ITransactor
     */
    public function onPersist(callable $callback): ITransactor;

    /**
     * Gets the transaction strategy.
     *
     * @return IStrategy
     */
    public function getStrategy(): IStrategy;
}
