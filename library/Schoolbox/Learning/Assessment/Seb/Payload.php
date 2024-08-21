<?php

/**
 * @copyright   Copyright 2021, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Assessment\Seb;

use Aura\Router\Generator;
use CFPropertyList\CFData;
use CFPropertyList\CFDate;
use CFPropertyList\CFDictionary;
use CFPropertyList\CFPropertyList;
use CFPropertyList\CFString;
use Schoolbox\IConfigLoader;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\User;

/**
 * Generate an xml payload for the given IAssessable
 *
 * The xml is manufactured via config.xml, which I lovingly handcrafted and manually
 * verified by reverse engineering the way SEB was built.
 *
 * The site_location value is used as part of a salt to make repeatable hashes
 * though I suspect this is a superficial gesture overall
 *
 * Usage;
 *
    new Payload(
        $this->getConfigLoader()->get('site_location'),
        $this->request->getServerParams('HTTP_HOST'),
        $router->generate('learningAssessment.getAssessment', [
            'assessmentId' => $assessment->getId()
        ]
    );
 */
class Payload
{
    /**
     * Browser Exam Key and Config Key are hashed with SHA256
     */
    public const HASH_ALGORITHM = 'sha256';


    /**
     * Load this inaccessible file and swop out its vars, phplib style
     */
    private const PATH = __DIR__ . '/config.xml';


    /**
     * Some arbitrary string used as the ExamKeySalt, though I don't know if
     * that actually does anything in SEB
     */
    private const EXAM_KEY_SALT = '$1$VMcFkEdF$.8q4GPiJ8MoA4zjOOfca11';


    /**
     * @var string
     */
    private string $saltBasis;


    /**
     * @var string
     */
    private string $host;


    /**
     * @var Generator
     */
    private Generator $routeGenerator;

    /**
     * @var IConfigLoader
     */
    private IConfigLoader $config;

    /**
     * A random 4 character unicode string to replace backslashes during json_encode.
     */
    private const BACKSLASH_SUBSTITUTE = "ؼҷҍԴ";

    /**
     * request parameter name to pass onetime key
     */
    public const SEB_KEY = 'sebKey';

    /**
     * Constructor.
     *
     * @param IConfigLoader $config
     * @param string        $host
     * @param Generator     $routeGenerator
     */
    public function __construct(IConfigLoader $config, string $host, Generator $routeGenerator)
    {
        $this->saltBasis = (string)$config->get('site_school_code');
        $this->host = $host;
        $this->routeGenerator = $routeGenerator;
        $this->config = $config;
    }


    /**
     * Generate a seb config, ready to go, for the assessment
     *
     * @param IAssessable $assessment
     * @param User        $user
     * @return string
     * @throws \Aura\Router\Exception\RouteNotFound
     */
    public function generate(IAssessable $assessment, User $user): string
    {
        /* The domain of our schoolbox instance. We need this so students can get past the
         * login screen since we can establish a filtered url https://domain/login*
         */
        $domain = 'https://' . $this->host;

        /* Figure out the absolute url for this assessment and all the subpages
         * such as https://domain/learning/assessment/123*
         *
         * FIXME If you want to lock down non-aura pages further, you'll need to change
         *  "Router $router" to "string $route" instead, and have the aura pages generate
         *  the $route and pass it instead of doing it internally
         */
        $assessmentUrl = $domain . $this->routeGenerator->generate('learningAssessment.getAssessment', [
                'assessmentId' => $assessment->getId()
            ]);
        $startUrl = $domain . $this->routeGenerator->generate('learningAssessment.getSummaryViaSeb', [
            'assessmentId' => $assessment->getId(),
            'studentId' => $user->getId()
        ]);

        // Allow access to quiz start URL, and in addition any URLs underneath
        // (learnosity.getCompleteAttempt is underneath)
        $quizStartUrl = $domain . $this->routeGenerator->generate(
            'learnosity.getAttempt',
            [
                'assessmentId' => $assessment->getId(),
                'studentId'    => $user->getId(),
            ]
        );

        $exitUrl = $domain . '/homepage/' . $assessment->getContext()->getFolder()->getId();

        /* Decide on a salt; I presume it should be derived from something unique to each
         * schoolbox instance, rather than a global salt; perhaps site_location or suchlike
         */
        $examKeySalt = $this->generateExamKeySalt($this->saltBasis);

        /* List of allowed urls
         */
        $whitelistUrls = [
            "{$domain}/login*",
            "{$assessmentUrl}*",
            "{$startUrl}*",
            "{$quizStartUrl}*",
            "{$domain}/storage/fetch.php*",
            "{$domain}/storage/image.php*",
            "{$domain}/cms/sendVideo.php*",
            /* We married learnosity and need to allow all their stuff
             */
            "https://*.learnosity.com/*",
        ];

        if ($this->config->get('saml_enabled')) {
            $whitelistUrls[] = "{$domain}/saml*";
            if (trim($this->config->get('saml_sso_url')) !== '') {
                $urlSso          = parse_url(trim($this->config->get('saml_sso_url')));
                $whitelistUrls[] = "{$urlSso['scheme']}://{$urlSso['host']}/*";
            }
            if (trim($this->config->get('saml_idp_metadata_url')) !== '') {
                $urlIdp          = parse_url(trim($this->config->get('saml_idp_metadata_url')));
                $whitelistUrls[] = "{$urlIdp['scheme']}://{$urlIdp['host']}/*";
            }
        }

        if ($this->config->get('seb_whitelist')) {
            $whitelistUrls = array_merge(
                $whitelistUrls,
                array_map('trim', preg_split('/\R/', $this->config->get('seb_whitelist'))) // Explode on \R to include all types of newline chars.
            );
        }

        /* Manufacture a phplib style struct for strings and their replacements
         */
        $tokens = [
            '{{ startUrl }}'    => $startUrl,
            '{{ exitUrl }}'     => $exitUrl,
            '{{ examKeySalt }}' => $examKeySalt,
            '{{ whiteList }}'   => $this->generateWhiteList($whitelistUrls),
        ];

        /* Perform the replacements
         */
        return strtr(file_get_contents(self::PATH), $tokens);
    }


