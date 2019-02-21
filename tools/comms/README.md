Truncated Article Repair Script (repairTruncatedArticles.php)
===


This script restores the full content of homepage and school news articles
created before the 18.1 Comms upgrade.

When the Comms database tables were first created, the article columns were
created as `TEXT` columns (max size: ~64KB), instead of as `MEDIUMTEXT` (~16MB)
columns; this meant that articles that exceeded this size were truncated to fit
the smaller size.

The original homepage and school news data still exists, and is linked to the
new data: therefore, it's possible to copy the old data back over to the new.

Usage:

    # perform a dry run: no changes will be made,
    # but the script will report what would have changed
    ./repairTruncatedArticles.php

    # perform update
    ./repairTruncatedArticles.php --perform
