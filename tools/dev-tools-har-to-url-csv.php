<?php
$longopts = array('debug::', 'help::');
$shortopts = 'f:i:o:';

$options = getopt($shortopts,$longopts);

$debug=array_key_exists('debug', $options);
$help=array_key_exists('help', $options);

if ($help)
{
    printf("-- Help --\n");
    printf("\ndev_tools_har_to_url_csv.php is used to convert a har file from a dev tools browser output in the network tab into a csv of urls.\n");
    printf("Usage - dev_tools_har_to_url_csv.php -i inputHarLocation  -o outPutCsvFile [-f harFormat] [--debug] [--help]\n\n");
    printf("-i input (required) location can be noted multiple times if you want to process multiple files, or you can denote a folder to process all har files in the folder.\n");
    printf("-o ouput (required) location is stated once as the file location you want to save the resulting csv to.\n");
    printf("-f format (optional), it defaults to chrome and only supports firefox and chrome. It doesn't really seem to matter between those two browsers\n");
    printf("--debug (optional), show debug information\n");
    printf("--help (optional), displays this help message\n\n");
    exit (1);
}

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

if(!array_key_exists('i', $options) || !key_exists('o', $options))
{
    printf("Please set the browser the har file came from, the location of where the HAR file we are converting and the location we are outputting the csv. eg.\n");
    printf("php dev_tools_har_to_url_csv.php -f chrome -i thisFIle.har -o toThisCsv.csv\n");
    exit(1);
}

if(!strcasecmp($argv[1], 'firefox') && !strcasecmp($argv[1], 'chrome'))
{
    printf("Right now only support firefox and chrome HAR exported formats\n");
}

if (!array_key_exists('f', $options))
{
    $options['f']='chrome';
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
    if ($debug)
    {
        printf("Attempt to import: %s, %s, %s\n", $host, $url, $extention);
    }
    if(!array_key_exists($log_entry['request']['url'], $have_seen) && !array_key_exists($extention, $filtered_extensions) && $VAGRANT_DOMAIN==$host && !isset($port))
    {
        //Write the row to the CSV file.
        fputcsv($csv_file, ['', 'Superuser', 'Har Exported Action', 'url' => $log_entry['request']['url']]);
        $have_seen[$log_entry['request']['url']] = 'url';
        if ($debug) {
            printf("Inserted url: %s\n", $log_entry['request']['url']);
        }
    } else {
        if ($debug) {
            printf("Ignored url as its either in filter or has been seen before: %s\n", $log_entry['request']['url']);
        }
    }
}

printf("Finished extracting URL requests from HAR file, extracted %s records\n", count($have_seen));

//Finally, close the file pointer.
fclose($csv_file);

