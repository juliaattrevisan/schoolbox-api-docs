<?php
/**
 * @copyright Copyright 2017, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\External\Connector;

use RuntimeException;

/**
 * Composite sync connector.
 *
 * `findAll` methods on this object will call each sub-sync's `findAll` in
 * order of addition, until one doesn't throw a `RuntimeException`.
 */
class CompositeSync implements ISync
{
    /**
     * @var ISync[]
     */
    private $subSyncs = [];

    /**
     * Add a sub-sync.
     *
     * @param ISync $sync
     * @return self
     */
    public function addSubSync(ISync $sync) : self
    {
        $this->subSyncs[] = $sync;
        return $this;
    }

    /**
     * {@inheritdoc}
     *
     * @throws RuntimeException if no sub-sync implements student sync
     */
    public function findAllStudents()
    {
        $lastException = null;
        foreach ($this->subSyncs as $sync) {
            try {
                return $sync->findAllStudents();
            } catch (RuntimeException $e) {
                $lastException = $e;
            }
        }
        throw new RuntimeException('No sub-sync implements student sync', 0, $lastException);
    }

    /**
     * {@inheritdoc}
     *
     * @throws RuntimeException if no sub-sync implements parent sync
     */
    public function findAllParents()
    {
        $lastException = null;
        foreach ($this->subSyncs as $sync) {
            try {
                return $sync->findAllParents();
            } catch (RuntimeException $e) {
                $lastException = $e;
            }
        }
        throw new RuntimeException('No sub-sync implements parent sync', 0, $lastException);
    }

    /**
     * {@inheritdoc}
     *
     * @throws RuntimeException if no sub-sync implements staff sync
     */
    public function findAllStaff()
    {
        $lastException = null;
        foreach ($this->subSyncs as $sync) {
            try {
                return $sync->findAllStaff();
            } catch (RuntimeException $e) {
                $lastException = $e;
            }
        }
        throw new RuntimeException('No sub-sync implements staff sync', 0, $lastException);
    }
}
