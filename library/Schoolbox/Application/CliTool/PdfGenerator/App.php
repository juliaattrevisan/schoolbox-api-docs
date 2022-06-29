<?php

/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Application\CliTool\PdfGenerator;

use Alaress\Authorizer\Failure\Unauthenticated;
use Alaress\Authorizer\Failure\Unauthorized;
use GuzzleHttp\Client;
use Predis\ClientInterface as RedisClient;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use RuntimeException;
use Schoolbox\Application\CliKernelAbstract;
use Schoolbox\Application\CliTool\App as BaseApp;
use Schoolbox\Auth\Remote\RemoteLogin;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\GenericCollection;
use Schoolbox\Gotenberg\RequestFactory;
use Schoolbox\Realtime\Pipe\PipeManager;
use Schoolbox\Url\Factory;
use Schoolbox\User;
use TheCodingMachine\Gotenberg;
use Laminas\Uri\UriInterface;
use ZipArchive;

/**
 * Generic functionality for a CLI tool which can generate PDFs.
 */
abstract class App extends BaseApp
{
    // print help
    private const OPT_SHORT_HELP = 'h';
    private const OPT_LONG_HELP  = 'help';

    // Which port does this Schoolbox instance run on?
    // This is not in configuration, so must be provided
    private const OPT_LONG_PORT = 'port:';

    // Session hash
    private const OPT_LONG_HASH = 'hash:';

    // Username, if session hash is not provided
    private const OPT_LONG_USERNAME = 'username:';

    // Number of consecutive fails after which to abort the script's execution.
    private const OPT_LONG_MAX_FAILS = 'max-fails:';
    private const DEFAULT_MAX_FAILS  = 3;

    // How many milliseconds to let JS run before attempting to print page
    private const OPT_LONG_JS_DELAY = 'js-delay:';
    private const DEFAULT_JS_DELAY  = 1000;

    // user style sheet
    private const OPT_LONG_USER_STYLE_SHEET = 'user-style-sheet:';

    // print media type style sheet
    private const OPT_LONG_PRINT_MEDIA_TYPE = 'print-media-type';

    // should landscape orientation be used? If not default to portrait
    private const OPT_LONG_ORIENTATION_LANDSCAPE = 'orientation-landscape';

    // Exit status for an authentication failure
    private const EXIT_STATUS_AUTHENTICATION_FAILURE = 100;

    // Exit status for an authorization failure
    private const EXIT_STATUS_AUTHORIZATION_FAILURE = 101;

    /**
     * @inheritdoc
     */
    public function getShortOpts(): array
    {
        return [
            self::OPT_SHORT_HELP,
        ];
    }


    /**
     * @inheritdoc
     */
    public function getLongOpts(): array
    {
        return [
            self::OPT_LONG_HELP,
            self::OPT_LONG_HASH,
            self::OPT_LONG_PORT,
            self::OPT_LONG_USERNAME,
            self::OPT_LONG_MAX_FAILS,
            self::OPT_LONG_JS_DELAY,
            self::OPT_LONG_USER_STYLE_SHEET,
            self::OPT_LONG_PRINT_MEDIA_TYPE,
            self::OPT_LONG_ORIENTATION_LANDSCAPE,
        ];
    }


