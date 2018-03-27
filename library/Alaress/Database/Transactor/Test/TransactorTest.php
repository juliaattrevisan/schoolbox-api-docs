<?php
/**
 * @copyright Copyright 2018, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

declare(strict_types=1);

namespace Alaress\Database\Transactor\Test;

use Alaress\Database\Transactor\Base as Transactor;
use Alaress\Database\Transactor\Strategy\Dummy as DummyStrategy;
use PHPUnit\Framework\TestCase;

/**
 * Class TransactorTest
 */
class TransactorTest extends TestCase
{
    /**
     * @var Transactor
     */
    private $transactor;

    /**
     * Count for the number of times a transactor method has persisted.
     *
     * @var int
     */
    private $count;

    /**
     * Results, returned in order
     *
     * @var array
     */
    private $orderedResults;

    /**
     * Build a transactor object
     */
    public function setUp(): void
    {
        $this->transactor     = new Transactor(new DummyStrategy());
        $this->count          = 0;
        $this->orderedResults = [];
    }

    /**
     * Test trigger of onPersist outside transaction
     */
    public function testOnPersistOutsideTransaction(): void
    {
        $this->buildOnPersist();
        $this->assertEquals(1, $this->count);
    }

    /**
     * Test trigger of onPersist in transaction that is committed
     */
    public function testOnPersistInsideCommittedTransaction(): void
    {
        $this->transactor->transact(function() {
            $this->buildOnPersist();
        });
        $this->assertEquals(1, $this->count);
    }

    /**
     * Test trigger of onPersist in transaction that is rolled back
     */
    public function testOnPersistInsideRolledBackTransaction(): void
    {
        $this->transactor->transact(function() {
            $this->buildOnPersist();
            return false;
        });
        $this->assertEquals(0, $this->count);
    }

    /**
     * Test trigger of onPersist in transaction that throws an exception
     */
    public function testOnPersistInsideExceptionTransaction(): void
    {
        try {
            $this->transactor->transact(function() {
                $this->buildOnPersist();
                throw new \Exception('blah');
            });
        } catch (\Throwable $e) {
            // noop
        }
        $this->assertEquals(0, $this->count);
    }

    /**
     * Test trigger of onPersist in transaction that rolls back outside,
     * but commits inside
     */
    public function testOnPersistNestCommitInsideRollback(): void
    {
        $this->transactor->transact(function() {
            $this->buildOnPersist();

            $this->transactor->transact(function() {
                $this->buildOnPersist();
            });

            return false;
        });
        $this->assertEquals(0, $this->count);
    }

    /**
     * Test trigger of onPersist in transaction that rolls back inside,
     * but commits outside
     */
    public function testOnPersistNestRollbackInsideCommit(): void
    {
        $this->transactor->transact(function() {
            $this->buildOnPersist();

            $this->transactor->transact(function() {
                $this->buildOnPersist();
                return false;
            });
        });
        $this->assertEquals(1, $this->count);
    }

    /**
     * Test trigger of onPersist in transaction with nested commits
     */
    public function testOnPersistNestCommitInsideCommit(): void
    {
        $this->transactor->transact(function() {
            $this->buildOnPersist();

            $this->transactor->transact(function() {
                $this->buildOnPersist();
            });
        });
        $this->assertEquals(2, $this->count);
    }

    /**
     * Test trigger of onPersist with sibling levels
     */
    public function testOnPersistCommitSiblingLevels(): void
    {
        $this->transactor->transact(function() {
            $this->transactor->transact(function() {
                $this->buildOnPersist();
            });

            $this->transactor->transact(function() {
                $this->buildOnPersist();
                return false;
            });

            $this->transactor->transact(function() {
                $this->buildOnPersist();
                return false;
            });

            $this->transactor->transact(function() {
                $this->buildOnPersist();
            });
        });
        $this->assertEquals(2, $this->count);
    }

    /**
     * Test trigger of onPersist with multiple callbacks on the one level.
     */
    public function testOnPersistMultipleCallbacks(): void
    {
        $this->transactor->transact(function() {
            $this->buildOnPersist();
            $this->buildOnPersist();
            $this->buildOnPersist();
        });
        $this->assertEquals(3, $this->count);
    }

    /**
     * Test that onPersist triggers get triggered in FIFO order.
     */
    public function testOnPersistOrdering(): void
    {
        $this->transactor->transact(function() {
            $this->buildOnPersistForOrderedResults(1);
            $this->buildOnPersistForOrderedResults(2);
            $this->buildOnPersistForOrderedResults(3);
        });
        $this->assertEquals(1, $this->orderedResults[0]);
        $this->assertEquals(2, $this->orderedResults[1]);
        $this->assertEquals(3, $this->orderedResults[2]);
    }

    /**
     * Test that onPersist triggers get triggered in FIFO order.
     */
    public function testOnPersistOrderingBetweenNestingLevels(): void
    {
        $this->transactor->transact(function() {
            $this->buildOnPersistForOrderedResults(1);

            $this->transactor->transact(function() {
                $this->buildOnPersistForOrderedResults(2);

                $this->transactor->transact(function() {
                    $this->buildOnPersistForOrderedResults(3);
                    return false;
                });
            });

            $this->buildOnPersistForOrderedResults(4);
        });
        $this->assertEquals(1, $this->orderedResults[0]);
        $this->assertEquals(2, $this->orderedResults[1]);
        $this->assertEquals(4, $this->orderedResults[2]);
    }


    /**
     * Increment count; used as onPersist callback.
     */
    public function incrementCount(): void
    {
        $this->count++;
    }

    /**
     * Helper used for building onPersist callbacks.
     */
    public function buildOnPersist(): void
    {
        $this->transactor->onPersist([$this, 'incrementCount']);
    }

    /**
     * Helper used for building onPersist callbacks for testing ordered results.
     */
    public function buildOnPersistForOrderedResults($value): void
    {
        $this->transactor->onPersist(function () use ($value) {
            $this->orderedResults[] = $value;
        });
    }
}