    /**
     * Generate an instance-specific salt for the xml
     *
     * I'm not even sure this does anything meaningful other than being a piece
     * of content in the xml that's sensitive to change and fiddling
     *
     * @param string $saltBasis
     * @return string
     */
    private function generateExamKeySalt(string $saltBasis): string
    {
        return hash(self::HASH_ALGORITHM, $saltBasis . self::EXAM_KEY_SALT);
    }


    /**
     * Generate hash of the dynamic xml for this assessment
     *
     * @param IAssessable $assessment
     * @param User        $user
     * @return string
     * @throws \Aura\Router\Exception\RouteNotFound
     * @throws \CFPropertyList\IOException
     * @throws \CFPropertyList\PListException
     * @throws \DOMException
     */
    public function generateConfigKey(IAssessable $assessment, User $user): string
    {

        /* Generate the hash which is concatenated onto the start url,
         * then hashed again later, as part of ValidSebCheck; but for
         * some reason the client is sending a different configuration
         * than what we're calculating here. I can't figure out why.
         *
         * I've tried various permutations of key and startUrl but to
         * no avail, and I can't easily grasp how seb did it in c#
         *
         * Even the exact shanking from moodle doesn't work, so maybe
         * there's something else missing in v3.3.0 of seb and what
         * I've got going here
         */
        return $this->hash($this->generate($assessment, $user));
    }