    /**
     * @inheritdoc
     */
    public function run(): void
    {
        // Did user request help?
        if ($this->getOpt(self::OPT_SHORT_HELP) || $this->getOpt(self::OPT_LONG_HELP)) {
            $this->printHelp();
            exit(CliKernelAbstract::EXIT_STATUS_OK);
        }


        // Set up required base information:
        // * base URL for requests
        // * temp dir for PDFs
        $baseUrl = $this->generateBaseUrl();
        $tempDir = $this->createTempDir();


        // If no session hash was provided:
        // attempt to log in, and use that session's hash in the PDF generator
        $sessionHash = $this->getOpt(self::OPT_LONG_HASH);
        if (!$sessionHash) {
            $sessionHash = $this->generateSessionHashViaLoginForm($this->getUsername(), $baseUrl);
        }


        // Authorize the user identified by the session hash
        $authenticatedUser = $this->determineAuthenticatedUserFromSessionHash($sessionHash);
        try {
            $this->authorizeUser($authenticatedUser);
        } catch (Unauthenticated $e) {
            $this->getLogger()->error($e->getMessage());
            exit(self::EXIT_STATUS_AUTHENTICATION_FAILURE);
        } catch (Unauthorized $e) {
            $this->getLogger()->error($e->getMessage());
            exit(self::EXIT_STATUS_AUTHORIZATION_FAILURE);
        }


        // Build a PDF generator object
        /** @var Gotenberg\Client $pdfGenerator */
        $pdfGenerator = $this->getDi()->get('pdfGenerator');


        // Generate all PDFs
        $pdfFilenames        = [];
        $consecutiveFailures = 0;
        $maxFailures         = $this->getOptAsInt(self::OPT_LONG_MAX_FAILS) ?? self::DEFAULT_MAX_FAILS;
        foreach ($this->generatePdfSources($baseUrl) as $pdfSource) {
            if (!$pdfSource instanceof SourceBundle) {
                $this->getLogger()->warning(
                    sprintf(
                        '`generatePdfSources` must return an iterable which contains instances of `%s`: skipping',
                        SourceBundle::class
                    )
                );
                continue;
            }
            try {
                $this->getLogger()->notice($pdfSource->getMessage());
                $pdfFilenames[]      = $this->generatePdf($pdfSource, $tempDir, $pdfGenerator, $sessionHash);
                $consecutiveFailures = 0;
            } catch (RuntimeException $e) {
                // Something went wrong: log it
                // abort if there's been enough consecutive failures
                $this->getLogger()->warning($e->getMessage());
                if ($consecutiveFailures++ >= $maxFailures) {
                    $this->getLogger()->error("$consecutiveFailures consecutive failures, giving up...");
                    break;
                }
                continue;
            }
        }

        // Nothing generated: exit early
        if (count($pdfFilenames) === 0) {
            $this->getLogger()->notice('No PDFs generated: not attempting to create archive');
            return;
        }

        // Done generating PDFs: zip them up and clean up
        $this->createZipArchive($pdfFilenames, $tempDir);
        $this->cleanUpTempFiles($pdfFilenames, $tempDir);
    }


    /**
     * Generates a help message for the base options which apply to all CLI PDF
     * generation apps.
     *
     * @return string
     */
    protected function generateHelpMessageForBaseOptions(): string
    {
        $defaultMaxFails        = self::DEFAULT_MAX_FAILS;
        $defaultJsDelay         = self::DEFAULT_JS_DELAY;
        $white                  = self::COLOUR_WHITE;
        $yellow                 = self::COLOUR_YELLOW;

        return <<<BASE
    $yellow-h, --help$white
        Print this help and exit

    $yellow-o=OUT_FILE$white
        Filename to write archive to, e.g. '-o=output.zip'
        Defaults to STDOUT (1>).

    $yellow-l=LOG_FILE$white
        Filename to append logs to, e.g. '-l=error.log'
        Defaults to STDERR (2>).

    $yellow-v[v[v]]$white
        Log verbosity. Max 3 levels, e.g. -vvv

    $yellow--port=PORT$white
        The port the Schoolbox instance to generate PDFs from is running on.
        Defaults to empty (thus the standard HTTPS port).

    $yellow--hash=HASH$white
        A valid session hash. If provided, this tool will use this session hash,
        and thus run within the context of the user who has authenticated in
        that session.
        This option overrides $yellow--username$white.

    $yellow--username=USERNAME$white
        A username to attempt to authenticate.
        If provided and $yellow--hash$white is not, this tool will prompt for the user's
        password; if the user is successfully authenticated, then this tool will
        run within the context of that user.
        If neither this nor $yellow--hash$white is provided: this tool will prompt for a
        username, and then a password.

    $yellow--max-fails=COUNT$white
        If the tool fails for this many students in a row, exit immediately.
        Defaults to $defaultMaxFails.

    $yellow--js-delay=COUNT$white
        Number of milliseconds to wait before printing a page to allow
        javascript to execute.
        Defaults to $defaultJsDelay.

    $yellow--user-style-sheet=STYLE_FILE$white
        Filename of a style sheet to load with every page.
        This option is not currently supported: use custom print styles within
        Schoolbox instead.

    $yellow--print-media-type$white
        If set, generate PDFs using the print media type and its styles;
        otherwise, PDFs will be generated using the screen media type.
        This option is not currently supported: PDFs will always be generated
        with the print media type.

    $yellow--orientation-landscape$white
        If set, generate PDFs in landscape orientation: by default, they will be
        generated in portrait orientation.
BASE;
    }


