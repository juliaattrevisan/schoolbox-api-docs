<?php

/**
 * GoogleDriveRedirect
 *
 * Redirects Google Drive links to their original location so the preview layer
 * can hand the URL off to the browser/view as a redirect.
 *
 * Uses parse_url to match the scheme+host against the constant
 * `https://drive.google.com`.
 *
 * Justin Clarke <justin.clarke@schoolbox.education>
 * @copyright Copyright 2025, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\Preview\Chain\Strategy\External;

class GoogleDriveRedirect extends Base
{
    /**
     * Base URL to match against (scheme + host)
     */
    private const GOOGLE_DRIVE_BASE = 'https://drive.google.com';

    /**
     * If the URL's scheme + host matches the Google Drive base constant,
     * return a redirect result. Otherwise continue to the next strategy.
     *
     * @return array|null
     */
    public function test(): ?array
    {
        $url = $this->getUrl();

        $parts = parse_url($url);

        if ($parts === false) {
            return $this->testNext();
        }

        $scheme = isset($parts['scheme']) ? strtolower($parts['scheme']) : null;
        $host   = isset($parts['host']) ? strtolower($parts['host']) : null;

        if ($scheme && $host && ($scheme . '://' . $host) === strtolower(self::GOOGLE_DRIVE_BASE)) {
            return [
                'type' => 'redirect',
                'src'  => $url,
            ];
        }

        return $this->testNext();
    }
}
