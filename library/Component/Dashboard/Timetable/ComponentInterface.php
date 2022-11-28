<?php

/**
 * @copyright   Copyright 2022, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

/**
 * This old area doesn't have a namespace, making this interface name
 * a bit annoying to decide
 */
interface Component_Dashboard_Timetable_ComponentInterface
{
    public function execute(): bool;


    /**
     * Returns an array with the following:
     *
     * [
     *     'template' => the path to the twig template
     *     'data' => an arbitrary struct for the twig template
     * ]
     *
     * Can return false if it can't render
     *
     * @param string $templateHandle
     * @return bool|array
     */
    public function renderTemplate(string $templateHandle): bool|array;
}
