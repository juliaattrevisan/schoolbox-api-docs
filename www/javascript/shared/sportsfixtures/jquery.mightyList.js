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

                /* When deleting, this is the param containing the
                 * deleted item's id - used asynchronously
                 */
                deleteKey: 'id',

                /* Empty State element
                 * Shown when there are no items
                 */
                emptyState: $('.empty-state')
            };

            return $.extend(defaults, options);
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
                if ($.trim(element.val())) {

                    /* Parent container for this row
                     */
                    var container = element.parents('[data-item]');

                    /* Data package
                     */
                    var data = {
                        entityId: container.attr('data-item'),
                        name: element.val()
                    };

                    /* Indicator
                     */
                    var indicator = container.find('[data-save-item]');

                    indicator.labelActivate({
                        text: 'Saving'
                    });

                    $.ajax({
                        type: "POST",
                        url:  container.find('[data-action]').attr('data-action'),
                        data: data,
                        success: function (newItem) {

                            /* Update all the links, controls and identifiers when you spawn new rows
                             * This is so their controls will work correctly after persisting
                             */
                            container.attr('data-item', newItem.id);
                            container.find('[data-delete]').attr('href', newItem.delete);
                            container.find('[data-action]').attr('data-action', newItem.update);

                            indicator.labelTimeout({
                                text: 'Saved',
                                css: 'success'
                            });

                            /* Spawn a new row automatically if there are no
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
                var container = element.parents('[data-item]');

                /* Just remove unsaved elements
                 */
                if (container.attr('data-item') == '') {
                    container.remove();

                    refreshEmptyStates();
                }
                /* Otherwise confirm deletion
                 */
                else {
                    $(this).confirmDialog({
                        text: 'Are you sure you want to delete ' + container.find('[data-item-element]').val() + '?',
                        confirmEvent: function () {
                            var data = [];

                            data[config.deleteKey] = element.attr('data-delete');

                            $.post(element.attr('href'), data, function() {
                                container.remove();

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
