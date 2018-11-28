<?php
/**
 * @copyright Copyright 2018, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Curriculum\Source\AustralianCurriculum;

use EasyRdf_Sparql_Client;

/**
 * Class SparqlClient
 */
class SparqlClient extends EasyRdf_Sparql_Client
{
    public const API_URI = 'http://rdf.australiancurriculum.edu.au/api/sparql';


}