    /**
     * Return a username, either from command line options, or by prompting the
     * user.
     *
     * @return string
     */
    private function getUsername(): string
    {
        // Revert to username and password
        $username = $this->getOpt(self::OPT_LONG_USERNAME);
        if (isset($username)) {
            $this->getLogger()->debug('Using username provided on command line');
            return $username;
        }

        while (true) {
            $this->getLogger()->debug("Requesting username");
            $username = trim($this->readLine('Please provide a schoolbox username: '));
            // TODO use a regex
            if ($username) {
                $this->getLogger()->debug("Username set to '$username'");
                break;
            }
            $this->getLogger()->warning("Username '$username' appears invalid");
        }
        return $username;
    }


    /**
     * Generate the base URL for this Schoolbox instance.
     *
     * Login requests will be made to this Schoolbox instance, as will any
     * page requests generated by `generatePdfSources`.
     *
     * @return UriInterface
     */
    private function generateBaseUrl(): UriInterface
    {
        $port    = $this->getOptAsInt(self::OPT_LONG_PORT);
        $baseUrl = (new Factory($this->getConfig()))
            ->buildFullyQualified()
            ->setScheme('https');
        if (isset($port)) {
            $baseUrl = $baseUrl->setPort($port);
        }
        return $baseUrl;
    }


    /**
     * Attempt to login via the web form, and return the generated session hash.
     *
     * @param string       $username
     * @param UriInterface $baseUrl
     * @return string
     */
    private function generateSessionHashViaLoginForm(string $username, UriInterface $baseUrl): string
    {
        $attempts    = 0;
        $maxAttempts = 3;
        $remoteLogin = new RemoteLogin(new Client(), $baseUrl);
        while (true) {
            $attempts++;
            if ($attempts > $maxAttempts) {
                $this->getLogger()->warning("Login failed");
                exit(self::EXIT_STATUS_AUTHENTICATION_FAILURE);
            }
            $remaining = $maxAttempts - $attempts + 1;
            $this->getLogger()->debug("Requesting password, ($remaining attempts remaining)");
            $password = $this->readPassword("Please provide your password ($remaining attempts remaining): ");
            // TODO use regex
            if ($password) {
                $this->getLogger()->info("Password provided, attempting login");
                $cookie = $remoteLogin->authenticate($username, $password);
                if (isset($cookie)) {
                    return $cookie;
                }
            }
            $this->getLogger()->warning("Password appears invalid");
        }
    }


    /**
     * Determine which user is logged in given a session hash.
     *
     * Note that this implementation is highly specific to the Redis session
     * handler, and should the session handler ever change this will cease to
     * function.
     *
     * @param string|null $hash
     * @return User|null
     */
    private function determineAuthenticatedUserFromSessionHash(?string $hash): ?User
    {
        // Don't try anything if there is no session hash
        if (!isset($hash)) {
            return null;
        }


        // Find the redis client
        try {
            $redis = $this->getDi()->get('redis.storage');
        } catch (NotFoundExceptionInterface | ContainerExceptionInterface $e) {
            $redis = null;
        }
        if (!$redis instanceof RedisClient) {
            $this->getLogger()->alert("Unable to fetch redis client");
            return null;
        }


        // Find the session data within Redis
        $redisData = $redis->get(sprintf('%s%s', PipeManager::REDIS_SESSION_PREFIX, $hash));
        if (null === $redisData) {
            $this->getLogger()->alert("No redis data found for hash: {$hash}");
            return null;
        }


        // Decode the session data.
        // Unfortunately a session must be started in order to do this:
        // abort the session immediately after we are done
        session_start();
        session_decode($redisData);
        /** @var User $user */
        $user     = $_SESSION['user'] ?? null;
        $lastTime = $_SESSION['lasttime'] ?? null;
        session_unset();
        session_abort();


        // Return the user, if their session has not expired
        if (!$user) {
            $this->getLogger()->alert("No user object found in session data");
            return null;
        }
        if (!$lastTime || time() - $lastTime > $this->getConfig()->get('session_length')) {
            $this->getLogger()->alert("Session has expired");
            return null;
        }
        return $user;
    }


