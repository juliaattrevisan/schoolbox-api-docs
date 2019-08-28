<?php

function getEntriesFromFolder(string $har_location)
{
    $new_har_location_list= [];
    $filelist = glob($har_location.'/*.har');
    foreach ($filelist as $file)
    {
        $new_har_location_list[]=$file;
    }
    if($new_har_location_list==[])
    {
        printf('Folder given in input but no .har files found in folder: '.$har_location);
    }
    return $new_har_location_list;
}

function jsonHarFileToUrlRequestedArray($file_location)
{
    $url_entries=[];
    $jsonString=file_get_contents($file_location);
    if ($jsonString)
    {
        $one_json_urls = json_decode($jsonString, true);
        $url_entries = $one_json_urls['log']['entries'];
    }
    return $url_entries;
}

$shortopts = 'f:i:o:';

$options = getopt($shortopts);

if(!key_exists('f', $options) || !key_exists('i', $options) || !key_exists('o', $options))
{
    printf("Please set the browser the har file came from, the location of where the HAR file we are converting and the location we are outputting the csv. eg.\n");
    printf("php dev_tools_har_to_url_csv.php -f chrome -i thisFIle.har -o toThisCsv.csv\n");
    exit(1);
}

if(!strcasecmp($argv[1], 'firefox') && !strcasecmp($argv[1], 'chrome'))
{
    printf("Right now only support firefox and chrome HAR exported formats\n");
}

$format=$options['f'];
$har_location=$options['i'];
$csv_location=$options['o'];

if (is_array($format) || is_array($csv_location))
{
    printf("2 formats or output locations supplied, can only accept singular values for those options (-f and -o)\n");
    exit(1);
}

$url_entries=[];

if (!is_array($har_location) && is_dir($har_location))
{
    $har_location = getEntriesFromFolder($har_location);
}

if (is_array($har_location))
{
    foreach($har_location as $seperateFile)
    {
        if (is_dir($seperateFile))
        {
            $seperateFolder = getEntriesFromFolder($seperateFile);
            foreach($seperateFolder as $seperateFile2)
            {
                array_push($url_entries, ...jsonHarFileToUrlRequestedArray($seperateFile2));
            }
        } else {
            array_push($url_entries, ...jsonHarFileToUrlRequestedArray($seperateFile));
        }
    }
}else {
    array_push($url_entries, ...jsonHarFileToUrlRequestedArray($har_location));
}


//Open file pointer.
$csv_file = fopen($csv_location, 'w');

$have_seen=[];
$filtered_extensions=['js' => 0, 'css' => 1];

$VAGRANT_DOMAIN='vagrant.dev.schoolbox.cloud';

//Loop through the associative array.
foreach($url_entries as $log_entry){
    $url=$log_entry['request']['url'];
    $extention = pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION);
    $host =  parse_url($url,  PHP_URL_HOST);
    $port = parse_url($url, PHP_URL_PORT);
    //printf("Imported details: %s, %s, %s\n", $url, $extention, $host);
    if(!key_exists($log_entry['request']['url'], $have_seen) && !key_exists($extention, $filtered_extensions) && $VAGRANT_DOMAIN==$host && !isset($port))
    {
        //Write the row to the CSV file.
        fputcsv($csv_file, ['url' => $log_entry['request']['url']]);
        $have_seen[$log_entry['request']['url']] = 'url';
        printf("Inserted url: %s\n", $log_entry['request']['url']);
    } else {
        printf("Ignored url as its either in filter or has been seen before: %s\n", $log_entry['request']['url']);
    }
}

//Finally, close the file pointer.
fclose($csv_file);

