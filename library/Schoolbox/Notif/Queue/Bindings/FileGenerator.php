<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Notif\Queue\Bindings;

use Alaress\Exception\IO\IOException;
use Schoolbox\User;

class FileGenerator implements GeneratorInterface
{

    /** @var string */
    private $filename;

    /**
     * @param string $filename
     * @throws IOException
     */
    public function __construct($filename)
    {
        if (!is_readable($filename)) {
            throw new IOException("Can't read '$filename'");
        }

        $this->filename = $filename;
    }


    /**
     * @param User $user
     * @return string[][]
     */
    public function generateBindingsForUser(User $user)
    {
        $filename = $this->filename;
        $userId = $user->getId();

        $data = `grep ".ux.$userId\s" $filename`;

        $bindings = [];

        foreach (explode("\n", $data) as $line) {
            list ($exchange, $route) = explode("\t", trim($line));
            unset($line);
            unset($exchange);

            $n = '*.notify.';

            if (strpos($route, $n) === false) {
                continue;
            }

            $route = explode('.', str_replace($n, '', $route), 2);

            $bindings[$route[0]][] = $route[1];
        }

        return $bindings;
    }
}
