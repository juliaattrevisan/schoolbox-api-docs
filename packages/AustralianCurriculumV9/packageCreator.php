<?php

/**
 * Function to work with 'standard' subjects, that have strands and substrands
 * @param $handle
 * @param array    $output_rows
 * @param int      $lastSubject_id
 * @param array    $seenCodes
 * @return void
 */
function processRowsSubStrand($handle, array &$output_rows, int $lastSubject_id, array &$seenCodes): void
{
    // Set last references to.
    $lastYear_id = $lastStrand_id = $lastSubStrand_id = $nodeParent = 0;

    while (($row = fgetcsv($handle, escape: '')) !== false) {
        // Sanitize the description, and remove the nbsp characters...
        // (Suppress inspection: `$row` can't possibly be false here)
        /** @noinspection PhpAutovivificationOnFalseValuesInspection */
        $row[0] = str_replace(' ', ' ', $row[0]);

        $node_id = count($output_rows) + 1;
        /**
         * then years
         * then strands
         * then substrands (if they exist, if not just use the strand Maths is like this)
         * then dump in the curric code
         */
        if (str_starts_with($row[0], 'Year') || 'Foundation' === $row[0]) {
            /**
             * if cell starts with Year or is 'Foundation' then new year level
             */
            $node_name     = trim($row[0]);
            $output_rows[] = [$node_id, $lastSubject_id, $node_name, null, 0, null];
            $lastYear_id   = $nodeParent = $node_id;
        } elseif ('Content descriptions' === trim($row[0])) {
            // weird english thing...
            $node_name        = trim($row[0]);
            $output_rows[]    = [$node_id, $lastYear_id, $node_name, null, 0, null];
            $nodeParent = $node_id;
        } else {
            if (str_starts_with($row[0], 'Strand:')) {
                // If the row contains a strand, create a new node in the output CSV file
                $strand_name   = trim(substr($row[0], 7));
                $output_rows[] = [$node_id, $nodeParent, $strand_name, null, 0, null];
                $lastStrand_id = $lastSubStrand_id = $node_id; //some nodes, aren't under a substrand but just a strand... so lets catch them here...
            } else {
                if (str_starts_with($row[0], 'Sub-strand:')) {
                    // If the row contains a sub-strand or an achievement standard, create a new node in the output CSV file
                    $node_name        = trim(substr($row[0], 11));
                    $output_rows[]    = [$node_id, $lastStrand_id, $node_name, null, 0, null];
                    $lastSubStrand_id = $node_id;
                } else {
                    // if there is no entry (blank line), ignore and move along...
                    if ('' === trim((string)$row[0])) {
                        continue;
                    }
                    //this will just be a code... hopefully....
                    $nodeFormat = mb_split('\n', $row[0]);
                    $node_name  = trim($nodeFormat[0]);
                    if (!isset($nodeFormat[1])) {
                        $node_code = null;
                        var_dump($nodeFormat);
                    } else {
                        $node_code = trim($nodeFormat[1]);
                    }
                    // If the curriculum code was already used, don't use it again...
                    if (in_array($node_code, $seenCodes)) {
                        echo sprintf("Duped code: '%s'\n", $node_code);
                        continue;
                    }
                    $output_rows[] = [$node_id, $lastSubStrand_id, $node_name, $node_code, 0, null];
                    $seenCodes[] = $node_code;
                }
            }
        }
    }
}

// Define input and output file paths
$output_file = 'AustralianCurriculumV9.csv';

