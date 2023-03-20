<?php

/**
 * Function to work with 'standard' subjects, that have strands and substrands
 * @param $handle
 * @param $output_rows
 * @param $lastSubject_id
 * @return void
 */
function processRowsSubStrand($handle, &$output_rows, $lastSubject_id)
{
    // Set last references to.
    $lastYear_id = $lastStrand_id = $lastSubStrand_id = 0;

    while (($row = fgetcsv($handle)) !== false) {
        $node_id = count($output_rows) + 1;
        /**
         * then years
         * then strands
         * then substrands
         * then dump in the curric code
         */
        if (str_starts_with($row[0], 'Year') || 'Foundation' === $row[0]) {
            /**
             * if cell starts with Year or is 'Foundation' then new year level
             */
            $node_name     = trim($row[0]);
            $output_rows[] = [$node_id, $lastSubject_id, $node_name, null, 0, null];
            $lastYear_id   = $node_id;
        } else {
            if (str_starts_with($row[0], 'Strand:')) {
                // If the row contains a strand, create a new node in the output CSV file
                $strand_name   = trim(substr($row[0], 7));
                $output_rows[] = [$node_id, $lastYear_id, $strand_name, null, 0, null];
                $lastStrand_id = $node_id;
            } else {
                if (str_starts_with($row[0], 'Sub-strand:')) {
                    // If the row contains a sub-strand or an achievement standard, create a new node in the output CSV file
                    $node_name        = trim(substr($row[0], 11));
                    $output_rows[]    = [$node_id, $lastStrand_id, $node_name, null, 0, null];
                    $lastSubStrand_id = $node_id;
                } else {
                    //this will just be a code... hopefully....
                    $nodeFormat = mb_split('\n', $row[0]);
                    $node_name  = trim($nodeFormat[0]);
                    if (!isset($nodeFormat[1])) {
                        $node_code = null;
                        var_dump($nodeFormat);
                    } else {
                        $node_code = trim($nodeFormat[1]);
                    }
                    $output_rows[] = [$node_id, $lastSubStrand_id, $node_name, $node_code, 0, null];
                }
            }
        }
    }
}

/**
 * Function to work with 'standard' subjects, that have strands with NO sub strands
 * @param $handle
 * @param $output_rows
 * @param $lastSubject_id
 * @return void
 */
function processRowsStrand($handle, &$output_rows, $lastSubject_id)
{
    // Set last references to.
    $lastYear_id = $lastStrand_id = 0;

    while (($row = fgetcsv($handle)) !== false) {
        $node_id = count($output_rows) + 1;
        /**
         * then years
         * then strands
         * then dump in the curric code
         */
        if (str_starts_with($row[0], 'Year') || 'Foundation' === $row[0]) {
            /**
             * if cell starts with Year or is 'Foundation' then new year level
             */
            $node_name     = trim($row[0]);
            $output_rows[] = [$node_id, $lastSubject_id, $node_name, null, 0, null];
            $lastYear_id   = $node_id;
        } else {
            if (str_starts_with($row[0], 'Strand:')) {
                // If the row contains a strand, create a new node in the output CSV file
                $strand_name   = trim(substr($row[0], 7));
                $output_rows[] = [$node_id, $lastYear_id, $strand_name, null, 0, null];
                $lastStrand_id = $node_id;
            } else {
                //this will just be a code... hopefully....
                $nodeFormat = mb_split('\n', $row[0]);
                $node_name  = trim($nodeFormat[0]);
                if (!isset($nodeFormat[1])) {
                    $node_code = null;
                    var_dump($nodeFormat);
                } else {
                    $node_code = trim($nodeFormat[1]);
                }
                $output_rows[] = [$node_id, $lastStrand_id, $node_name, $node_code, 0, null];
            }
        }
    }
}
// Define input and output file paths
$output_file = 'AustralianCurriculumV9.csv';

// Files
$subjectFiles = [
    'English' => ['english-scope-and-sequence-f-10-v9.csv'],
    'Health and Physical Education' => ['health-and-physical-education-scope-and-sequence-f-10-v9.csv'],
    'Mathematics' => ['mathematics-scope-and-sequence-f-10-v9.csv'],
    'Science' => ['science-scope-and-sequence-f-10-v9.csv'],
    'Technologies' => [],
];

// Subject Array
$subjects = [
    'English',
];

// Define the header for the output CSV file
$output_header = ['id', 'curriculum_node_parent_id', 'name', 'code', 'order', 'description'];

// TopLevel ID
$topParentId = 1;

// Define the last seen Subject ID
$lastSubject_id = 0;

// Define the last seen Year id
$lastYear_id = 0;

// Define the seen strand id
$lastStrand_id = 0;

// Define the seen sub strand id
$lastSubStrand_id = 0;

// Define a 2-dimensional array to store the rows of the output CSV file
$output_rows = [];

$output_rows[] = [1, 0, 'Australian Curriculum - V9', null, 0, null];

// For each file, do things...
foreach ($subjectFiles as $subject => $filesArray) {
    // Open the input CSV file and create a CSV reader object
    foreach ($filesArray as $fileName) {
        if (($handle = fopen($fileName, "r")) !== false) {
            $node_id = count($output_rows) + 1;
            //create the subject
            $node_name      = $subject;
            $output_rows[]  = [$node_id, $topParentId, $node_name, null, 0, null];
            $lastStrand_id  = $lastSubStrand_id = $lastYear_id = null;
            $lastSubject_id = $node_id;

            switch ($subject) {
                case "Mathematics":
                    processRowsStrand($handle, $output_rows, $lastSubject_id);
                    break;
                case "Technologies":
                    break;
                default:
                    processRowsSubStrand($handle, $output_rows, $lastSubject_id);
            }
            fclose($handle);
        }
    }
}

// Add the output header to the beginning of the output rows
array_unshift($output_rows, $output_header);

// Open the output CSV file and create a CSV writer object
if (($handle = fopen($output_file, "w")) !== false) {
    foreach ($output_rows as $output_row) {
        fputcsv($handle, $output_row);
    }
    fclose($handle);
}
