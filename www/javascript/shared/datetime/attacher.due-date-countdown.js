/**
 * Automatically apply a plugin to a predefined data attribute.
 * This saves you from having to instantiate plugins manually.
 *
 * Due date countdown setup, based on data attributes
 *
 * Usage:
 * <time datetime="{{ dateObject }}" data-due-countdown>{{ date.getRelativeTime }}</time>
 *
 * Requires:
 * * momentjs
 * * moment-precise-range
 * * mini-countdown
 */

$(function(){

    $('time[data-due-countdown]').miniCountdown({
        onDestroy: function() {
            this.parent().remove();
        }
    });

});