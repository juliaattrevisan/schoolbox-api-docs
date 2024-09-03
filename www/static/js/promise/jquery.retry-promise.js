/**
 * jQuery plugin for attempting to retry a promise.
 *
 * This can also be used with AJAX requests.
 */
(function ($) {
    "use strict";

    var defaults = {
        timeout: 500,
        maxRetry: 10,
    };

    /**
     * @param internalPromise The promise object to retry.
     * @param settings
     * @returns A new promise object
     */
    $.retryPromise = function (internalPromise, settings) {
        return _retryPromise(internalPromise, $.extend({}, defaults, settings), 0, 0);
    };

    /**
     * Internal method for actually retrying promise
     *
     * @param internalPromise
     * @param settings
     * @param attempts The amount of times the promise has been attempted.
     * @param wait     The amount of time to wait before trying the promise.
     * @returns A new promise object
     * @private
     */
    var _retryPromise = function (internalPromise, settings, attempts, wait) {
        var deferred = $.Deferred();
        setTimeout(function () {
            internalPromise()
                .done(function () {
                    deferred.resolve(arguments);
                })
                .fail(function () {
                    // If the response is 4xx client error or 500 internal server error,
                    // fail immediately rather than making multiple failing attempts
                    if ((arguments[0].status >= 400 && arguments[0].status < 500) || attempts >= settings.maxRetry) {
                        deferred.reject(arguments);
                    } else {
                        // Try again after some jitter
                        var jitter = parseInt(
                            Math.random() *
                                Math.min(
                                    settings.timeout * settings.maxRetry,
                                    Math.pow(2, attempts + 1) * settings.timeout,
                                ),
                        );
                        return _retryPromise(internalPromise, settings, attempts + 1, jitter)
                            .done(function () {
                                deferred.resolve(arguments);
                            })
                            .fail(function () {
                                deferred.reject(arguments);
                            });
                    }
                });
        }, wait);
        return deferred.promise();
    };
})(jQuery);
