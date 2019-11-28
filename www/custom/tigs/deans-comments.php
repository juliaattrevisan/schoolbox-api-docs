<?php /** @noinspection PhpUnhandledExceptionInspection */
/**
 * This is a temporary hack mechanism by which to insert dean's comments until the feature is built properly.
 *
 * It provides a HTML form where user can upload CSV of pastoral comments.
 * It stores the results of the run in the session, and redirects to this page, which will render those results, and remove from session.
 *
 * This page is therefore rendered for multiple cases:
 * 1. GET to display the info, form and most recent results, and clear those results if they exist
 * 2. POST to process the CSV, generate results, and store them in the session
 */
declare(strict_types=1);

use Alaress\Database;
use Aura\Di\Exception\ServiceNotFound;
use Psr\Log\AbstractLogger;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Schoolbox\Application\Exception\AccessDenied;
use Schoolbox\Application\Exception\NotAuthenticated;
use Schoolbox\Application\Exception\NotFound;
use Schoolbox\Application\WebRequest;
use Schoolbox\UserMapper;

require_once __DIR__ . '/../../../conf/core/require.php';
$kernel = new WebRequest\Kernel();

const YEAR_LEVEL_MIN = 7;
const YEAR_LEVEL_MAX = 12;
const YEAR_MIN       = 2018;
const YEAR_MAX       = 2030;
const SESSION_KEY    = 'tigsDeansCommentsInsert';

// Main flow
authenticateUser($_SESSION);
if (isPost($_POST)) {
    processPost($kernel, $_POST);
} else {
    renderPage($_GET['yearLevel'] ?? '', $_GET['year'] ?? '', $_GET['semester'] ?? '', $_SESSION[SESSION_KEY] ?? []);
}

// Helpers
/**
 * Authenticates the session user can access this page (must be SU)
 *
 * @param array $session
 * @throws AccessDenied
 * @throws NotAuthenticated
 * @throws NotFound
 */
function authenticateUser(array $session): void
{
    /** @var \Schoolbox\User $user */
    $user = $session['user'] ?? null;
    if (!$user) {
        throw new NotAuthenticated();
    }
    if (!$user || !$user->isSuperuser()) {
        throw new AccessDenied();
    }

    if(Cfg::get('site_location') !== 'tigs'){
        throw new NotFound();
    }
}

/**
 * Returns whether we are processing a post from this page, as opposed to rendering the page
 *
 * @param array $post
 * @return bool
 */
function isPost(array $post): bool
{
    return (bool)count($post);
}

/**
 * Process a form submission
 *
 * @param WebRequest\Kernel $kernel
 * @param array             $post
 * @throws InvalidArgumentException
 */
function processPost(WebRequest\Kernel $kernel, array $post): void
{
    if (array_key_exists('dryRun', $post)) {
        // Dry Run
        processDryRun($kernel, (int)$post['yearLevel'], (int)$post['year'], (int)$post['semester'], $_FILES['comments']);
    } elseif (array_key_exists('liveRun', $post)) {
        // Live Run
        processLiveRun($kernel);
    } else {
        // Start Over
        unset($_SESSION[SESSION_KEY]);
        redirectToPage('', '', '');
    }
}

/**
 * Processes the dry run form.
 *
 * If errors, redirect to display form again.
 * Else, store data in session, redirect to show 'Live Run' + 'Start Over' buttons
 *
 * @param WebRequest\Kernel $kernel
 * @param int               $yearLevel
 * @param int               $year
 * @param int               $semester
 * @param array             $file
 * @throws InvalidArgumentException
 */
function processDryRun(WebRequest\Kernel $kernel, int $yearLevel, int $year, int $semester, array $file)
{
    $fp      = fopen($file['tmp_name'], 'rb');
    $csvData = [];
    while ($row = fgetcsv($fp)) {
        $csvData[] = $row;
    }

    $logger = getLogger();

    validateYearLevelYearSemester($logger, $yearLevel, $year, $semester);

    $date = getPastoralRecordCreateDate($logger, $yearLevel, $year, $semester);

    if ($date) {
        processCsvData($kernel, false, $logger, $yearLevel, $year, $semester, $csvData, $date);
    } else {
        saveSessionData(true, $yearLevel, $year, $semester, $csvData, $logger->getResults());
    }
}

