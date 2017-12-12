$(function () {

    var url = '/tour/url/' +
        encodeURIComponent(
            Base64.encode(
                window.location.pathname
            )
        );

    $.get(url).done(function(tours) {

        // Function to initiate a joyride

        window.tourStart = function(uuid) {

            // TODO: This will eventually need to handle the situation where 2 are triggered simultaneously

            var tour = window.tours[uuid];

            if (typeof(tour) !== 'undefined') {
                alert(tour.title + "\n" + tour.uuid);
            } else {
                console.log('Unknown feature tour requested: ' + uuid);
            }
        };

        // Load all tours for this page/user into global array and setup triggers for them

        window.tours = [];

        $(tours).each(function(i, tour) {
            console.log('Loading tour: ' + tour.title + ' (' + tour.uuid + ')')

            window.tours[tour.uuid] = tour;

            $(tour.triggers).each(function(j, trigger) {
                // If no event/selector was specified, start the tour immediately.
                // Otherwise schedule it to start on the specified selector/event combination.

                if (trigger.selector === null && trigger.event === null) {
                    console.log('Triggering immediately');

                    window.tourStart(tour.uuid);
                } else {
                    console.log('Triggering on: $("' + trigger.selector + '").' + trigger.event + ' event');

                    $(trigger.selector).on(trigger.event, function() {
                        window.tourStart(tour.uuid);
                    });
                }
            });
        });

        $(document).trigger('tourReady');
        console.log('All tours ready');
    });
});