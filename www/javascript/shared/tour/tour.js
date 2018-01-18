"use strict";
$(function () {

    /**
     * Custom events that can be inserted into the `tour_step_event` and triggered on step start or finish
     *
     */
    var tourActions = {
        // Open an accordion
        'accordion.open': function (element) {
            $(element).foundation('accordion', 'open');
        },

        // Close an accordion
        'accordion.close': function (element) {
            $(element).foundation('accordion', 'close');
        }
    };

    if (typeof(window.tourUrl) !== 'undefined') {
        $.get(window.tourUrl).done(function (tours) {

            // Function to initiate a joyride

            window.tourStart = function (uuid) {

                // TODO: This will eventually need to handle the situation where 2 are triggered simultaneously

                var tour = window.tours[uuid];

                if (typeof(tour) !== 'undefined') {
                    // Grab an instance of IntroJS
                    var tourCurrent = introJs();

                    // The tour object will eventually include steps that aren't just tooltips for IntroJS to render
                    //
                    // For now I'm just filtering the steps to those of type 'tooltip' and creating an array of tooltips
                    // to pass to IntroJS. In the future I *guess* we will call IntroJS on each individual tooltip step
                    // so we can alternate between the different types appropriately.
                    var stepsToDisplay = [];

                    $(tour.steps).each(function (i, step) {
                        if (step.type === 'tooltip') {
                            stepsToDisplay.push({
                                intro: step.body,
                                element: step.selector,
                                tooltipClass: step.css_class,
                                uuid: step.uuid,
                                jsEvents: step.events
                            });
                        }


                    });

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
                        steps: stepsToDisplay.filter(function(obj) { return $(obj.element).is(':visible'); })
                    });


                    var oldStep = null;
                    var completed  = false;
                    var tourUrl = window.location.pathname;
                    //TO DO: Inject route when file is pluginerised
                    var saveRoute = '/tour/url/save';

                    tourCurrent.onchange(function(targetElement) {
                        var stepCurrent = tourCurrent._introItems[tourCurrent._currentStep];
                        var stepLast = tourCurrent._introItems[tourCurrent._currentStep - 1];

                        // If the current step has custom JS events attached to open of this step - trigger those events now
                        if (stepCurrent.jsEvents !== null) {
                            for (var key in stepCurrent.jsEvents) {
                                var jsEvent = stepCurrent.jsEvents[key];

                                // Check this event is set to run on open, and the element exists
                                if (jsEvent.insertion_point === 'open' && $(jsEvent.selector).length) {
                                    // Check if the event is one of the predefined events
                                    if (tourActions[jsEvent.event] !== undefined) {
                                        tourActions[jsEvent.event].call(this, jsEvent.selector);
                                    } else { // Otherwise try for a generic trigger on the element
                                        $(jsEvent.selector).trigger(jsEvent.event);
                                    }
                                }
                            }
                        }

                        // If the previous step had any custom JS events attached on close - trigger those now
                        if (stepLast !== undefined && stepLast.jsEvents !== null) {
                            for (var key in stepLast.jsEvents) {
                                var jsEvent = stepLast.jsEvents[key];

                                // Check this event is set to run on open, and the element exists
                                if (jsEvent.insertion_point === 'close' && $(jsEvent.selector).length) {
                                    // Check if the event is one of the predefined events
                                    if (tourActions[jsEvent.event] !== undefined) {
                                        tourActions[jsEvent.event].call(this, jsEvent.selector);
                                    } else { // Otherwise try for a generic trigger on the element
                                        $(jsEvent.selector).trigger(jsEvent.event);
                                    }
                                }
                            }
                        }

                        // Update the status in the DB
                        var newStep = stepCurrent.uuid;
                        var status = "unactioned"; // TODO: use const from Status/Model when this is made into a plugin

                        $.post(saveRoute, {
                            url: tourUrl,
                            oldStep: oldStep,
                            newStep: stepCurrent.uuid,
                            status: status
                        });

                        oldStep = newStep;
                    });

                    tourCurrent.oncomplete(function(targetElement) {
                        completed = true;

                        var newStep     = tourCurrent._introItems[tourCurrent._currentStep].uuid;

                        //This matches the consts on the Status/Model - to be updated when this file is a plugin
                        var status      = "completed";

                        //Save the status
                        $.post(saveRoute, {
                            url: tourUrl,
                            oldStep: oldStep,
                            newStep: newStep,
                            status: status
                        });
                    });

                    tourCurrent.onexit(function(targetElement) {
                        //onexit triggers when oncomplete triggers, so need check
                        if(!completed) {
                            var newStep = tourCurrent._introItems[tourCurrent._currentStep].uuid;

                            //This matches the consts on the Status/Model - to be updated when this file is a plugin
                            var status = "dismissed";

                            //Save the status
                            $.post(saveRoute, {
                                url: tourUrl,
                                oldStep: oldStep,
                                newStep: newStep,
                                status: status
                            });
                        }
                    });


                    //You can retrieve the tour status as follows
                    var getRoute    = '/tour/status/{tour_uuid}';
                    var getUrl      = getRoute.replace("{tour_uuid}", tour["uuid"]);

                    $.get(getUrl, null, function (data, status) {
                        //Do whatever you like with data
                    });

                    // Fire the tour
                    tourCurrent.start();
                } else {
                    console.log('Unknown feature tour requested: ' + uuid);
                }
            };

            // Load all tours for this page/user into global array and setup triggers for them

            window.tours = [];

            $(tours).each(function (i, tour) {
                window.tours[tour.uuid] = tour;

                $(tour.triggers).each(function (j, trigger) {
                    if (trigger.selector && trigger.event) {
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
        });
    }
});