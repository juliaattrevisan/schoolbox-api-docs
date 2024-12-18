$(function () {
    "use strict";

    /**
     * Custom events that can be inserted into the `tour_step_event` and triggered on step open or close
     *
     */
    var tourActions = {
        // Open an accordion
        "accordion.open": function (element) {
            $(element).foundation("accordion", "open");
        },

        // Close an accordion
        "accordion.close": function (element) {
            $(element).foundation("accordion", "close");
        },

        // Open a standard dropdown
        "dropdown.open": function (element) {
            $(element).addClass("open");
        },
    };

    // Only show tours windows larger than medium (has sidebar showing)
    if (typeof window.tourData !== "undefined" && window.tourData.length > 0 && window.innerWidth >= 1024) {
        // Function to initiate a joyride

        window.tourStart = function (uuid) {
            // TODO: This will eventually need to handle the situation where 2 are triggered simultaneously
            var tour = window.tours[uuid];

            if (typeof tour !== "undefined") {
                // Grab an instance of IntroJS
                var tourCurrent = introJs();

                // The tour object will eventually include steps that aren't just tooltips for IntroJS to render
                //
                // For now I'm just filtering the steps to those of type 'tooltip' and creating an array of tooltips
                // to pass to IntroJS. In the future I *guess* we will call IntroJS on each individual tooltip step
                // so we can alternate between the different types appropriately.
                var stepsToDisplay = [];

                $(tour.steps).each(function (i, step) {
                    if (step.type === "tooltip") {
                        stepsToDisplay.push({
                            intro: step.body.replaceAll("{CDN_PREFIX}", window.cdnPrefix),
                            element: step.selector,
                            tooltipClass: step.css_class + " closeable-tooltip",
                            uuid: step.uuid,
                            jsEvents: step.events,
                            type: step.type,
                        });
                    } else if (step.type === "modal") {
                        var modalContent = "";

                        if (step.title) {
                            modalContent += "<h2>" + step.title + "</h2>";
                        }

                        modalContent += step.body;

                        stepsToDisplay.push({
                            intro: modalContent.replaceAll("{CDN_PREFIX}", window.cdnPrefix),
                            tooltipClass: step.css_class,
                            uuid: step.uuid,
                            type: step.type,
                        });
                    }
                });

                var buttonLabels = {
                    next: "Next",
                    prev: "Back",
                    done: "Finish",
                    skip: "&times;",
                };

                tourCurrent.setOptions({
                    showStepNumbers: false,
                    showBullets: false,
                    hidePrev: true,
                    hideNext: true,
                    nextLabel: buttonLabels.next,
                    prevLabel: buttonLabels.prev,
                    doneLabel: buttonLabels.done,
                    skipLabel: buttonLabels.skip,
                    scrollTo: "tooltip",
                    disableInteraction: true,
                    exitOnOverlayClick: false,
                    steps: stepsToDisplay.filter(function (obj) {
                        if (obj.type == "tooltip") {
                            return $(obj.element).length; // Filter out elements that don't exist in the DOM
                        } else if (obj.type == "modal") {
                            return true;
                        } else {
                            return false;
                        }
                    }),
                });

                var completed = false;
                //TO DO: Inject route when file is pluginerised
                var saveRoute = "/tour/url/save";

                tourCurrent.onafterchange(function (targetElement) {
                    if (tourCurrent._introItems[tourCurrent._currentStep].tooltipClass == "introjs-intromodal") {
                        $(".introjs-nextbutton").html("Take me on the tour!").addClass("introjs-startbutton");
                        $(".introjs-skipbutton").html("No Thanks").addClass("introjs-nothanksbutton");
                    } else {
                        $(".introjs-nextbutton").html(buttonLabels.next).removeClass("introjs-startbutton");
                        $(".introjs-skipbutton").html(buttonLabels.skip).removeClass("introjs-nothanksbutton");
                        $(".introjs-donebutton").html(buttonLabels.done);
                    }
                });

                tourCurrent.onchange(function (targetElement) {
                    var stepCurrent = tourCurrent._introItems[tourCurrent._currentStep];
                    var stepPrev = tourCurrent._introItems[tourCurrent._currentStep - 1];
                    var stepNext = tourCurrent._introItems[tourCurrent._currentStep + 1];

                    // If the current step has custom JS events attached to open of this step - trigger those events now
                    if (stepCurrent.jsEvents !== null) {
                        runStepEvents(stepCurrent.jsEvents, "open");
                    }

                    // If the user has moved forward, and the previous step had any custom JS events attached on close - trigger those now
                    if (stepPrev !== undefined && stepPrev.jsEvents !== null && tourCurrent._direction === "forward") {
                        runStepEvents(stepPrev.jsEvents, "close");
                    }

                    // If the user has moved back, and the previous step had any custom JS events attached on close - trigger those now
                    if (stepNext !== undefined && stepNext.jsEvents !== null && tourCurrent._direction === "backward") {
                        runStepEvents(stepNext.jsEvents, "close");
                    }
                });

                tourCurrent.oncomplete(function (targetElement) {
                    completed = true;

                    //This matches the consts on the Status/Model - to be updated when this file is a plugin
                    var status = "completed";

                    //Save the status
                    $.post(saveRoute, {
                        tour_uuid: uuid,
                        status: status,
                    });
                });

                tourCurrent.onexit(function (targetElement) {
                    var currentStep = tourCurrent._introItems[tourCurrent._currentStep];

                    //onexit triggers when oncomplete triggers, so need check
                    if (!completed) {
                        //This matches the consts on the Status/Model - to be updated when this file is a plugin
                        var status = "dismissed";

                        //Save the status
                        $.post(saveRoute, {
                            tour_uuid: uuid,
                            status: status,
                        });
                    }

                    // Check for a close event on the step
                    // Purposefully left out of the completed check above - want to run it in both cases
                    if (currentStep !== undefined && currentStep.jsEvents !== null) {
                        runStepEvents(currentStep.jsEvents, "close");
                    }
                });

                //You can retrieve the tour status as follows
                // var getRoute    = '/tour/status/{tour_uuid}';
                // var getUrl      = getRoute.replace("{tour_uuid}", tour["uuid"]);
                //
                // $.get(getUrl, null, function (data, status) {
                //     //Do whatever you like with data
                // });

                //Save the status
                $.post(saveRoute, {
                    tour_uuid: uuid,
                    status: "started",
                });

                // Fire the tour
                tourCurrent.start();

                // If a tour is started before the page has fully loaded, it will not be positioned correctly.
                // This will refresh the tour which triggers it to reposition itself when loaded.
                if (document.readyState !== "complete") {
                    window.addEventListener("load", () => tourCurrent.refresh());
                }
            } else {
                console.log("Unknown feature tour requested: " + uuid);
            }
        };

        // Load the first tour for this page/user into global array and setup triggers for it
        window.tours = [];
        var tour = window.tourData[0];
        window.tours[tour.uuid] = tour;

        $(tour.triggers).each(function (j, trigger) {
            if (trigger.selector && trigger.event) {
                // jQuery is unable to select document/window dynamically from a variable- need to check if the
                // selector is one of these and use a hard coded jQuery selector instead.

                if (trigger.selector === "document") {
                    $(document).on(trigger.event, function () {
                        window.tourStart(tour.uuid);
                    });
                } else if (trigger.selector === "window") {
                    $(window).on(trigger.event, function () {
                        window.tourStart(tour.uuid);
                    });
                } else {
                    $(trigger.selector).on(trigger.event, function () {
                        window.tourStart(tour.uuid);
                    });
                }
            }
        });

        // Trigger 'tourReady' on body tag
        $("body").trigger("tourReady");
    }

    /**
     * Run events for a step
     *
     * @param events
     * @param insertionPoint
     */
    function runStepEvents(events, insertionPoint) {
        for (var key in events) {
            var jsEvent = events[key];

            // Check this event is set to run on the specified insertion point, and the element exists
            if (jsEvent.insertion_point === insertionPoint && $(jsEvent.selector).length) {
                // Check if the event is one of the predefined events
                if (tourActions[jsEvent.event] !== undefined) {
                    tourActions[jsEvent.event].call(this, jsEvent.selector);
                } else {
                    // Otherwise try for a generic trigger on the element
                    $(jsEvent.selector).trigger(jsEvent.event);
                }
            }
        }
    }
});
