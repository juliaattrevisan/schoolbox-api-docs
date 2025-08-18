<?php

use Ramsey\Uuid\Uuid;

require_once __DIR__ . '/../../conf/core/require.php';


/**
 * Function to work with 'standard' subjects, that have strands and sub-strands
 * @param       $handle
 * @param array $output_rows
 * @param int   $lastSubject_id
 * @param array $seenCodes
 * @param array $learningAreas
 * @return void
 */
function processRowsSubStrand($handle, array &$output_rows, int $lastSubject_id, array &$seenCodes, array $learningAreas = []): void
{
    // Set last references to.
    $lastFocus_id = $lastStage_id = $nodeParent = 0;
    // only some subjects will have Learning Area areas
    $lastLearning_id = $lastSubject_id;

    while (($row = fgetcsv($handle, escape: '')) !== false) {
        // Sanitize the description, and remove the nbsp characters...
        $row[0] = str_replace(' ', ' ', $row[0]);
        $row[0] = trim($row[0]);

        $node_id = count($output_rows) + 1;
        /**
         * then sub subjects
         * then stages / years
         * then focus area
         * then dump in the curric code
         */
        if (in_array($row[0], $learningAreas)) {
            printf("\nSub Subject: %s", $row[0]);
            //this is a sub subject and stages exist under here...
            $node_name     = $row[0];
            $output_rows[] = [$node_id, $lastSubject_id, $node_name, null, 0, null, (string)Uuid::uuid4()];
            $lastLearning_id  = $nodeParent = $node_id;
            continue;
        } elseif (str_starts_with($row[0], 'Stage') || 'Early Stage 1' === $row[0]) {
            printf("\nStage: %s", $row[0]);
            /**
             * if cell starts with Stage or is 'Early Stage' then new year level (Stage)
             */
            $node_name     = $row[0];
            $output_rows[] = [$node_id, $lastLearning_id, $node_name, null, 0, null, (string)Uuid::uuid4()];
            $lastFocus_id = $lastStage_id   = $nodeParent = $node_id;
        } else {
            //explode the description / code
            $nodeFormat = mb_split('\n', $row[0]);
            if (!isset($nodeFormat[1])) {
                printf("\nFocus Area: %s", $row[0]);
                // if there is no code, then call this a Focus Area, and continue
//                $strand_name   = trim(substr($row[0], 7));
                $output_rows[] = [$node_id, $lastStage_id, $row[0], null, 0, null, (string)Uuid::uuid4()];
                $lastFocus_id = $lastSubStrand_id = $node_id;
            } else {
                printf("\nCurric Node: %s", $row[0]);
                // curric object has code, so is the actual node, lets use it
                $node_name  = trim($nodeFormat[1]);
                if (!isset($nodeFormat[0])) {
                    $node_code = null;
                    var_dump($nodeFormat);
                } else {
                    $node_code = trim($nodeFormat[0]);
                }
                // If the curriculum code was already used, don't use it again...
                if (in_array($node_code, $seenCodes)) {
                    echo sprintf("\nDuped code: '%s'\n", $node_code);
                    continue;
                }
                $output_rows[] = [$node_id, $lastFocus_id, $node_name, $node_code, 0, null, (string)Uuid::uuid4()];
                $seenCodes[] = $node_code;
            }
        }
    }
}

// Define input and output file paths
$output_file = 'NSWCurriculum23.csv';

// Files
$subjectFiles = [
    'English' => ['English' => 'NSW English Curriculum - 2023.csv'],
    'Mathematics' => ['Mathematics' => 'NSW Mathematics Curriculum - 2023.csv'],

    'Technological and Applied Studies' => ['Technological and Applied Studies (TAS)' => 'NSW Computing Technology Curriculum - 2023.csv'],
    'Languages' => ['Languages' => 'NSW Languages Curriculum - 2023.csv'],
];

// Define the header for the output CSV file
$output_header = ['id', 'curriculum_node_parent_id', 'name', 'code', 'order', 'description', 'uuid'];

// TopLevel ID
$topParentId = 1;

// Define the last seen Subject ID
$lastSubject_id = 0;

// Seen subject list
$seenSubjects = [];

// Seen curriculum codes
$seenCodes = [];

// Define a 2-dimensional array to store the rows of the output CSV file
$output_rows = [];

$output_rows[] = [1, 0, 'NSW Curriculum 2023', null, 0, null, (string)Uuid::uuid4()];

// For each file, do things...
foreach ($subjectFiles as $subject => $filesArray) {
    // Open the input CSV file and create a CSV reader object
    foreach ($filesArray as $subSubject => $fileName) {
        if (($handle = fopen(__DIR__ . "/" . $fileName, "r")) !== false) {
            // Don't create a new entry for a subject that we have already seen
            if (!array_key_exists($subject, $seenSubjects)) {
                $node_id = count($output_rows) + 1;
                //create the subject
                $node_name      = $subject;
                $output_rows[]  = [$node_id, $topParentId, $node_name, null, 0, null, (string)Uuid::uuid4()];
                $lastStrand_id  = $lastSubStrand_id = $lastStage_id = null;
                $lastSubject_id = $node_id;
                $seenSubjects[$subject] = $node_id;
            }

            processRowsSubStrand($handle, $output_rows, $node_id, $seenCodes, ['Computing Technology', 'Aboriginal Languages K–10', 'Modern Languages K–10', 'Classical Languages K–10']);

            fclose($handle);
        }
    }
}

// Add the output header to the beginning of the output rows
array_unshift($output_rows, $output_header);

// Open the output CSV file and create a CSV writer object
if (($handle = fopen(__DIR__ . "/" . $output_file, "w")) !== false) {
    foreach ($output_rows as $output_row) {
        fputcsv($handle, $output_row, escape: '');
    }
    fclose($handle);
}
