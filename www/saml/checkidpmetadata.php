<?php

require_once __DIR__ . '/../../conf/core/require.php';
$kernel = new \Schoolbox\Application\WebRequest\Kernel();

require "saml-setup.php";

echo "<pre>";
echo "IDP metadata:\n\n";

if (Config::get('saml_idp_metadata_url')) {
    if ($idpMetadata ?? false) {
        try {
            $check = OneLogin\Saml2\IdPMetadataParser::parseXML($idpMetadata->getStreamContents());
            print_r($check['idp'] ?? '');
        } catch (Exception $e) {
            echo "Error: unable to read SAML Identity Provider (IDP) metadata from:\n" . Config::get('saml_idp_metadata_url') . "\n";
        }
    } else {
        echo "Error: unable to retrieve SAML Identity Provider (IDP) metadata from:\n" . Config::get('saml_idp_metadata_url') . "\n";
    }
} else {
    echo "SAML Identity Provider (IDP) metadata URL not configured.\n";
}

echo "\nOverall IDP configuration (IDP Metadata + Schoolbox configuration):\n";
/** @var array $settingsInfo */
print_r($settingsInfo['idp']);

echo "</pre>";