    /**
     * Authorizes the user `determineAuthenticatedUserFromSessionHash` fetched,
     * if any.
     *
     * This default implementation does not authorize the user at all, nor even
     * verify that they are authenticated: subclasses may choose to do either.
     *
     * @param User|null $user
     * @throws Unauthenticated if user was not able to be authenticated at all,
     *                         and that is not acceptable to this implementation
     * @throws Unauthorized    if user is not authorized to perform the action
     *                         requested in this implementation
     */
    protected function authorizeUser(?User $user): void
    {
    }


    /**
     * Create a temp directory to store files and return its name
     *
     * @return string
     * @throws RuntimeException If unable to create the temp directory
     */
    private function createTempDir(): string
    {
        $tempDir = $this->getConfig()->get('zip_dir') . '/' . time();
        if (!mkdir($tempDir) && !is_dir($tempDir)) {
            throw new RuntimeException(sprintf('Directory "%s" was not created', $tempDir));
        }
        return $tempDir;
    }


    /**
     * Generates a PDF for this source.
     *
     * @param SourceBundle                       $pdfSource
     * @param string                             $tempDir
     * @param Gotenberg\GotenbergClientInterface $pdfGenerator
     * @param string                             $sessionHash
     * @return string
     */
    private function generatePdf(
        SourceBundle $pdfSource,
        string $tempDir,
        Gotenberg\GotenbergClientInterface $pdfGenerator,
        string $sessionHash
    ): string {
        $filename          = $tempDir . '/' . $pdfSource->getFilename();
        $maxAttempts       = 10;
        $attempts          = 0;
        $previousException = null;
        $jsDelay           = $this->getOptAsInt(self::OPT_LONG_JS_DELAY) ?? self::DEFAULT_JS_DELAY;
        $requestFactory    = new RequestFactory($this->getConfig()->get(ConfigMapper::NAME_GOTENBERG_API_KEY));
        while (true) {
            if ($attempts++ >= $maxAttempts) {
                unlink($filename);
                throw new RuntimeException("Unable to generate PDF after $maxAttempts attempts, skipping", 0, $previousException);
            }
            try {
                $this->getLogger()->debug(
                    "Generating PDF from the following URLs:\n* " . implode("\n* ", $pdfSource->getUrls())
                );

                // Gotenberg needs to download each URL individually:
                // merge appears to not work with URLs.
                // Just generate a PDF for each URL, then merge them.
                /** @var Gotenberg\Document[] $files */
                $files = [];
                foreach ($pdfSource->getUrls() as $i => $url) {
                    $request = $requestFactory->buildUrlRequest(
                        $url,
                        RequestFactory::DEFAULT_WAIT_DELAY,
                        RequestFactory::DEFAULT_WAIT_TIMEOUT,
                        $sessionHash
                    );
                    $request->setLandscape((bool)$this->getOpt(self::OPT_LONG_ORIENTATION_LANDSCAPE));

                    // Gotenberg doesn't seem to care about the response code.
                    // Just have to hope that no requests are made with an
                    // invalid URL.
                    $pdfGenerator->store($request, $tempDir . "/$i.pdf");
                    $files[] = Gotenberg\DocumentFactory::makeFromPath("$i.pdf", $tempDir . "/$i.pdf");
                }

                // Merge the intermediary PDFs together, then delete them
                $request = new Gotenberg\MergeRequest($files);
                $request->addWebhookURLHTTPHeader(
                    RequestFactory::AUTH_HTTP_HEADER,
                    $this->getConfig()->get(ConfigMapper::NAME_GOTENBERG_API_KEY),
                );
                $pdfGenerator->store($request, $filename);
                foreach ($files as $file) {
                    unlink($tempDir . '/' . $file->getFileName());
                }
                break;
            } catch (RuntimeException $e) {
                $this->getLogger()->warning($e->getMessage());
                $this->getLogger()->warning($e->getTraceAsString());

                // Extend the JS delay, as that might prevent rendering
                $jsDelay           *= 2;
                $previousException = $e;
            }
        }
        return $filename;
    }


