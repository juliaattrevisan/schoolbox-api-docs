<?php

declare(strict_types=1);

use Schoolbox\Application\CliTool;

require_once __DIR__ . '/../../conf/core/require.php';

/**
 * With the replacement of spectrum-colourpicker to Coloris, HEX codes must be formatted with a leading hash.
 * This script will ensure all existing HEX codes in the database to include the hash, so that Coloris will load them correctly.
 *
 * For example, "FF0000" will be converted to "#FF0000"
 *  or "ff0000a0" will be converted to "#ff0000a0"
 */
$app = new class extends CliTool\App
{
    public function run(): void
    {
        // Every single place in the database where a HEX code could be stored... hopefully. :)
        $colourFields = [
            'config' => [
                'primary_key' => 'configID',
                'fields' => [
                    'value',
                ],
                'restriction' => "WHERE field_id = (SELECT id FROM lu_config_field WHERE field = 'colour')",
            ],
            'folder' => [
                'primary_key' => 'id',
                'fields' => [
                    'colour',
                ],
            ],
            'luEventType' => [
                'primary_key' => 'eventTypeID',
                'fields' => [
                    'eventColour',
                ],
            ],
            'lu_timetable_colour' => [
                'primary_key' => 'timetable_colour_id',
                'fields' => [
                    'hex_colour',
                ],
            ],
            'tile' => [
                'primary_key' => 'id',
                'fields' => [
                    'hover_color',
                    'title_background_color',
                    'title_color',
                ],
            ],
            'lu_pastoral_severity' => [
                'primary_key' => 'id',
                'fields' => [
                    'color',
                ],
            ],
            'lu_pastoral_type' => [
                'primary_key' => 'id',
                'fields' => [
                    'color',
                ],
            ],
            'lu_pastoral_subtype' => [
                'primary_key' => 'id',
                'fields' => [
                    'color',
                ],
            ],
            'homepage_config' => [
                'primary_key' => 'cid',
                'fields' => [
                    'left_bg_primary',
                    'left_bg_secondary',
                    'left_font',
                    'left_head_bg',
                    'left_head_font',
                    'left_hover',
                    'left_link',
                    'right_bg_primary',
                    'right_bg_secondary',
                    'right_font',
                    'right_head_bg',
                    'right_head_font',
                    'right_hover',
                    'right_link',
                    'top_bg_primary',
                    'top_bg_secondary',
                    'top_font',
                    'top_head_bg',
                    'top_head_font',
                    'top_hover',
                    'top_link',
                ],
            ],
            'eportfolio_page' => [
                'primary_key' => 'page_id',
                'fields' => [
                    'left_bg_primary',
                    'left_bg_secondary',
                    'left_font',
                    'left_head_bg',
                    'left_head_font',
                    'left_hover',
                    'left_link',
                    'right_bg_primary',
                    'right_bg_secondary',
                    'right_font',
                    'right_head_bg',
                    'right_head_font',
                    'right_hover',
                    'right_link',
                    'top_bg_primary',
                    'top_bg_secondary',
                    'top_font',
                    'top_head_bg',
                    'top_head_font',
                    'top_hover',
                    'top_link',
                ],
            ],
        ];

        $updatedAmtTotal = 0;

        $dbc = $this->getDatabaseInternal();

        foreach ($colourFields as $table => $tableConfig) {
            // Get all necessary rows in the table
            $rows = $dbc->executeQuery(
                'SELECT ' . $tableConfig['primary_key'] . ', ' . implode(', ', $tableConfig['fields']) . ' FROM ' . $table . ' ' . ($tableConfig['restriction'] ?? '')
            )->fetchAll();

            foreach ($tableConfig['fields'] as $field) {
                $updatedAmt = 0;

                // Loop through each row and update the HEX codes in each field
                foreach ($rows as $row) {
                    // Check if the value is empty
                    if (empty($row[$field])) {
                        $this->getLogger()->debug(sprintf(
                            '  Skipped %s.%s identifier %s because it is empty',
                            $table,
                            $field,
                            $row[$tableConfig['primary_key']]
                        ));
                        continue;
                    }

                    // If the value already has a hash, skip it
                    if (str_starts_with($row[$field], '#')) {
                        continue;
                    }

                    // Check if the value is a HEX code (3, 6, or 8 characters, without a hash)
                    if (!preg_match('/^(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6}|[0-9a-fA-F]{8})$/', $row[$field])) {
                        continue;
                    }

                    $updatedAmt++;

                    $dbc->executeQuery(
                        'UPDATE ' . $table . ' SET ' . $field . ' = :value WHERE ' . $tableConfig['primary_key'] . ' = :id',
                        ['id' => $row[$tableConfig['primary_key']], 'value' => '#' . $row[$field]]
                    );
                }

                $updatedAmtTotal += $updatedAmt;
                if ($updatedAmt > 0) {
                    $this->getLogger()->info("Updated " . $table . "." . $field . " - " . $updatedAmt . " HEX codes replaced");
                } else {
                    $this->getLogger()->info("No HEX codes to update in " . $table . "." . $field . "");
                }
            }
        }

        $this->getLogger()->info("Finished updating HEX codes, changed a total of " . $updatedAmtTotal);
    }
};

/** @noinspection PhpUnhandledExceptionInspection */
(new Schoolbox\Application\CliTool\Kernel(
    $app,
    [Schoolbox\Application\CliTool\Kernel::OPTION_LEVEL => Monolog\Logger::DEBUG]
))->runApp();
