(function() {
    "use strict";
    /**
     * Update a fraction display, based on the value of selected radio buttons within a form.
     *
     * Intended for updating the rubric graph, but could be used elsewhere I guess.
     *
     * @param options
     */
    $.fn.fractionDisplay = function(options) {
        options = options || {};

        var config = init(options, $(this));

        setupRedraw();

        /**
         * Init
         *
         * @param options
         * @param element
         * @returns {*}
         */
        function init(options, element) {
            var defaults = {
                element: element,

                /* Selector for inputs
                 */
                inputs: ':radio',

                /* Selector to put the value in
                 */
                displaySelector: false,

                /* Does the gradient get updated too?
                 */
                updateGradient: true
            };
            return $.extend({}, defaults, options);
        }

        /**
         * Set up redraw activators
         */
        function setupRedraw() {
            var display = $(config.displaySelector);
            if (display.length) {
                var max         = 0;
                var inputs      = $(config.inputs);
                var inputValues = {};

                // Calculate a max value
                inputs.each(function() {
                    var name = this.name;
                    var val  = Number(this.value);
                    if (!isNaN(val)) {
                        if (!(name in inputValues)) {
                            inputValues[name] = [];
                        }
                        inputValues[name].push(val);
                    }
                });
                $.each(inputValues, function(i, v) {
                    max += Math.max.apply({}, v);
                });

                var redraw = function() {
                    // Which ones are selected?
                    var total = 0;
                    inputs.filter(':checked').each(function() {
                        var val = Number(this.value);
                        if (!isNaN(val)) {
                            total += val;
                        }
                    });
                    display.text(String(total) + ' / ' + String(max));

                    // Change the gradient style too
                    if (config.updateGradient) {
                        display
                            .removeClass('gradient-0 gradient-1 gradient-2 gradient-3 gradient-4 gradient-5 gradient-6 gradient-7 gradient-8 gradient-9 gradient-10')
                            .addClass('gradient-'+String(Math.ceil(10*total/max)));
                    }
                };
                redraw();
                inputs.change(redraw);
            }
        }
    }
}(jQuery));