/**
 * Generates a Kami document export, then downloads it
 *
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
(function ($, window, document, undefined) {
    var kamiExport,
        pluginName = "kamiExport",
        cookieNameToken = "kamiExportToken",
        cookieNameStatus = "kamiExportStatus",
        defaults = {
            timeout: 120, // after how many seconds should we assume the download failed and re-enable button
        };

    /**
     * @param {dom}    element
     * @param {object} options
     * @param {jQuery} options.toast   Required.
     * @param {int}    options.timeout Optional.
     * @constructor
     */
    function KamiExport(element, options) {
        kamiExport = this;
        kamiExport.element = $(element);
        kamiExport.options = $.extend(true, {}, defaults, options);

        // validate a couple things
        if (!options || !options.toast) {
            throw "'toast option is required and must be a jQuery object'";
        }

        // Set up the link with a random token
        updateDownloadToken(kamiExport.element);

        // install listeners
        kamiExport.element.on("click", function (event) {
            // We need to prevent multiple clicks on the download button. But we cannot do an async request
            // as the browser blocks async file downloads.
            // @see https://stackoverflow.com/questions/1106377/detect-when-browser-receives-file-download
            var $downloadButton = $(this);
            $downloadButton.attr("disabled", true);
            kamiExport.options.toast.toastActivate({ text: "Requesting export..." });
            checkDownloadFinished($downloadButton, $downloadButton.attr("token"), 1000, 0, kamiExport.options.timeout);
        });
    }

    /**
     * Appends a new download token. Overwriting if it already exists
     *
     * @param {jQuery} $downloadButton
     */
    function updateDownloadToken($downloadButton) {
        removeDownloadToken($downloadButton);
        var token = generateRandomToken(10);
        $downloadButton.attr("token", token);
        $downloadButton.attr("href", $downloadButton.attr("href") + "?token=" + token);
    }

    /**
     * Removes the download token from the link, and the data attribute from the DOM element
     *
     * @param {jQuery} $downloadButton
     */
    function removeDownloadToken($downloadButton) {
        $downloadButton.removeAttr("token");
        // strip the token from the URL, and the '?' char if no other args remain
        $downloadButton.attr(
            "href",
            $downloadButton
                .attr("href")
                .replace(/token=\d+&*/, "")
                .replace(/\?$/, ""),
        );
    }

    /**
     * Check to see if the download has finished
     *
     * @param {jQuery} $downloadButton  The download button
     * @param {string} token            The token we are looking for
     * @param {int} interval            Milliseconds between checks
     * @param {int} count               How many checks have been performed so far
     * @param {int} limit               How many seconds to check for
     */
    function checkDownloadFinished($downloadButton, token, interval, count, limit) {
        // Has our limit expired?
        if ((count * interval) / 1000 >= limit) {
            kamiExport.options.toast.toastTimeout({
                text: "No download detected after " + limit + " seconds. Assuming failed",
                css: "alert",
            });
            stopPolling($downloadButton);
            return;
        }
        // Has the server finished the request?
        if (token === getCookie(cookieNameToken)) {
            if ("done" === getCookie(cookieNameStatus)) {
                kamiExport.options.toast.toastTimeout({
                    text: "Export successful",
                    css: "success",
                });
            } else if ("pending" === getCookie(cookieNameStatus)) {
                kamiExport.options.toast.toastTimeout({
                    text: "Export timed out server-side",
                    css: "alert",
                });
            } else if ("error" === getCookie(cookieNameStatus)) {
                kamiExport.options.toast.toastTimeout({
                    text: "Export failed",
                    css: "alert",
                });
            }
            stopPolling($downloadButton);
            return;
        }
        setTimeout(function () {
            checkDownloadFinished($downloadButton, token, interval, count + 1, limit);
        }, interval);
    }

    /**
     * Common logic when we finish polling to see if download has finished
     *
     * @param {jQuery} $downloadButton
     */
    function stopPolling($downloadButton) {
        deleteCookie(cookieNameToken);
        deleteCookie(cookieNameStatus);
        updateDownloadToken($downloadButton);
        $downloadButton.attr("disabled", false);
    }

    /**
     * Read a cookie
     *
     * @param {string} cname
     * @return {*}
     */
    function getCookie(cname) {
        var name = cname + "=";
        var decodedCookie = decodeURIComponent(document.cookie);
        var ca = decodedCookie.split(";");
        for (var i = 0; i < ca.length; i++) {
            var c = ca[i];
            while (c.charAt(0) === " ") {
                c = c.substring(1);
            }
            if (c.indexOf(name) === 0) {
                return c.substring(name.length, c.length);
            }
        }
        return "";
    }

    /**
     * Delete a cookie by setting its expirty to now
     *
     * @param cname
     */
    function deleteCookie(cname) {
        var d = new Date();
        d.setTime(d.getTime()); // now
        var expires = "expires=" + d.toUTCString();
        document.cookie = cname + "=empty;" + expires + ";path=/";
    }

    /**
     * Return a random number with 'length' digits, not starting with '0'
     * @param length
     * @return {string}
     */
    function generateRandomToken(length) {
        var random = Math.random();
        while (random < 0.1) {
            random *= 10;
        }
        return random.toString().substr(2, length);
    }

    $.fn[pluginName] = function (options) {
        return this.each(function () {
            if (!$.data(this, "plugin_" + pluginName)) {
                $.data(this, "plugin_" + pluginName, new KamiExport(this, options));
            }
        });
    };
})(jQuery, window, document);
