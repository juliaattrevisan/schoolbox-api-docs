/**
 * A compact string countdown
 * Will a replace a time element's contents with a countdown to the time set in the datetime attr
 * E.g. '1d 4h left', '2h 30m left'
 *
 * Requires momentjs & moment-precise-range
 *
 * @author      Doug MacKenzie <doug.mackenzie@alaress.com.au>
 * @copyright   Copyright 2016, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

;(function($) {

    /**
     * The plugin init
     * @param options
     */
    $.fn.miniCountdown = function(options) {
        var self = this;

        var settings = $.extend({
            destroyOnComplete: true,
            futureSuffix: 'remaining',
            pastSuffix: '',
            onDestroy: $.noop
        }, options);

        self.updateCountdown(settings);
    };


    /**
     * Update the countdown
     * @param settings
     */
    $.fn.updateCountdown = function(settings) {

        var datetime = moment($(this).attr('datetime')),
            now = moment(),
            diff = datetime.diff(now),
            preciseDiff = moment.preciseDiff(datetime, now, true),
            timeLeft;

        /**
         * Check if the countdown has been reached and run destroy stuff if applicable
         */
        if (diff < 0 && settings.destroyOnComplete) {
            $(this).text('');
            this.stopCountdown();
            settings.onDestroy.call(this);
            return;
        }

        /**
         * Set different ways of showing the countdown string depending on how much time is left
         */
        if (preciseDiff.hours == 0 && preciseDiff.minutes == 0 && preciseDiff.seconds <= 60) {

            // Less than a minute, show seconds
            timeLeft = preciseDiff.seconds + ' second' + (preciseDiff.seconds > 1 ? 's' : '');

        } else if (preciseDiff.days == 0 && preciseDiff.hours == 0) {

            // Less than an hour, show minutes
            timeLeft = preciseDiff.minutes + ' minute' + (preciseDiff.minutes > 1 ? 's' : '');

        } else if (preciseDiff.days == 0) {

            // Less than a day, show hours and minutes (if minutes isn't 0)
            if (preciseDiff.minutes == 0) {
                timeLeft = preciseDiff.hours + 'h';
            } else {
                timeLeft = preciseDiff.hours + 'h' + ' ' + preciseDiff.minutes + 'm';
            }

        } else {

            // Greater than a day, show days and hours (if hours isn't 0)
            if (preciseDiff.hours == 0) {
                timeLeft = preciseDiff.days + ' days';
            } else {
                timeLeft = preciseDiff.days + 'd' + ' ' + preciseDiff.hours + 'h';
            }
        }

        /**
         * Append the suffix if applicable
         */
        if (diff >= 0 && settings.futureSuffix != '') {
            timeLeft += ' ' + settings.futureSuffix;
        } else if (settings.pastSuffix != '') {
            timeLeft += ' ' + settings.pastSuffix;
        }

        /**
         * Update the text
         */
        $(this).text(timeLeft);


        /**
         * Set an interval to update this periodically
         * This runs on every update to check if this should start updating more or less frequently
         */
        this.updateCountdownInterval(settings, datetime);
    };


    /**
     * Kill the existing interval and set it again
     * @param settings
     * @param datetime
     */
    $.fn.updateCountdownInterval = function(settings, datetime) {

        // Kill the existing interval
        this.stopCountdown();

        // Set the interval again
        this.setCountdownInterval(settings, datetime);
    };


    /**
     * Check the correct interval update frequency and set the interval
     * @param settings
     * @param datetime
     */
    $.fn.setCountdownInterval = function(settings, datetime) {

        var self = this;

        // Check the difference in times, we'll use it to determine how often to update this bad boy
        var updateInterval = self.getUpdateInterval(settings, datetime);

        self.data('interval', setInterval(function() {
            self.updateCountdown(settings);
        }, updateInterval));
    };


    /**
     * Determine how often the countdown should update based on the difference in times
     * @param settings
     * @param datetime
     * @returns {number}
     */
    $.fn.getUpdateInterval = function(settings, datetime) {

        var now = moment(),
            preciseDiff = moment.preciseDiff(datetime, now, true);

        if (preciseDiff.hours == 0 && preciseDiff.minutes <= 1) {
            // A couple minutes or less away - every second
            return 1000;

        } else if (preciseDiff.days == 0 || (preciseDiff.days == 1 && preciseDiff.hours == 0)) {
            // A day or less away - every minute
            return 60000;

        } else {
            // Greater than a day - every hour
            return 3600000;
        }
    };


    /**
     * Stop the countdown from updating (clear the interval)
     */
    $.fn.stopCountdown = function() {
        clearInterval(this.data('interval'));
    };

}(jQuery));
