<?php
require_once __DIR__.'/../../library/Bootstrap.php';

require "settings.php";

echo "<pre>";
echo "IDP metadata:\n\n";

if ($result = idp_metadata()) {
    print_r($result);
} else {
    echo "Error: unable to retrieve or read SAML Identity Provider (IDP) metadata from:\n" .Cfg::get('saml_idp_metadata_url');
}

echo "</pre>";