    /**
     * Hash an xml into whatever format was required
     *
     * @param string $xml
     * @return string
     * @throws \CFPropertyList\IOException
     * @throws \CFPropertyList\PListException
     * @throws \DOMException
     */
    private function hash(string $xml): string
    {
        $jsonplist = new CFPropertyList();
        $jsonplist->parse($xml, CFPropertyList::FORMAT_XML);

        // Pass root dict to recursively convert dates to ISO 8601 format, encode strings to UTF-8,
        // lock data to Base 64 encoding and remove empty dictionaries.
        $this->preparePlistForJsonEncoding($jsonplist->getValue());

        // Serialize PList to array.
        $plistarray = $jsonplist->toArray();

        // Sort array alphabetically by key using case insensitive, natural sorting. See point 3 for more information.
        $plistarray = $this->arraySort($plistarray);

        // Encode in JSON with following rules from SEB docs.
        // 1. Don't add any whitespace or line formatting to the SEB-JSON string.
        // 2. Don't add unicode or slash escaping.
        $json = json_encode($plistarray, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        // There is no way to prevent json_encode from escaping backslashes. We replace each backslash with a unique string
        // prior to encoding in preparePlistForJsonEncoding(). We can then replace the substitute with a single backslash.
        $json = str_replace(self::BACKSLASH_SUBSTITUTE, "\\", $json);

        return hash(self::HASH_ALGORITHM, $json);
    }

    /**
     * Recursively convert PList date values from unix to iso 8601 format, and ensure strings are UTF 8 encoded.
     *
     * This will mutate the PList.
     */

    /**
     * Recursively convert PList date values from unix to iso 8601 format, and ensure strings are UTF 8 encoded.
     *
     * This will mutate the PList.
     * @param \Iterator $root The root element of the PList. Must be a dictionary or array.
     */
    private function preparePlistForJsonEncoding(\Iterator $root): void
    {
        $this->plistMap(function ($value, $key, $parent): void {
            // Convert date to ISO 8601 if date object.
            if ($value instanceof CFDate) {
                $date = \DateTime::createFromFormat('U', $value->getValue());
                $date->setTimezone(new \DateTimeZone('UTC')); // Zulu timezone a.k.a. UTC+00.
                $isodate = $date->format('c');
                $value->setValue($isodate);
            }
            // Make sure strings are UTF 8 encoded.
            if ($value instanceof CFString) {
                // As literal backslashes will be lost during encoding, we must replace them with a unique substitute to be
                // reverted after JSON encoding.
                $string = str_replace("\\", self::BACKSLASH_SUBSTITUTE, $value->getValue());
                $value->setValue(mb_convert_encoding($string, 'UTF-8'));
            }
            // Data should remain base 64 encoded, so convert to base encoded string for export. Otherwise
            // CFData will decode the data when serialized.
            if ($value instanceof CFData) {
                $data = trim($value->getCodedValue());
                $parent->del($key);
                $parent->add($key, new CFString($data));
            }
            // Empty dictionaries should be removed.
            if ($value instanceof CFDictionary && empty($value->getValue())) {
                $parent->del($key);
            }
        }, $root);
    }


    /**
     * Iterate through the PList elements, and call the callback on each.
     *
     * @param callable $callback A callback function called for every element.
     * @param \Iterator $root The root element of the PList. Must be a dictionary or array.
     * @param bool $recursive Whether the function should traverse dicts and arrays recursively.
     */
    private function plistMap(callable $callback, \Iterator $root, bool $recursive = true): void
    {
        $root->rewind();
        while ($root->valid()) {
            $value = $root->current();
            $key = $root->key();

            // Recursively traverse all dicts and arrays if flag is true.
            if ($recursive && $value instanceof \Iterator) {
                $this->plistMap($callback, $value);
            }

            // Callback function called for every element.
            $callback($value, $key, $root);

            $root->next();
        }
    }


    /**
     * Recursively sort array alphabetically by key.
     *
     * @link https://safeexambrowser.org/developer/seb-config-key.html
     *
     * @param array $array Top level array to process.
     * @return array Processed array.
     */
    private function arraySort(array $array): array
    {
        foreach ($array as $key => $value) {
            if (is_array($value)) {
                $array[$key] = $this->arraySort($value);
            }
        }
        // Sort assoc array. From SEB docs:
        //
        // All <dict> elements from the plist XML must be ordered (alphabetically sorted) by their key names. Use
        // a recursive method to apply ordering also to nested dictionaries contained in the root-level dictionary
        // and in arrays. Use non-localized (culture invariant), non-ASCII value based case insensitive ordering.
        // For example the key <key>allowWlan</key> comes before <key>allowWLAN</key>. Cocoa/Obj-C and .NET/C#
        // usually use this case insensitive ordering as default, but PHP for example doesn't.
        if ($this->isAssociativeArray($array)) {
            // Note this is a pragmatic solution as none of the native PHP *sort method appear to sort strings that
            // differ only in case (e.g. ["allowWLAN", "allowWlan"] is expected to have the lower version first).
            $keys = array_keys($array);
            (new \Collator('root'))->asort($keys); // Use Unicode Collation Algorithm (UCA).
            $original = $array;
            $array = [];
            foreach ($keys as $key) {
                $array[$key] = $original[$key];
            }
        }

        return $array;
    }

    /**
     * Check if an array is associative or sequential.
     *
     * @param array $array Array to check.
     * @return bool False if not associative.
     */
    private function isAssociativeArray(array $array): bool
    {
        if (empty($array)) {
            return false;
        }
        // Check that all keys are not sequential integers starting from 0 (Which is what PHP arrays have behind the scenes.)
        return array_keys($array) !== range(0, count($array) - 1);
    }

    /**
     * Generates list of filter rules
     * @param array $urls
     * @return string
     */
    private function generateWhiteList(array $urls): string
    {
        $urls = array_unique(array_filter($urls));
        $whiteList = '';
        foreach ($urls as $url) {
            $whiteList .= "
      <dict>
        <key>active</key>
        <true />
        <key>regex</key>
        <false />
        <key>expression</key>
        <string>{$url}</string>
        <key>action</key>
        <integer>1</integer>
      </dict>";
        }
        return $whiteList;
    }
}
