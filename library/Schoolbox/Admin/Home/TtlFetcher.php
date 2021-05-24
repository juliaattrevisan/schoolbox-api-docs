<?php

/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Admin\Home;

use UnexpectedValueException;

use function escapeshellarg;
use function exec;
use function preg_match;
use function sprintf;
use function strpos;
use function trim;

/**
 * Responsible for fetching the authoritative TTL for a domain name's DNS record.
 *
 * This is not the TTL as seen by a resolving DNS server, but rather the TTL set
 * at the registrar/start-of-authority(SOA)
 *
 * The native PHP function dns_get_record() always uses the resolving DNS server, so is
 * not suitable for this task.
 */
class TtlFetcher
{
    // Default DNS server for determining domain's SOA
    private const IP_GOOGLE_DNS_SERVER = '8.8.8.8';

    /**
     * Returns the original TTL of a domain name
     *
     * @param string $domain     A fully qualified domain name
     * @param string $nameserver The nameserver we will use to look up the domains name servers
     * @return int
     * @throws UnexpectedValueException If unable to determine the domain's TTL
     */
    public function getTtl(string $domain, string $nameserver = self::IP_GOOGLE_DNS_SERVER): int
    {
        $soa = $this->getStartOfAuthority($domain, $nameserver);
        return $this->getTtlFromSoa($domain, $soa);
    }


    /**
     * Returns a domain's TTL by querying the domain's SOA
     *
     * @param string $domain
     * @param string $soa
     * @return int
     * @throws UnexpectedValueException
     */
    private function getTtlFromSoa(string $domain, string $soa): int
    {
        $command = sprintf("dig @%s %s +noall +answer", escapeshellarg($soa), escapeshellarg($domain));
        exec($command, $output);
        //
        //  ; <<>> DiG 9.11.3-1ubuntu1.12-Ubuntu <<>> @ns-1894.awsdns-44.co.uk. vagrant.dev.schoolbox.cloud +noall +answer
        //  ; (2 servers found)
        //  ;; global options: +cmd
        //  vagrant.dev.schoolbox.cloud. 3600 IN    A   192.168.36.10

        foreach ($output as $line) {
            $trimmed = trim($line);
            if ($trimmed === '' || strpos($trimmed, ';') === 0) {
                continue;
            }
            $matches = [];
            if (preg_match("/^[.\w-]+\s+(\d+)\s+IN/", $trimmed, $matches)) {
                return (int)$matches[1];
            }
        }

        throw new UnexpectedValueException("Unable to determine TTL for domain: '$domain'");
    }


    /**
     * Returns the nameserver responsible for DNS records for the specified domain
     *
     * @param string $domain     The domain whose name servers we are interested in
     * @param string $nameserver The nameserver to query to fetch the domain's name servers
     * @param int    $depth      Recursion depth
     * @return string
     * @throws UnexpectedValueException If unable to determine SOA for domain name
     */
    private function getStartOfAuthority(string $domain, string $nameserver, int $depth = 0): string
    {
        if ($depth > 100) {
            throw new UnexpectedValueException("Recursion depth reached fetching TTL: '$domain'");
        }

        $command = sprintf("dig @%s %s SOA +noall +answer +authority", escapeshellarg($nameserver), escapeshellarg($domain));
        exec($command, $output);

        //  ; <<>> DiG 9.11.3-1ubuntu1.12-Ubuntu <<>> @8.8.8.8 vagrant.dev.schoolbox.cloud SOA +noall +authority +nostats
        //  ; (1 server found)
        //  ;; global options: +cmd
        //  dev.schoolbox.cloud.    301 IN  SOA ns-1894.awsdns-44.co.uk. awsdns-hostmaster.amazon.com. 1 7200 900 1209600 86400

        // ; <<>> DiG 9.11.3-1ubuntu1.14-Ubuntu <<>> andie.standrewscc.qld.edu.au SOA +noall +answer +authority
        // ;; global options: +cmd
        // andie.standrewscc.qld.edu.au. 51290 IN   CNAME   stacc.schoolbox.cloud.
        // stacc.schoolbox.cloud.   68  IN  CNAME   sbox-nlb-public-aa2a5eec300b979b.elb.ap-southeast-2.amazonaws.com.
        // elb.ap-southeast-2.amazonaws.com. 2 IN   SOA ns-527.awsdns-01.net. awsdns-hostmaster.amazon.com. 1 7200 900 1209600 60

        // If domain is a CNAME, output will have CNAME records
        // In this case, we want to strip the leading URL fragment and recurse

        $soa = null;
        foreach ($output as $line) {
            $trimmed = trim($line);
            if ($trimmed === '' || strpos($trimmed, ';') === 0) {
                continue;
            }
            if (preg_match("/^{$domain}\.\s+\d+\s+IN\s+CNAME/", $trimmed, $soaMatches)) {
                return $this->getStartOfAuthority(
                    preg_replace("/^[^.]+\./", '', $domain),
                    $nameserver,
                    $depth + 1
                );
            }
            $matches = [];
            if (preg_match('/^[.\w-]+\s+\d+\s+IN\s+SOA\s+([.\w-]+)/', $trimmed, $matches)) {
                $soa = $matches[1];
            }
        }
        if ($soa !== null) {
            return $soa;
        }
        throw new UnexpectedValueException("Unable to determine SOA for domain: '$domain'");
    }
}
