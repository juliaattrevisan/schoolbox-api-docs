(function () {
    /**
     * Plugin
     *
     * @param options
     */
    $.fn.mightyList = function (options) {
        options = options || {};

        var mightyList = this;
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
                deleteKey: "id",

                listSelector: "[data-item-list]",

                /* What am I cloning to create a new row?
                 */
                templateSelector: "#item-clone",

                /* Which element to focus when a new record is spawned
                 */
                focusSelector: '[name="name"]',

                /* Callback when a line-item is reverted to its original state. By default, no effect.
                 */
                onRevert: function (element) {
                    /* noop*/
                },

                /* Callback when a new line-item is spawned. By default, no effect.
                 */
                onCreate: function (element) {
                    /* noop */
                },

                /* Callback when a line-item is modified. By default, persist to server.
                 */
                onModify: function (element, newData) {
                    /* Parent container for this row
                     */
                    var container = element.parents("[data-item]");

                    /* Data package
                     */
                    var data = {
                        entityId: container.attr("data-item"),
                        name: newData,
                    };

                    /* Indicator
                     */
                    var indicator = container.find("[data-save-item]");

                    indicator.toastActivate({
                        text: "Saving",
                    });

                    $.ajax({
                        type: "POST",
                        url: container.find("[data-action]").attr("data-action"),
                        data: data,
                        success: function (newItem) {
                            /* Update all the links, controls and identifiers when you spawn new rows
                             * This is so their controls will work correctly after persisting
                             */
                            container.attr("data-item", newItem.id);
                            container.find("[data-delete]").attr("href", newItem.delete);
                            container.find("[data-action]").attr("data-action", newItem.update);
                            element.attr("data-item-original-value", newData);

                            indicator.toastTimeout({
                                text: "Saved",
                                css: "success",
                            });

                            /* Spawn a new row automatically if there are no
                             * empty ones ready to utilise
                             */
                            if (canSpawnRow()) {
                                spawnNewRow();
                            }
                        },
                        error: function () {
                            indicator.toastTimeout({
                                text: "Error!",
                                css: "alert",
                            });
                        },
                    });
                },

                /* Callback when a line-item is removed. By default, persist to server.
                 */
                onDelete: function (element, container) {
                    $(this).confirmDialog({
                        text: "Are you sure you want to delete " + container.find("[data-item-element]").val() + "?",
                        confirmLabel: "Delete",
                        confirmEvent: function () {
                            var data = [];

                            data[config.deleteKey] = element.attr("data-delete");

                            $.ajax({
                                type: "POST",
                                url: element.attr("href"),
                                data: data,
                                success: function () {
                                    container.remove();
                                },
                            }).fail(function (responseText) {
                                /* Tried to delete an already-deleted item?
                                 * Be compliant and just remove it
                                 */
                                if (responseText.status == 410) {
                                    container.remove();

                                    refreshEmptyStates();
                                }
                            });
                        },
                    });
                },

                /* Empty State element
                 * Shown when there are no items
                 */
                emptyState: ".empty-state",
            };

            return $.extend(defaults, options);
        }

        /**
         * Spawn clone when trying to add new item
         * If you already have an empty row, prompt to use it
         */
        function setupNewItems() {
            $(config.element).on("click", "[data-item-add]", function (event) {
                if (canSpawnRow()) {
                    spawnNewRow();
                } else {
                    $(config.element).find(config.focusSelector).last().focus();
                }
            });
        }

        /**
         * Determine whether the last list item is empty
         */
        function canSpawnRow() {
            return $(config.element).find(config.listSelector).find(config.focusSelector).last().val() != "";
        }

        /**
         * Attach save timeouts to input changes
         */
        function setupPersist() {
            /* Remember original value
             *
             * Compare against this in onChange event to determine whether anything
             * changed and needs to be saved
             */
            $(config.element).on("enter", "[data-item-element]", function () {
                var element = $(this);

                /* Force trim
                 */
                element.val($.trim(element.val()));

                element.attr("data-item-original-value", element.val());
            });

            /**
             * Save typing based inputs on keyup (with a timeout)
             */
            $(config.element).on("keyup change", "[data-item-element]", function (event) {
                var self = this;

                /**
                 * Clear any existing save timeout
                 */
                window.clearTimeout(mightyList.saveTimeout);

                /**
                 * Set a new save timeout
                 */
                mightyList.saveTimeout = window.setTimeout(function () {
                    saveElement(self);
                }, 1000);
            });
        }

        /**
         * Apply modify/revert to element
         * @param element
         */
        function saveElement(element) {
            var $element = $(element);

            /* Force trim
             */
            $element.val($.trim($element.val()));

            /* Only execute if there is data to send
             */
            var newData = $element.val();
            var originalData = $element.attr("data-item-original-value");

            if (newData && newData != originalData) {
                $.when(config.onModify($element, newData)).then(function () {
                    /* Spawn a new row automatically if there are no
                     * empty ones ready to utilise
                     */
                    if (canSpawnRow()) {
                        spawnNewRow();
                    }
                });
            } else {
                $.when(config.onRevert($element)).then(function () {
                    /* Spawn a new row automatically if there are no
                     * empty ones ready to utilise
                     */
                    if (canSpawnRow()) {
                        spawnNewRow();
                    }
                });
            }
        }

        /**
         * Remove asynchronously upon confirm
         */
        function setupDelete() {
            $(config.element).on("click", "[data-delete]", function (event) {
                event.preventDefault();

                var element = $(this);
                var container = element.parents("[data-item]");

                /* Just remove unsaved elements
                 */
                if (container.attr("data-item") == "") {
                    container.remove();

                    refreshEmptyStates();
                } else {
                    /* Otherwise confirm deletion
                     */
                    $.when(config.onDelete(element, container)).then(refreshEmptyStates);
                }
            });
        }

        /**
         * Show empty state/hide list when no items
         * Otherwise hide empty state/show list when items exist
         */
        function refreshEmptyStates() {
            var $element = $(config.element),
                $empty = $element.find(config.emptyState),
                $list = $element.find(config.listSelector),
                numItems = $list.find("[data-item]").length;

            if (numItems) {
                $empty.addClass("hide");
                $list.removeClass("hide");
            } else {
                $empty.removeClass("hide");
                $list.addClass("hide");
            }
        }

        /**
         * Spawn a new list row
         */
        function spawnNewRow() {
            var $element = $(config.element),
                $list = $element.find(config.listSelector),
                clone = $element.find(config.templateSelector).clone(),
                last = config.templateSelector.split(" ").pop(),
                selector = last.match(/([a-z]+)?([^a-z][a-z-_0-9]+)/).pop(),
                sstring = selector.substr(1),
                type = selector[0];

            if (type == "#") {
                // remove id
                clone.removeAttr("id");
            } else if (type == ".") {
                // remove class
                clone.removeClass(sstring);
            } else if (type == "[") {
                // remove attr
                clone.removeAttr(sstring);
            }

            /* Append it to the list
             */
            $list.append(clone);

            /* Focus it, to save you needing to do it manually
             */
            $element.find(config.focusSelector).last().focus();

            refreshEmptyStates();

            config.onCreate(clone);
        }
    };
})(jQuery);
