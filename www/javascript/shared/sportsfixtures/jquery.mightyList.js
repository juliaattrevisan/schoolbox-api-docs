(function() {

    /**
     * Plugin
     *
     * @param options
     */
    $.fn.mightyList = function(options) {
        options = options || {};

        var config = init(options, $(this));

        setupNewItems();
        setupPersist();
        setupDelete();

        /* Initial state
         */
        refreshEmptyStates();

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

                /* The entity name of the things you are managing
                 * e.g. Status. Appears in confirmDialogs
                 */
                name: 'Item',

                /* The input element containing the unique id
                 * of each row item that exists
                 */
                identifierField: $('input[name=id]'),

                /* When deleting, this is the param containing the
                 * deleted item's id - used asynchronously
                 */
                deleteKey: 'id',

                /* Empty State element
                 * Shown when there are no items
                 */
                emptyState: $('.empty-state')
            };

            var derived = {
            };

            return $.extend(defaults, derived, options);
        }

        /**
         * Spawn clone when trying to add new item
         * If you already have an empty row, prompt to use it
         */
        function setupNewItems() {
            $(config.element).on('click', '[data-item-add]', function(event) {
                if (canSpawnRow()) {
                    spawnNewRow();
                }
                else {
                    $(config.element).find('input[name=name]').last().focus();
                }
            });
        }

        /**
         * Determine whether the last list item is empty
         */
        function canSpawnRow() {
            return ($(config.element).find('input[name=name]').last().val() != '');
        }

        /**
         * Submit asynchronously on blur
         */
        function setupPersist() {
            $(config.element).on('blur', '[data-item-element]', function(event) {
                var element = $(this);

                /* Only execute if there is data to send
                 */
                if (element.val()) {
                    var identifier = element.attr('data-item-element');

                    /* Find destination route
                     */
                    var form = $(config.element).find('[data-item-form='+identifier+']');
                    var url = form.attr('data-action');

                    /* Data package
                     */
                    var data = {
                        id: element.attr('data-item-element'),
                        name: element.val()
                    };

                    /* Parent container for this row
                     */
                    var parent = element.parents('[data-item]');

                    /* Indicator
                     */
                    var indicator = parent.find('[data-save-item]');

                    indicator.labelActivate({
                        text: 'Saving'
                    });

                    $.ajax({
                        type: "POST",
                        url:  url,
                        data: data,
                        success: function (newItem) {

                            /* Update all the controls and identifiers
                             */
                            parent.find('[data-item-form]').attr('data-item-form', newItem.id);
                            parent.find('[data-delete]').attr('data-delete', newItem.id).attr('href', newItem.delete);
                            parent.find(config.identifierField).val(newItem.id);
                            element.attr('data-item-element', newItem.id);

                            indicator.labelTimeout({
                                text: 'Saved',
                                css: 'success'
                            });

                            /* Count number of empty rows at bottom
                             * Spawn a new row automatically if there are no
                             * empty ones ready to utilise
                             */
                            if (canSpawnRow()) {
                                spawnNewRow();
                            }
                        },
                        error: function() {
                            indicator.labelTimeout({
                                text: 'Error!',
                                css: 'alert'
                            });
                        }
                    });
                }
            });
        }

        /**
         * Remove asynchronously upon confirm
         */
        function setupDelete() {
            $(config.element).on('click', '[data-delete]', function(event) {
                event.preventDefault();

                var element = $(this);
                var elementContainer = element.parents('[data-item]');

                /* Just remove unsaved elements
                 */
                if (element.attr('data-delete') == '') {
                    elementContainer.remove();

                    refreshEmptyStates();
                }
                /* Otherwise confirm deletion
                 */
                else {
                    $(this).confirmDialog({
                        text: 'Are you sure you want to delete this ' + config.entityName + '?',
                        confirmEvent: function () {
                            var data = [];

                            data[config.deleteKey] = element.attr('data-delete');

                            $.post(element.attr('href'), data, function() {
                                elementContainer.remove();

                                refreshEmptyStates();
                            });
                        }
                    });
                }
            });
        }

        /**
         * Show empty state/hide list when no items
         * Otherwise hide empty state/show list when items exist
         */
        function refreshEmptyStates() {
            var numItems = $(config.element).find('[data-item-list]').find('[data-item]').length;

            if (numItems) {
                $(config.emptyState).addClass('hide');
                $(config.element).find('[data-item-list]').removeClass('hide');
            }
            else {
                $(config.emptyState).removeClass('hide');
                $(config.element).find('[data-item-list]').addClass('hide');
            }
        }

        /**
         * Spawn a new list row
         */
        function spawnNewRow() {
            var clone = $('#item-clone').clone();
            clone.attr('id', '');

            /* Append it to the list
             */
            $(config.element).find('[data-item-list]').append(clone);

            /* Focus it, to save you needing to do it manually
             */
            $(config.element).find('input[name=name]').last().focus();

            refreshEmptyStates();
        }
    }
}(jQuery));