// Files
$subjectFiles = [
    'English' => ['English' => 'english-scope-and-sequence-f-10-v9.csv'],
    'Humanities and Social Sciences' => [
        'HASS' => 'humanities-and-social-sciences-hass-f-6-scope-and-sequence-v9.csv',
        'Civics and Citizenship' => 'humanities-and-social-sciences-civics-and-citizenship-scope-and-sequence-v9.csv',
        'Economics and Business' => 'humanities-and-social-sciences-economics-and-business-scope-and-sequence-v9.csv',
        'Geography' => 'humanities-and-social-sciences-geography-scope-and-sequence-v9.csv',
        'History' => 'humanities-and-social-sciences-history-scope-and-sequence-v9.csv'
    ],
    'Health and Physical Education' => ['Health and Physical Education' => 'health-and-physical-education-scope-and-sequence-f-10-v9.csv'],
    'Languages' => [
        'Chinese' => 'languages-chinese-scope-and-sequence-f-10-7-10-v9.csv',
        'French' => 'languages-french-scope-and-sequence-f-10-7-10-v9.csv',
        'German' => 'languages-german-scope-and-sequence-f-10-7-10-v9.csv',
        'Indonesian' => 'languages-indonesian-scope-and-sequence-f-10-7-10-v9.csv',
        'Italian' => 'languages-italian-scope-and-sequence-f-10-7-10-v9.csv',
        'Japanese' => 'languages-japanese-scope-and-sequence-f-10-7-10-v9.csv',
        'Korean' => 'languages-korean-scope-and-sequence-f-10-7-10-v9.csv',
        'Modern Greek' => 'languages-modern-greek-scope-and-sequence-f-10-7-10-v9.csv',
        'Spanish' => 'languages-spanish-scope-and-sequence-f-10-7-10-v9.csv',
    ],
    'Mathematics' => ['Mathematics' => 'mathematics-scope-and-sequence-f-10-v9.csv'],
    'Science' => ['Science' => 'science-scope-and-sequence-f-10-v9.csv'],
    'Technologies' => [
        'Design and Technologies' => 'technologies-design-and-technologies-scope-and-sequence-f-10-v9.csv',
        'Digital Technologies' => 'technologies-digital-technologies-scope-and-sequence-f-10-v9.csv'
    ],
    'The Arts' => [
        'Dance' => 'the-arts-dance-scope-and-sequence-f-10-v9.csv',
        'Drama' => 'the-arts-drama-scope-and-sequence-f-10-v9.csv',
        'Media' => 'the-arts-media-arts-scope-and-sequence-f-10-v9.csv',
        'Music' => 'the-arts-music-scope-and-sequence-f-10-v9.csv',
        'Visual Arts' => 'the-arts-visual-arts-scope-and-sequence-f-10-v9.csv',
    ]
];

// Define the header for the output CSV file
$output_header = ['id', 'curriculum_node_parent_id', 'name', 'code', 'order', 'description'];

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

$output_rows[] = [1, 0, 'Australian Curriculum (v9)', null, 0, null];

// For each file, do things...
foreach ($subjectFiles as $subject => $filesArray) {
    // Open the input CSV file and create a CSV reader object
    foreach ($filesArray as $subSubject => $fileName) {
        if (($handle = fopen($fileName, "r")) !== false) {
            // Don't create a new entry for a subject that we have already seen
            if (!array_key_exists($subject, $seenSubjects)) {
                $node_id = count($output_rows) + 1;
                //create the subject
                $node_name      = $subject;
                $output_rows[]  = [$node_id, $topParentId, $node_name, null, 0, null];
                $lastStrand_id  = $lastSubStrand_id = $lastYear_id = null;
                $lastSubject_id = $node_id;
                $seenSubjects[$subject] = $node_id;
            }

            switch ($subject) {
                case "Languages":
                case "Humanities and Social Sciences":
                case "Technologies":
                case "The Arts":
                    // Subjects with sub subjects... need to have a sub subject specified
                    $node_id = count($output_rows) + 1;
                    //create the subject
                    $node_name     = $subSubject;
                    $output_rows[] = [$node_id, $seenSubjects[$subject], $node_name, null, 0, null];
                    processRowsSubStrand($handle, $output_rows, $node_id, $seenCodes);
                    break;
                default:
                    // Subjects with no sub subject
                    processRowsSubStrand($handle, $output_rows, $lastSubject_id, $seenCodes);
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
        fputcsv($handle, $output_row, escape: '');
    }
    fclose($handle);
}
