<?php

$rss = new Alaress_Rss_Stream('http://blogs.abc.net.au/goodgame/rss.xml');
$rss = new Alaress_Rss_Stream('http://blogs.abc.net.au/goodgame/atom.xml');

$allRss = $rss->stream();

