(function ($j) {
    $j.fn.addSelectAll = function (options) {
        var config = init(options, this);

        addSelectAll();

        /**
         * Init
         *
         * @param options
         * @param element
         * @returns {*|void|Function|extend|extend|extend}
         */
        function init(options, element) {
            var defaults = {
                element: element,		// The checkbox which selects/deselects everything
                source: null			// The element containing all the checkboxes
            };

            return $j.extend(defaults, {}, options);
        }

        /**
         * Add select-all functionality to a checkbox
         */
        function addSelectAll() {
            config.element.click(function () {
                var elements = config.source.find('input[name="selected[]"]');

                if ($j(this).is(':checked')) {
                    elements.prop('checked', true);
                } else {
                    elements.prop('checked', false);
                }
            });
        }
    };

    $j.fn.emailSelected = function (options) {

        var config = init(options, this);

        fetchEmails();

        /**
         * Init
         *
         * @param options
         * @param element
         * @returns {*|void|Function|extend|extend|extend}
         */
        function init(options, element) {
            var defaults = {
                element: element,       // The email form belonging to this plugin instance
                source: null,           // The element containing all the checkboxes
                url: '',                // The url to access, to provide a list of recipient emails
                classCode: '',          // The class code for the current list of people
                target: null            // A jquery-selected element in the form. All the emails get rammed into here
            };

            return $j.extend(defaults, {}, options);
        }

        /**
         * Get a list of checked checkboxes containing externalIds
         */
        function gatherSelectedIds() {
            var ids = [];
            var allSelected = config.source.find('input[name="selected[]"]:checked');

            for (var i = 0; i < allSelected.length; i++) {
                var currentSelected = allSelected[i];
                ids.push(currentSelected.value);
            }

            return ids;
        }

        /**
         * A list of all the emails associated with the selected people
         */
        function fetchEmails() {
            var payload = {
                allIds: gatherSelectedIds(),
                classCode: config.classCode
            };

            $j.post(config.url, payload, function (responseText) {

                // Parse the result into an object

                var allEmails = JSON.parse(responseText);

                // Inject and redirect only if emails were found

                if (allEmails.length) {
                    inject(allEmails);
                    redirect();
                }
                else {
                    config.element.trigger('emailSelected.noEmail');
                }
            });
        }

        /**
         * Inject emails into target form element as a concatenated CSV string
         */
        function inject(allEmails) {
            config.target.val(allEmails.join(','));
        }

        /**
         * Submit the form as originally intended
         */
        function redirect() {
            $j(config.element).unbind('submit');
            config.element.submit();
        }

        return this;
    }
}(jQuery));
