<?php
/**
 * @copyright Copyright 2017, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\External\Connector;

use RuntimeException;
use Schoolbox\Sync\Iterator;

/**
 * Sync connector which is able to access custom views.
 *
 * This is meant to be used in conjunction with other `ISync` implementations;
 * if a method throws a `RuntimeException`, then the connector's default
 * implementation should be used instead.
 */
class CustomSync extends Base implements ISync
{
    /**
     * {@inheritdoc}
     *
     * @throws RuntimeException if no view has been configured for student sync
     */
    public function findAllStudents()
    {
        $customView = trim($this->config->get('external_custom_ups_student'));
        if (\strlen($customView) > 0) {
            return new Iterator\Student(
                $this->databaseExternal->executeQueryIterator("
                    SELECT DISTINCT *
                    FROM {$this->databaseExternal->quoteIdentifier($customView)}
                    ORDER BY student_id ASC
                ")
            );
        }
        throw new RuntimeException('No custom view has been configured for student sync');
    }

    /**
     * {@inheritdoc}
     *
     * @throws RuntimeException if no view has been configured for parent sync
     */
    public function findAllParents()
    {
        $customView = trim($this->config->get('external_custom_ups_parent'));
        if (\strlen($customView) > 0) {
            return new Iterator\Guardian(
                $this->databaseExternal->executeQueryIterator("
                    SELECT DISTINCT *
                    FROM {$this->databaseExternal->quoteIdentifier($customView)}
                    ORDER BY student_contact_id ASC
                ")
            );
        }
        throw new RuntimeException('No custom view has been configured for parent sync');
    }

    /**
     * {@inheritdoc}
     *
     * @throws RuntimeException if no view has been configured for staff sync
     */
    public function findAllStaff()
    {
        $customView = trim($this->config->get('external_custom_ups_staff'));
        if (\strlen($customView) > 0) {
            return new Iterator\Staff(
                $this->databaseExternal->executeQueryIterator("
                    SELECT DISTINCT *
                    FROM {$this->databaseExternal->quoteIdentifier($customView)}
                    ORDER BY staff_id ASC
                ")
            );
        }
        throw new RuntimeException('No custom view has been configured for staff sync');
    }
}
