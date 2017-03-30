<?php
require_once __DIR__.'/../../library/Bootstrap.php';

$url = new \Zend\Uri\Uri(Cfg::get('saml_idp_metadata_url'));
$idp_metadata_url = $url->toString();

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $idp_metadata_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
curl_setopt($ch, CURLOPT_USERAGENT, 'schoolbox/'.Cfg::get('site_version'));
$idpmetadataraw = curl_exec($ch);
curl_close($ch);

$idpmetadata = simplexml_load_string($idpmetadataraw);
if ($idpmetadata) {
    $idp['SigningKeys'] = array();
    $idp['SingleSignOnService'] = array();
    $idp['SingleLogoutService'] = array();


    $x = 0;
    foreach ($idpmetadata->IDPSSODescriptor->KeyDescriptor as $key) {
        foreach ($key->attributes() as $keytype => $keytypevalue) {
            if ((string)$keytypevalue == "signing") {
                $cert = "-----BEGIN CERTIFICATE-----\n";
                $cert .= implode("\n",str_split((string)$key->KeyInfo->X509Data->X509Certificate, 64));
                $cert .= "\n-----END CERTIFICATE-----";
                $output = array();
                $status = '1';
                exec('echo "' . $cert . '" | openssl x509 -noout -dates', $output, $status);
                if ($status == 0) {
                    $idp['SigningKeys'][$x]['X509Cert'] = $cert;
                    foreach ($output as $certdate) {
                        $thiscertdate = explode('=', $certdate);
                        $idp['SigningKeys'][$x]['Dates'][$thiscertdate[0]] = $thiscertdate[1];
                    }
               }
               $x++;
            }
        }
    }

    $x = 0;
    foreach ($idpmetadata->IDPSSODescriptor->SingleSignOnService as $SSO) {
       foreach ($SSO->attributes() as $key => $value) {
           $idp['SingleSignOnService'][$x][$key] = (string)$value;
       }
       $x++;
    }

    $x = 0;
    foreach ($idpmetadata->IDPSSODescriptor->SingleLogoutService as $SLO) {
        foreach ($SLO->attributes() as $key => $value) {
            $idp['SingleLogoutService'][$x][$key] = (string)$value;
        }
        $x++;
    }
} else {
    die("Error: unable to retrieve or read SAML Identity Provider (IDP) metadata from:\n$idp_metadata_url");
}
echo "<pre>";
echo "IDP metadata:\n\n";
print_r($idp);
echo "</pre>";
