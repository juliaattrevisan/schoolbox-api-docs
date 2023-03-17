<?php

// Define input and output file paths
$input_file = 'input.csv';
$output_file = 'AustralianCurriculumV9.csv';

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

// Open the input CSV file and create a CSV reader object
if (($handle = fopen($input_file, "r")) !== false) {
    while (($row = fgetcsv($handle)) !== false) {
        $node_id = count($output_rows) + 1;
        /**
         * first process subjects
         * then years
         * then strands
         * then substrands
         * then dump in the curric code
         */
        if (in_array(trim($row[0]), $subjects)) {
            // if subjects, reset the strand / substrand counters
            $node_name = trim($row[0]);
            $output_rows[] = [$node_id, $topParentId, $node_name, null, 0, null];
            $lastStrand_id = $lastSubStrand_id = $lastYear_id = null;
            $lastSubject_id = $node_id;
        } else if (str_starts_with($row[0], 'Year') || 'Foundation' === $row[0]) {
            /**
             * if cell starts with Year or is 'Foundation' then new year level
             */
            $node_name = trim($row[0]);
            $output_rows[] = [$node_id, $lastSubject_id, $node_name, null, 0, null];
            $lastYear_id = $node_id;
        } else if (str_starts_with($row[0], 'Strand:')) {
            // If the row contains a strand, create a new node in the output CSV file
            $strand_name   = trim(substr($row[0], 7));
            $output_rows[] = [$node_id, $lastYear_id, $strand_name, null, 0, null];
            $lastStrand_id = $node_id;
        } else if (str_starts_with($row[0], 'Sub-strand:')) {
            // If the row contains a sub-strand or an achievement standard, create a new node in the output CSV file
            $node_name = trim(substr($row[0], 11));
            $output_rows[] = [$node_id, $lastStrand_id, $node_name, null, 0, null];
            $lastSubStrand_id = $node_id;
        }  else {
            //this will just be a code... hopefully....
            $nodeFormat = mb_split('\n', $row[0]);
            $node_name = trim($nodeFormat[0]);
            if (!isset($nodeFormat[1])) {
                $node_code = null;
                var_dump($nodeFormat);
            } else {
                $node_code = trim($nodeFormat[1]);
            }
            $output_rows[] = [$node_id, $lastSubStrand_id, $node_name, $node_code, 0, null];
        }
    }
    fclose($handle);
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
