<?php
require_once __DIR__.'/../../library/Bootstrap.php';

require "saml-setup.php";

echo "<pre>";
echo "IDP metadata:\n\n";

if ($idp) {
    print_r($idpMetadata_raw);
} else {
    echo "Error: unable to retrieve or read SAML Identity Provider (IDP) metadata from:\n" .Cfg::get('saml_idp_metadata_url');
}

echo "</pre>";
