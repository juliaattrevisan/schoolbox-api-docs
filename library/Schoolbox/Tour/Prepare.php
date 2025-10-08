<?php

namespace Schoolbox\Tour;

use Schoolbox\Application\CliTool;

class Prepare extends CliTool\App
{
    /**
     * The feature tours that are obsolete and scheduled for soft-deletion
     *
     * @var array|string[]
     */
    protected array $obsoleteTitles = [
        '23.0 Release',
        '23.1 Release',
        '24.0 Release',
        '24.1 Release',
    ];


    /**
     * Soft-delete all tours listed in $obsoleteTitles
     *
     * @return void
     */
    public function run(): void
    {
        $now = (\Alaress_Date_Format::now())->getDatabase();

        /* We'll be reusing this a few times
         */
        $tourStatement = $this->getDatabaseInternal()->prepareQuery('
            SELECT uuid 
            FROM tour 
            WHERE title = :title
        ');

        /* Mass-update a tour's contents via this set of statements
         */
        $statements = $this->prepareStatements();

        /* Soft-delete every tour in the $obsoleteTitles list
         */
        foreach ($this->obsoleteTitles as $title) {
            $tourStatement->execute([
                'title' => $title
            ]);

            $tourUuids = $tourStatement->fetchAll(\PDO::FETCH_COLUMN);

            /* Soft-delete all contents of the obsoleted tour, too
             */
            foreach ($tourUuids as $tourUuid) {
                // 'UPDATE SET deleted_at' commands for all tour tables containing deleted_at columns.
                foreach ($statements as $statement) {
                    $statement->execute([
                        'uuid' => $tourUuid,
                        'now' => $now
                    ]);
                }
            }
        }
    }


    /**
     * Mass-prepare a bunch of queries that we'll inevitably recycle;
     * returns an array of prepared statements ready for execution to obsolete a tour's contents
     *
     * @return array
     */
    private function prepareStatements(): array
    {
        $updateTourStepStatement = $this->getDatabaseInternal()->prepare('
            UPDATE tour_step
            SET deleted_at = :now
            WHERE tour_uuid = :uuid
        ');

        $updateTourStepEventStatement = $this->getDatabaseInternal()->prepare('
            UPDATE tour_step_event
            SET deleted_at = :now
            WHERE tour_step_uuid IN (
                SELECT uuid FROM tour_step WHERE tour_uuid = :uuid
            )
        ');

        $updateTourStepModalStatement = $this->getDatabaseInternal()->prepare('
            UPDATE tour_step_modal
            SET deleted_at = :now
            WHERE tour_step_uuid IN (
                SELECT uuid FROM tour_step WHERE tour_uuid = :uuid
            )
        ');

        $updateTourStepTooltipStatement = $this->getDatabaseInternal()->prepare('
            UPDATE tour_step_tooltip
            SET deleted_at = :now
            WHERE tour_step_uuid IN (
                SELECT uuid FROM tour_step WHERE tour_uuid = :uuid
            )
        ');

        $updateTourTriggerStatement = $this->getDatabaseInternal()->prepare('
            UPDATE tour_trigger
            SET deleted_at = :now
            WHERE tour_uuid = :uuid
        ');

        $updateTourStatement = $this->getDatabaseInternal()->prepare('
            UPDATE tour
            SET deleted_at = :now
            WHERE uuid = :uuid
        ');

        /* The ordering!
         */
        return [
            $updateTourStepStatement,
            $updateTourStepEventStatement,
            $updateTourStepModalStatement,
            $updateTourStepTooltipStatement,
            $updateTourTriggerStatement,
            $updateTourStatement
        ];
    }


    public function runAsRoot(): ?bool
    {
        return null;
    }
}