    /**
     * Create a single ZIP archive of all the PDF documents.
     *
     * @param string[] $pdfFilenames
     * @param string   $tempDir
     */
    private function createZipArchive(array $pdfFilenames, string $tempDir): void
    {
        $zipFilename = tempnam($tempDir, 'zip');

        /* https://stackoverflow.com/questions/32694148/ziparchivegetstatusstring-invalid-or-uninitialized-zip-object
         * Also, SA-10419
         *
         * ZipArchive doesn't expect a file to exist when it's creating its zip
         * archive: therefore, the file should be deleted
         */
        @unlink($zipFilename);

        $zipArchive = new ZipArchive();
        $this->getLogger()->debug("Creating temp zip archive: $zipFilename");
        $zipArchive->open($zipFilename, ZipArchive::CREATE);

        // To avoid exceeding open file limit, may need to batch zip processing.
        // Buffer of 20, round to nearest 100
        $openFileLimit = (int)exec('ulimit -n');
        $batchSize     = round($openFileLimit - 20, -2);
        $this->getLogger()->debug(sprintf('Open file limit: %d, setting batch size to %d', $openFileLimit, $batchSize));

        // Write each batch to the zip
        $batchIndex = 1;
        $batches    = ceil(count($pdfFilenames) / $batchSize);
        foreach ($pdfFilenames as $pdfFilename) {
            if ($batchIndex++ % $batchSize === 0) {
                $batchCount = (int)($batchIndex / $batchSize);
                $this->getLogger()->info("Writing zip batch $batchCount/$batches...");
                $zipArchive->close();
                $zipArchive->open($zipFilename);
            }
            $this->getLogger()->debug("Adding PDF file to zip archive: $pdfFilename");
            $zipArchive->addFile($pdfFilename, basename($pdfFilename));
        }
        $this->getLogger()->info("Writing zip batch $batches/$batches...");
        $zipArchive->close();

        // Send this zip to this tool's output stream, and delete the zip
        $this->getLogger()->debug("Writing zip archive to output stream");
        if (false === stream_copy_to_stream(fopen($zipFilename, 'rb'), $this->getOutStream())) {
            $this->getLogger()->error('Unable to write zip archive to output stream');
        }
        $this->getLogger()->debug("Deleting zip archive: $zipFilename");
        unlink($zipFilename);
    }


    /**
     * Explicitly deletes any temporary files this tool created.
     *
     * @param array  $pdfFilenames
     * @param string $tempDir
     */
    private function cleanUpTempFiles(array $pdfFilenames, string $tempDir): void
    {
        $this->getLogger()->debug("Cleaning up temporary files");
        foreach ($pdfFilenames as $pdfFilename) {
            $this->getLogger()->debug("Deleting $pdfFilename");
            unlink($pdfFilename);
        }

        $this->getLogger()->debug("Deleting temp directory $tempDir");
        rmdir($tempDir);
    }


    /**
     * Prints a help message, always to STDOUT, regardless of Kernel's output
     * file option.
     */
    abstract protected function printHelp(): void;


    /**
     * Generate the list of PDFs to generate.
     *
     * @param UriInterface $baseUrl
     * @return GenericCollection|SourceBundle[]
     */
    abstract protected function generatePdfSources(UriInterface $baseUrl): GenericCollection;
}
