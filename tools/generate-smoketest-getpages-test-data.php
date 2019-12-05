<?php
$codeception_data_folder='./test/codeception/_data/';
$codeception_testdata_path=$codeception_data_folder.'SmokeTests/GetSmokeTestDataList.csv';
$codeception_temptestdata_path=$codeception_data_folder.'SmokeTests/newGetSmokeTestDataList.csv';

$url_pages_csv = array_map('str_getcsv', file($codeception_data_folder.'SmokeTests/GetSmokeTestUrlList.csv'));
$persona_csv = array_map('str_getcsv', file($codeception_data_folder.'SmokeTests/GetSmokeTestPersonaList.csv'));

if(file_exists($codeception_testdata_path))
{
    $old_fetchdata_file=array_map('str_getcsv', file($codeception_testdata_path));
} else {
    $old_fetchdata_file=null;
}

$csv_output_location=$codeception_data_folder.'SmokeTests/newGetSmokeTestDataList.csv';

$entriesInserted=[];

//Open file pointer.
$fetchpages_data_file = fopen($csv_output_location, 'w');

if(isset($old_fetchdata_file))
{
    foreach($old_fetchdata_file as $key => $old_entry)
    {
        $old_fetchdata_file[$old_entry[2].$old_entry[1]]=$old_entry;
        unset($old_fetchdata_file[$key]);
    }
    
    foreach($url_pages_csv as $url_entry){
        foreach($persona_csv as $persona){
            if (!key_exists($url_entry[1].$persona[0], $entriesInserted))
            {
                if (key_exists($url_entry[1].$persona[0], $old_fetchdata_file))
                {
                    // If entry exists, copy it and remove it from old list. All left over items will be not created in effect deleting them.
                    fputcsv($fetchpages_data_file, $old_fetchdata_file[$url_entry[1].$persona[0]]);
                    unset($old_fetchdata_file[$url_entry[1].$persona[0]]);
                } else {
                    // For each new entry that we have not seen before.
                    fputcsv($fetchpages_data_file, [$url_entry[0], $persona[0], $url_entry[1], 'UNKNOWN']);
                }
                $entriesInserted[$url_entry[1].$persona[0]]=0;
            }
        }
    }
    
    $deleted_entries_file = fopen($codeception_data_folder.'SmokeTests/temp/'.date('YmdHis').'_deletedFetchPagesEntries.csv', 'w');
    
    foreach($old_fetchdata_file as $old_entry)
    {
        fputcsv($deleted_entries_file, $old_entry);
    }
    
    fclose($deleted_entries_file);
    
    rename($codeception_testdata_path,$codeception_data_folder.'SmokeTests/temp/'.date('YmdHis').'_oldGetSmokeTestPersonaList.csv');
    
} else {
    foreach($url_pages_csv as $url_entry){
        foreach($persona_csv as $persona){
            fputcsv($fetchpages_data_file, [$url_entry[0], $persona[0], $url_entry[1], 'UNKNOWN']);
        }
    }
}


fclose($fetchpages_data_file);
rename($codeception_temptestdata_path,$codeception_testdata_path);
?>