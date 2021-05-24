<?php

/**
 * @author      Steve Chan <steve.chan@schoolbox.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        http://www.schoolbox.com.au/
 */

declare(strict_types=1);

use Schoolbox\Application\CliTool;

require_once __DIR__ . '/../../../conf/core/require.php';

$app = new class extends CliTool\App {

    // effect types
    private const MAPS   = 1;
    private const EQUALS = 2;

    /**
     * Migrate ups_effect.arguments values for the Year user_property
     */
    public function run(): void
    {
        $db = $this->getDatabaseInternal();

        /* Find a list of all the (lu_year_level.name, lu_year_level.id) tuples,
         * indexed by lu_year_level.name; we're gonna use that as a lookup
         */
        $yearLevelsStatement = $db->prepareQuery(
            '
            SELECT id, name
            FROM lu_year_level
            WHERE deleted_at IS NULL
            ORDER BY name
        '
        );

        $yearLevelsStatement->execute();

        /* Set up lookup
         */
        $yearLevels = $yearLevelsStatement->fetchRowArray('name');

        /* Find all the UPS Effects for "year"
         */
        $effectStatement = $db->prepareQuery(
            '
            SELECT id, effect_type_id, arguments
            FROM ups_effect
            WHERE user_property = :userPropertyYear
        '
        );

        $effectStatement->execute(
            [
                'userPropertyYear' => 'year',
            ]
        );

        $effects = $effectStatement->fetchAll(PDO::FETCH_ASSOC);

        /* Set up update statement
         */
        $updateStatement = $db->prepareQuery(
            '
            UPDATE ups_effect
            SET arguments = :arguments, effect_type_id = :effectTypeId
            WHERE id = :id
        '
        );

        foreach ($effects as $effect) {
            /* Get their string and unpack it
             */
            $row = json_decode($effect['arguments'], true);

            $effectTypeId = (int)$effect['effect_type_id'];

            // each of decoded arguments should have 1 or 2 depending on its effect type id
            if (
                ($effectTypeId === self::EQUALS && count($row) !== 1)
                || ($effectTypeId === self::MAPS && count($row) !== 2)
            ) {
                $this->getLogger()->warning("Effect {$effect['id']} has unexpected arguments. Skipping.");
                continue;
            }

            if ($effectTypeId === self::MAPS) {
                /* Everything inside [1] is a key-value pair
                 */
                foreach ($row[1] as $key => $name) {
                    /* Replace the value which is the equivalent of lu_year_level.name,
                     * with lu_year_level.id - anything that doesn't match should be given null
                     * Such values are no longer determinate by us and the UPS admin needs to repair it
                     */
                    $row[1][$key] = $yearLevels[$name]['id'] ?? null;
                }
            } elseif (preg_match('/\{:.+\}/', $row[0])) {
                // Year level is set from a token
                // Convert this effect to $name => $key map
                $effectTypeId = self::MAPS;
                $row[1]       = [];
                foreach ($yearLevels as $name => $yearLevel) {
                    $row[1][$name] = $yearLevel['id'];
                }
            } else {
                // Year level is set to a single value
                // Convert the value to a Year Level key
                $row[0] = $yearLevels[$row[0]]['id'] ?? null;
            }
            /* Save it back into the object
             */
            $newArguments = json_encode($row);

            /* Repack the data payload and save it back
             */
            $updateStatement->execute(
                [
                    'arguments'    => $newArguments,
                    'effectTypeId' => $effectTypeId,
                    'id'           => $effect['id'],
                ]
            );
        }
    }
};

$options = [CliTool\Kernel::OPTION_LEVEL => Monolog\Logger::DEBUG, CliTool\Kernel::OPTION_ERRORS => E_ALL,];

/** @noinspection PhpUnhandledExceptionInspection */
(new Schoolbox\Application\CliTool\Kernel($app, $options))->runApp();
