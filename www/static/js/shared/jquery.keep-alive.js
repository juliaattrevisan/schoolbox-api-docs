/**
 * jQuery plugin for keeping a user's session alive.
 *
 * Periodically makes an AJAX request to an authenticated page.
 *
 * Please don't use this just anywhere - only use on pages which you feel a
 * user could genuinely be looking at a screen without interacting for an
 * extended period (examples: quiz, collabdocs)
 *
 * Options:
 * * location: the location to ping
 * * interval: time in ms between pings
 * * onFailure: what happens when the session dies unexpectedly
 */
(function($) {
    "use strict";

    var defaults = {
        location: '/keepAlive',
        interval: 180000,
        onFailure: function() {}
    };

    /**
     * @param settings
     */
    $.keepAlive = function(settings) {
        var config   = $.extend({}, defaults, settings);
        var interval = setInterval(
            function() {
                $.ajax(config.location)
                    .done(function() {
                        console.debug('User session kept alive');
                    })
                    .fail(function() {
                        console.warn('User session died unexpectedly!');
                        clearInterval(interval);
                        settings.onFailure();
                    });
            },
            config.interval
        )
    };
}(jQuery));
