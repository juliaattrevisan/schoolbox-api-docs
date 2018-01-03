$(function () {

    if (typeof(window.tourUrl) !== 'undefined') {
        $.get(tourUrl).done(function (tours) {

            // Function to initiate a joyride

            window.tourStart = function (uuid) {

                // TODO: This will eventually need to handle the situation where 2 are triggered simultaneously

                var tour = window.tours[uuid];

                if (typeof(tour) !== 'undefined') {
                    var tourCurrent = introJs();

                    tourCurrent.setOptions({
                        showStepNumbers: false,
                        hidePrev: true,
                        hideNext: true,
                        nextLabel: 'Next',
                        prevLabel: 'Back',
                        skipLabel: '&times;',
                        scrollTo: 'tooltip',
                        disableInteraction: true,
                        exitOnOverlayClick: false,
                        steps: tour.steps
                    });

                    tourCurrent.start();
                } else {
                    console.log('Unknown feature tour requested: ' + uuid);
                }
            };

            // Load all tours for this page/user into global array and setup triggers for them

            window.tours = [];

            $(tours).each(function (i, tour) {
                console.log('Loading tour: ' + tour.title + ' (' + tour.uuid + ')')

                window.tours[tour.uuid] = tour;

                $(tour.triggers).each(function (j, trigger) {
                    if (trigger.selector && trigger.event) {
                        console.log('Triggering on: $("' + trigger.selector + '").' + trigger.event + ' event');

                        // jQuery is unable to select document/window dynamically from a variable- need to check if the
                        // selector is one of these and use a hard coded jQuery selector instead.

                        if (trigger.selector === 'document') {
                            $(document).on(trigger.event, function () {
                                window.tourStart(tour.uuid);
                            });
                        }
                        else if (trigger.selector === 'window') {
                            $(window).on(trigger.event, function () {
                                window.tourStart(tour.uuid);
                            });
                        }
                        else {
                            $(trigger.selector).on(trigger.event, function () {
                                window.tourStart(tour.uuid);
                            });
                        }
                    }
                });
            });

            // Trigger 'tourReady' on body tag

            $('body').trigger('tourReady');
            console.log('All tours ready');
        });
    }
});