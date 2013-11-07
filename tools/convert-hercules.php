<?php
/**
 * Converts Hercules images (in /templates/{{site.location}}/m/hercules.html) to
 * image files.
 */

foreach (glob(dirname(__DIR__).'/templates/*/m/hercules.html') as $file) {
    if (preg_match('|/templates/(.*?)/m/hercules.html$|', $file, $matches)) {
        $site_location = $matches[1];
        $document = new DOMDocument();
        $document->loadHTMLFile($file);
        foreach ($document->getElementsByTagName('img') as $image) {
            /** @var DomElement $image */
            $source = $image->getAttribute('src');
            if (preg_match('|^data:image/png|', $source)) {
                $dir     = dirname(__DIR__)."/www/images/$site_location/";
                $outfile = "$dir/hercules.png";
                file_put_contents("$dir/hercules.png", file_get_contents($source));
            }
        }
    }
}
