<?php
/**
 * Standardize component configuration - ensure that all configs of type "date"
 * or "time" are stored in database format.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2015, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

require_once __DIR__.'/../../../include/Bootstrap.php';

$db     = Database::getInstance();
$logger = new \Alaress\Lumberjack\Logger();
$select = $db->prepare(
    "
        SELECT cci.id, cci.value
        FROM component_config_instance cci
        INNER JOIN component_config_definition ccd ON cci.component_config_definition_id = ccd.id
        WHERE ccd.key = ?
    "
);

// Calculate standardized dates
$updates = [];
$select->execute(['date']);
foreach ($select as $row) {
    $date = $row['value'];
    try {
        $dateObject = new Alaress_Date_Format($date, Alaress_Date_Format::DATABASE_DATE_FORMAT);
    } catch (InvalidArgumentException $e) {
        // Not a valid date, update it
        $timestamp = strtotime($date);

        if ($timestamp !== false) {
            $dateObject = new Alaress_Date_Format($timestamp, Alaress_Date_Format::TIMESTAMP);
        }

        $updates[] = [
            'id'       => $row['id'],
            'oldValue' => $date,
            'newValue' => $timestamp ? $dateObject->getDatabaseDate() : null,
        ];
    }
}

// Calculate standardized times
$select->execute(['time']);
foreach ($select as $row) {
    $date = $row['value'];
    try {
        $dateObject = new Alaress_Date_Format($date, Alaress_Date_Format::DATABASE_TIME_FORMAT);
    } catch (InvalidArgumentException $e) {
        // Not a valid time, update it
        $timestamp = strtotime($date);
        if ($timestamp !== false) {
            $dateObject = new Alaress_Date_Format($timestamp, Alaress_Date_Format::TIMESTAMP);
        }

        $updates[] = [
            'id'       => $row['id'],
            'oldValue' => $date,
            'newValue' => $timestamp ? $dateObject->getDatabaseTime() : null,

        ];

    }
}

// Run the standardization queries
$logger->info(sprintf('Standardizing component configuration: %d to standardize', count($updates)));
$update = $db->prepare("UPDATE component_config_instance SET value = :value WHERE id = :id");
$counts = ['success' => 0, 'failure' => 0];
foreach ($updates as $row) {
    if (!isset($row['newValue'])) {
        $logger->warning(
            sprintf(
                'Skipping standardization of component configuration %d: could not parse "%s"!',
                $row['id'],
                $row['oldValue']
            )
        );
        $counts['failure']++;
        continue;
    }
    $logger->info(
        sprintf(
            'Standardizing component configuration %d from "%s" to "%s"...',
            $row['id'],
            $row['oldValue'],
            $row['newValue']
        )
    );
    if ($update->execute(['id' => $row['id'], 'value' => $row['newValue']])) {
        $logger->info(sprintf('Component configuration %d standardized!', $row['id']));
        $counts['success']++;
    } else {
        $logger->warning(sprintf('Component configuration %d standardization failed!', $row['id']));
        $counts['failure']++;
    }
}
$logger->info(
    sprintf(
        'Standardization complete: %d updated, %d failed',
        $counts['success'],
        $counts['failure']
    )
);
