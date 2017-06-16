Acceptance tests
===

All Codeception acceptance tests go in this folder.


Structure
---

    _data/ # not sure yet
    _output/ # logs and screenshots of runs
    _support/ # custom steps for scenarios?
    bin/
        phantomjs/ # created by phantomjs-server.sh
        phantomjs-server.sh
    *Cest.php # individual test scenarios

Running tests
---

First, run `./test/acceptance/bin/phantomjs-server.sh` to download and install
PhantomJS (headless browser).

Then, run:

    ./composer.phar install
    ./vendor/bin/codecept run --steps

Writing tests
---

See `test/acceptance/LoginCest.php` for an example - it shows an example of
driving a web browser to perform commands, and of a direct database query.
