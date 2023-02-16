/* Set up countdown
 */
(function ($j) {
    $j.fn.evaluationCountdown = function (options) {
        var config = init(options, $j(this));

        renderCountdown();

        /**
         * Init
         *
         * @param options
         * @param element
         * @returns {*}
         */
        function init(options, element) {
            var defaults = {
                attemptId: 0,
                evaluationId: 0,
                evaluationType: "assess",
                element: element,
            };
            var mergedOptions = $j.extend({}, defaults, options);

            var derived = {
                endUrl:
                    "/" +
                    mergedOptions.evaluationType +
                    "/end/" +
                    mergedOptions.evaluationId +
                    "/" +
                    mergedOptions.attemptId,
            };

            var merged = $j.extend({}, defaults, derived, mergedOptions);

            return merged;
        }

        /**
         * Render countdown timer
         */
        function renderCountdown() {
            const element = config.element[0];
            element.addEventListener("countdownChange", (event) => {
                const hours = event.detail.hours.toString().padStart(2, "0");
                const minutes = event.detail.minutes.toString().padStart(2, "0");
                const seconds = event.detail.seconds.toString().padStart(2, "0");

                element.innerHTML = `${hours} : ${minutes} : ${seconds}`;

                if (event.detail.seconds === 2 && event.detail.minutes === 0 && event.detail.hours === 0) {
                    $.fn.evaluation.saveAll("");
                }
            });
            element.addEventListener("countdownFinished", () => {
                window.location = config.endUrl;
            });
        }
    };
})(jQuery);
