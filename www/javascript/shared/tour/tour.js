$(function () {

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
                                uuid: step.uuid
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

                    var tourTaken = false;
                    var oldStep = null;
                    var completed  = false;
                    var saveRoute = '/tour/url/{url}/{step}';

                    tourCurrent.onchange(function(targetElement) {
                        var tourUrl     = btoa(window.location.pathname);
                        var newStep     = tourCurrent._introItems[tourCurrent._currentStep].uuid;
                        var postUrl     = saveRoute.replace("{url}", tourUrl).replace("{step}", oldStep || "");
                        var status      = "Unactioned";

                        //Save the status
                        $.post(postUrl, {
                            newStep: newStep,
                            status: status
                        }, function () {

                        });

                        oldStep         = newStep;
                    });

                    tourCurrent.oncomplete(function(targetElement) {
                        completed = true;

                        var tourUrl     = btoa(window.location.pathname);
                        var newStep     = tourCurrent._introItems[tourCurrent._currentStep].uuid;
                        var postUrl     = saveRoute.replace("{url}", tourUrl).replace("{step}", oldStep || "");
                        var status      = "Completed";

                        //Save the status
                        $.post(postUrl, {
                            newStep: newStep,
                            status: status
                        }, function () {

                        });
                    });

                    tourCurrent.onexit(function(targetElement) {
                        //onexit triggers when oncomplete triggers, so need check
                        if(!completed) {
                            var tourUrl = btoa(window.location.pathname);
                            var newStep = tourCurrent._introItems[tourCurrent._currentStep].uuid;
                            var postUrl = saveRoute.replace("{url}", tourUrl).replace("{step}", oldStep || "");
                            var status = "Dismissed";

                            //Save the status
                            $.post(postUrl, {
                                newStep: newStep,
                                status: status
                            }, function () {

                            });
                        }
                    });


                    //You can retrieve the tour status as follows
                    var getRoute    = '/tour/getStatus/{tour_uuid}';
                    var getUrl      = getRoute.replace("{tour_uuid}", tour["uuid"]);

                    $.get(getUrl, null, function (data, status) {
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