/**
 * Process CSV data, optionally persisting to database
 *
 * @param WebRequest\Kernel   $kernel
 * @param bool                $perform
 * @param LoggerInterface     $logger
 * @param int                 $yearLevel
 * @param int                 $year
 * @param int                 $semester
 * @param array               $csvData
 * @param Alaress_Date_Format $date
 * @throws Database\Exception
 * @throws RuntimeException
 * @throws ServiceNotFound
 * @throws \Schoolbox\Mapper\Exception
 */
function processCsvData(
    WebRequest\Kernel $kernel,
    bool $perform,
    LoggerInterface $logger,
    int $yearLevel,
    int $year,
    int $semester,
    array $csvData,
    Alaress_Date_Format $date
): void {

    $logger->warning($perform ? "Running in PERFORM mode" : "Running in DRY RUN mode");
    $logger->info("Pastoral record Create/Update date: " . $date->getDatabase());

    /** @var Database\Connector\IConnector $db */
    $db         = $kernel->get('databaseInternal');
    $userMapper = UserMapper::getInstance();

    $insertStatement = $db->prepareQuery("
        INSERT INTO pastoral (
            owner_id, 
            student_id, 
            type_id, 
            heading, 
            body_public, 
            modified_by_user_id, 
            created_at, 
            updated_at
        ) VALUES (
            :owner_id,
            :student_id,
            (SELECT value FROM config WHERE name = 'tigs_crp_pastoral_type'),
            :heading,
            :body_public,
            :modified_by_user_id,
            :created_at,
            :updated_at
        )
    ");

    foreach ($csvData as $rowId => $row) {
        if (0 === $rowId) {
            // Ignore headers
            continue;
        }
        [$deanId, $studentId, $surname, $preferred, $comment] = $row;
        $dean    = $userMapper->findByExternalId($deanId);
        $student = $userMapper->findByExternalId($studentId);

        // Sanity check
        if ($student->getLastname() !== $surname) {
            $logger->warning("Lastname: {$student->getLastname()} !== Surname: {$surname}");
        } else {
            $logger->debug("Lastname: {$student->getLastname()} === Surname: {$surname}");
        }
        if ($student->getFirstname() !== $preferred) {
            $logger->warning("Lastname: {$student->getFirstname()} !== Surname: {$preferred}");
        } else {
            $logger->debug("Lastname: {$student->getFirstname()} === Surname: {$preferred}");
        }

        $heading = "Year $yearLevel $year Semester $semester Dean's Comment";
        $logger->info("Inserting comment for student {$student->getId()} '$heading'");
        if ($perform) { // TODO remove
            $insertStatement->execute([
                'owner_id'            => $dean->getId(),
                'student_id'          => $student->getId(),
                'heading'             => $heading,
                'body_public'         => $comment,
                'modified_by_user_id' => $dean->getId(),
                'created_at'          => $date->getDatabase(),
                'updated_at'          => $date->getDatabase(),
            ]);
        }
    }

    saveSessionData($logger->hasErrors(), $yearLevel, $year, $semester, $perform ? [] : $csvData, $logger->getResults());
    redirectToPage((string)$yearLevel, (string)$year, (string)$semester);
}


function saveSessionData(bool $hasErrors, int $yearLevel, int $year, int $semester, array $csvData, string $logResults): void
{
    $_SESSION[SESSION_KEY] = [
        'hasErrors'  => $hasErrors,
        'yearLevel'  => $yearLevel,
        'year'       => $year,
        'semester'   => $semester,
        'csvData'    => $csvData,
        'logResults' => $logResults,
    ];
}

function redirectToPage(string $yearLevel, string $year, string $semester): void
{
    $query = [
        'yearLevel' => $yearLevel,
        'year'      => $year,
        'semester'  => $semester,
    ];

    $query = http_build_query(array_filter($query));

    http_response_code(303);
    header("Location: /custom/tigs/deans-comments.php" . ($query ? "?$query" : ''));
    exit;
}

/**
 * Returns the date we will use as the 'created_at' date for the pastoral records
 *
 * @param LoggerInterface $logger
 * @param int             $yearLevel
 * @param int             $year
 * @param int             $semester
 * @return Alaress_Date_Format|null
 * @throws InvalidArgumentException
 */
function getPastoralRecordCreateDate(LoggerInterface $logger, int $yearLevel, int $year, int $semester): ?Alaress_Date_Format
{
    $monthAndDay = null;
    switch ($yearLevel) {
        case 7:
        case 8:
        case 9:
        case 10:
            $monthAndDay = $semester === 1 ? '06-12' : '12-12';  // Jun 12th : Dec 12th
            break;
        case 11:
            $monthAndDay = $semester === 1 ? '06-12' : '09-12';  // Jun 12th : Sep 12th
            break;
        case 12:
            $monthAndDay = $semester === 1 ? '03-25' : '09-12';  // Mar 25th : Sep 12th
            break;
        default:
            $logger->error("Unknown year level: $yearLevel");

    }
    $logger->info("Month and Day: $monthAndDay");

    return $monthAndDay
        ? new Alaress_Date_Format("$year-$monthAndDay", Alaress_Date_Format::DATABASE_DATE_FORMAT)
        : null;
}

/**
 * Validates yearLevel, year, semester
 *
 * @param LoggerInterface $logger
 * @param int             $yearLevel
 * @param int             $year
 * @param int             $semester
 */
function validateYearLevelYearSemester(LoggerInterface $logger, int $yearLevel, int $year, int $semester): void
{
    // Sanity check
    $logger->info("YearLevel: $yearLevel");
    if ($yearLevel < YEAR_LEVEL_MIN || $yearLevel > YEAR_LEVEL_MAX) {
        $logger->error(sprintf("YearLevel must be from %d to %d, found '$yearLevel'", YEAR_LEVEL_MIN, YEAR_LEVEL_MAX));
    }
    $logger->info("Year: $year");
    if ($year < YEAR_MIN || $year > YEAR_MAX) {
        $logger->error(sprintf("Year must be from %d to %d, found '$year'", YEAR_MIN, YEAR_MAX));
    }
    $logger->info("Semester: $semester");
    if (!in_array($semester, [1, 2], false)) {
        $logger->error("Semester must be 1 or 2, found '$semester'");
    }

}

function processLiveRun(WebRequest\Kernel $kernel)
{
    $session = $_SESSION[SESSION_KEY];
    $logger  = getLogger();
    $date    = getPastoralRecordCreateDate($logger, $session['yearLevel'], $session['year'], $session['semester']);
    processCsvData($kernel, true, $logger, $session['yearLevel'], $session['year'], $session['semester'], $session['csvData'], $date);
}

/**
 * Builds and returns a logger
 *
 * @return LoggerInterface
 */
function getLogger(): LoggerInterface
{
    return new class extends AbstractLogger
    {
        private $results   = '';
        private $hasErrors = false;

        public function log($level, $message, array $context = []): void
        {
            $colour        = $this->getColour($level);
            $this->results .= "<span style='color: $colour'>$message</span><br>\n";
        }

        public function getResults(): string
        {
            return $this->results;
        }

        public function hasErrors(): bool
        {
            return $this->hasErrors;
        }

        private function getColour(string $level): string
        {
            switch ($level) {
                case LogLevel::EMERGENCY;
                case LogLevel::ALERT;
                case LogLevel::CRITICAL;
                case LogLevel::ERROR;
                    $this->hasErrors = true;
                    return 'red';
                case LogLevel::WARNING;
                    return 'orange';
                case LogLevel::NOTICE;
                    return 'cyan';
                case LogLevel::INFO;
                default;
                    return 'black';
                case LogLevel::DEBUG;
                    return 'blue';
            }
        }
    };
}

/**
 * Renders the upload form
 *
 * @param string $yearLevel
 * @param string $year
 * @param string $semester
 * @param array  $sessionData
 */
function renderPage(string $yearLevel, string $year, string $semester, array $sessionData): void
{
    renderHeader();

    $hasErrors  = $sessionData['hasErrors'] ?? false;
    $csvData    = $sessionData['csvData'] ?? null;
    $logResults = $sessionData['logResults'] ?? '';

    if (!$logResults) {
        renderInstructions();
    }

    if ($hasErrors || (!$logResults && !$csvData)) {
        renderDryRunForm($yearLevel, $year, $semester);
    }


    if ($logResults) {
        renderLogResults($logResults);
    }

    if (!$hasErrors && $csvData) {
        renderLiveRunForm();
    }
    if ($logResults) {
        renderStartOver();
    }


    renderFooter();
}

function renderDryRunForm(string $yearLevel, string $year, string $semester): void
{
    ?>
    <form method="post" enctype="multipart/form-data" action="./deans-comments.php">
        <table style="border: 1px solid black;">
            <tr>
                <td>Year Level:</td>
                <td><input type="number" name="yearLevel" title="Year Level" value="<?php echo $yearLevel; ?>"
                           min="<?php echo YEAR_LEVEL_MIN; ?>" max="<?php echo YEAR_LEVEL_MAX; ?>" required></td>
            </tr>
            <tr>
                <td>Year:</td>
                <td><input type="number" name="year" title="Year" value="<?php echo $year; ?>"
                           min="<?php echo YEAR_MIN; ?>" max="<?php echo YEAR_MAX; ?>" required></td>
            </tr>
            <tr>
                <td>Semester:</td>
                <td><input type="number" name="semester" title="Semester" value="<?php echo $semester; ?>" min="1"
                           max="2" required></td>
            </tr>
            <tr>
                <td>Deans Comments File:</td>
                <td><input type="file" name="comments" title="Deans Comments CSV" accept="text/csv" required></td>
            </tr>
            <tr>
                <td colspan="2"><input type="submit" name="dryRun" value="Dry Run"></td>
            </tr>
        </table>
    </form>
    <?php
}

/**
 * Renders "Live Run" and "Start Over" buttons
 */
function renderLiveRunForm(): void
{
    ?>
    <form method="post" enctype="multipart/form-data" action="./deans-comments.php">
        <table style="border: 1px solid black;">
            <tr>
                <td colspan="2"><input type="submit" name="liveRun" value="Live Run"></td>
            </tr>
        </table>
    </form>
    <?php
}

/**
 * Renders "Live Run" and "Start Over" buttons
 */
function renderStartOver(): void
{
    ?>
    <form method="post" enctype="multipart/form-data" action="./deans-comments.php">
        <table style="border: 1px solid black;">
            <tr>
                <td colspan="2"><input type="submit" name="startOver" value="Start Over"></td>
            </tr>
        </table>
    </form>
    <?php
}

/**
 * Renders log results
 *
 * @param string $logResults
 */
function renderLogResults(string $logResults): void
{
    ?>
    <div style="border: 1px solid black;">
        <h3>Log Results:</h3>
        <?php echo $logResults ?>
    </div>
    <?php
}


/**
 * Renders page header HTML
 */
function renderHeader(): void
{ ?>
    <html lang="en">
    <head>
        <title>TIGS Deans Comments Import tool</title>
    </head>
    <body>
    <h2>TIGS Deans Comments Import tool</h2>
    <p>This is a temporary hack to import deans comments until the feature is properly built.<br>
        It is intended for use by Schoolbox Support agents</p>

<?php }


/**
 * Renders page instructions
 */
function renderInstructions(): void
{ ?>
    <ol>
        <li>Dry Run</li>
        <ol>
            <li>Attach client-provided CSV of comments</li>
            <li>Click DryRun</li>
            <li>Review dry-run results to ensure they make sense</li>
            <li>Only continue to 'Live Run' if dry run results look ok</li>
        </ol>
        <li>Live Run</li>
        <ol>
            <li>Ensure 'Perform' checkbox IS checked</li>
            <li>Attach client-provided CSV of comments</li>
            <li>Click LiveRun</li>
        </ol>
    </ol>
<?php }


/**
 * Renders page footer HTML
 */
function renderFooter(): void
{ ?>
    </body>
    </html>
<?php }


