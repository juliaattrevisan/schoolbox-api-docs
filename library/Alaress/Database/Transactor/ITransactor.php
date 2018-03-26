<?php
/**
 * @copyright Copyright 2018, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

declare(strict_types=1);

namespace Alaress\Database\Transactor;

/**
 * Interface ITransactor
 *
 * @package Alaress\Database\Transactor
 */
interface ITransactor
{

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
}
