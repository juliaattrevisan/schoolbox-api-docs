<?php
/**
 * @copyright Copyright 2018, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

declare(strict_types=1);

namespace Alaress\Database\Transactor;

use Alaress\Database\Transactor\Strategy\IStrategy;

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
     * @throws \Throwable Anything that $callback throws
     */
    public function transact(callable $callback): bool;

    /**
     * Begin transaction
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
     * Commit transaction
     *
     * @return bool Did it commit?
     */
    public function commit(): bool;

    /**
     * Rollback current transaction
     *
     * @return bool Did it roll back?
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
