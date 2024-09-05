<?php

/**
 * @author      Justin Clarke <justin.clarke@schoolbox.education>
 * @copyright   Copyright 2024, Schoolbox Pty Ltd
 * @link        https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\Admin\SummativeReport\Payload\SubjectOverview;

class Payload
{
    public function __construct()
    {
    }
    public function generate(array $folderId, string $searchTerm): array
    {
        // find the collection of courses imported onto the specified pages
        // find any textboxes that match the search term
        // sort the textboxes by course title naturally
        // return the textboxes grouped by the folder Id
    }
}
