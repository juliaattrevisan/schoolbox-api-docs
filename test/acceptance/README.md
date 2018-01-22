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

These tests are currently configured to run within Schoolbox's Vagrant box.
The acceptance tests are configured to automatically start chromedriver (the web driver for Chrome Headless).

### Demo data set

These tests are currently unreliable with the `legacy` demo data set; this is
due to the dashboard containing `<img>` tags whose `src` is a dead link.

You should switch to the `sales` dataset before reprovisioning and running
these tests: modify `vagrant.yaml` and ensure this value for `data_filename`:

    data_filename: "sales.tar.gz"

### Reprovision

If you've not yet reprovisioned after checking out this branch: run
`vagrant provision` to install Chrome, Chromium WebDriver and its dependencies.

### Codeception test

To run the Codeception tests:

    vagrant ssh
    cd /vagrant
    ./vendor/bin/codecept run --steps

Writing tests
---

See `test/acceptance/LoginCest.php` for an example - it shows an example of
driving a web browser to perform commands, and of a direct database query.
