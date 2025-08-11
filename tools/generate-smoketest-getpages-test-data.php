#!/usr/bin/php
<?php

/**
 * Deprecation notice
 */

echo "\033[1;33m/******************************************************************************************/\033[0m" . PHP_EOL;
echo "\033[1;33m/***************     Deprecated NOTICE - this will be deleted in future    ****************/\033[0m" . PHP_EOL;
echo "\033[1;33m/*    If you are running this, please add a comment of this scripts name into SA-19334    */\033[0m" . PHP_EOL;
echo "\033[1;33m/******************************************************************************************/\033[0m" . PHP_EOL;
echo "\033[1;33m/******************************************************************************************/\033[0m" . PHP_EOL;

$codeception_data_folder = './test/codeception/_data/';
$codeception_testdata_path = $codeception_data_folder . 'SmokeTests/GetSmokeTestDataList.csv';
$codeception_temptestdata_path = $codeception_data_folder . 'SmokeTests/newGetSmokeTestDataList.csv';

$url_pages_csv = array_map(
    fn ($line) => str_getcsv($line, escape: ''),
    file($codeception_data_folder . 'SmokeTests/GetSmokeTestUrlList.csv'),
);
$persona_csv = array_map(
    fn ($line) => str_getcsv($line, escape: ''),
    file($codeception_data_folder . 'SmokeTests/GetSmokeTestPersonaList.csv'),
);

$directory = $codeception_data_folder . 'SmokeTests/temp';
if (! is_dir($directory) && ! mkdir($directory) && ! is_dir($directory)) {
    throw new \RuntimeException(sprintf('Directory "%s" was not created', $directory));
}
if (file_exists($codeception_testdata_path)) {
    $old_fetchdata_file = array_map(
        fn ($line) => str_getcsv($line, escape: ''),
        file($codeception_testdata_path),
    );
} else {
    $old_fetchdata_file = null;
}

$csv_output_location = $codeception_data_folder . 'SmokeTests/newGetSmokeTestDataList.csv';

$entriesInserted = [];

//Open file pointer.
$fetchpages_data_file = fopen($csv_output_location, 'w');

if (isset($old_fetchdata_file)) {
    foreach ($old_fetchdata_file as $key => $old_entry) {
        $old_fetchdata_file[$old_entry[3] . $old_entry[2]] = $old_entry;
        unset($old_fetchdata_file[$key]);
    }

    foreach ($url_pages_csv as $url_entry) {
        foreach ($persona_csv as $persona) {
            if (!array_key_exists($url_entry[2] . $persona[0], $entriesInserted) && ($persona[3] === 'ALL' || $persona[3] === $url_entry[0])) {
                if (array_key_exists($url_entry[2] . $persona[0], $old_fetchdata_file)) {
                    // If entry exists, copy it and remove it from old list. All left over items will be not created in effect deleting them.
                    fputcsv($fetchpages_data_file, $old_fetchdata_file[$url_entry[2] . $persona[0]], escape: '');
                    unset($old_fetchdata_file[$url_entry[2] . $persona[0]]);
                } else {
                    // For each new entry that we have not seen before.
                    fputcsv($fetchpages_data_file, [$url_entry[0], $url_entry[1], $persona[0], $url_entry[2], 'UNKNOWN'], escape: '');
                }
                $entriesInserted[$url_entry[2] . $persona[0]] = 0;
            }
        }
    }

    $deleted_entries_file = fopen($codeception_data_folder . 'SmokeTests/temp/' . date('YmdHis') . '_deletedFetchPagesEntries.csv', 'w');

    foreach ($old_fetchdata_file as $old_entry) {
        fputcsv($deleted_entries_file, $old_entry, escape: '');
    }

    fclose($deleted_entries_file);

    rename($codeception_testdata_path, $codeception_data_folder . 'SmokeTests/temp/' . date('YmdHis') . '_oldGetSmokeTestPersonaList.csv');
} else {
    foreach ($url_pages_csv as $url_entry) {
        foreach ($persona_csv as $persona) {
            fputcsv($fetchpages_data_file, [$url_entry[0], $url_entry[1], $persona[0], $url_entry[2], 'UNKNOWN'], escape: '');
        }
    }
}


fclose($fetchpages_data_file);
rename($codeception_temptestdata_path, $codeception_testdata_path);

/**
 * Deprecation notice
 */
echo "\033[1;33m/******************************************************************************************/\033[0m" . PHP_EOL;
echo "\033[1;33m/***************     Deprecated NOTICE - this will be deleted in future    ****************/\033[0m" . PHP_EOL;
echo "\033[1;33m/*    If you are running this, please add a comment of this scripts name into SA-19334    */\033[0m" . PHP_EOL;
echo "\033[1;33m/******************************************************************************************/\033[0m" . PHP_EOL;
echo "\033[1;33m/******************************************************************************************/\033[0m" . PHP_EOL;
