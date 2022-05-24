/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
;(function ($, window, document, undefined) {

    var pluginName = 'rubricEditable';

    var defaults = {

        hideHorizontalClass: 'hide-h',
        hideVerticalClass: 'hide-v',
        inputSelector: 'input[type=text],input[type=tel],textarea',
        levelLimit: 0,
        url: '',
        swap: function (move, first, second) {
            first[move](second);
        },
        afterApplyResultSet: $.noop,
        rubricId: null,
        formField: 'rb',
        localisedGroupName: 'Capability',
        localisedCriterionName: 'Indicator',
};

    /**
     * maxCapabilityLength/maxIndicatorLength is 900, due to the way browsers look at maxlength as character lenght, but mysql table looks at char byte lenght
     * so if there are bullet points in the textbox they count as 3 characters, and will cause issues, so lets work on 900 as the limit, and we shouldn't hit the db max...
     */
    let maxCapabilityLength = 900, maxIndicatorLength = 900;


        function RubricEditable(element, options) {
        this.element = element;
        this.options = $.extend(true, {}, defaults, options);

        // ensure an ID is set
        if (this.options.rubricId === null) {
            this.options.rubricId = this.element.id;
        }

        this.listen();
    }

    /**
     * Clear and assign all relevant listeners
     */
    RubricEditable.prototype.listen = function () {
        var rubricEditable = this;
        var $rb = $(this.element);
        var opt = this.options;

        $rb.off('click', '[data-spawn]');
        $rb.on('click', '[data-spawn]', function (e) {
            e.preventDefault();

            if ($(this).data('spawn') === 'level') {
                rubricEditable.addLevel();
            }

        });

        $rb.off('blur keydown', '[data-spawn]');
        $rb.on('blur keydown', '[data-spawn]', function (e) {

            // ignore all key presses except for a non-shift return (ie shift+return is ignored)
            if (e.type === 'keydown' && (e.keyCode !== 13 || e.shiftKey)) {
                return true;
            }

            var $input = $(this),
                type   = $input.data('spawn'),
                name   = this.value ? this.value.trim() : '',
                $toastFeedback = $('[data-relation="create-response"]'),
                $row,
                groupIdentifier,
                groupIndex,
                groupId,
                $groupIdInput;


            // prevent blank, but also prevent double-fire from keydown to blur
            if (name.length === 0 || $input.prop('disabled')) {
                return true;
            }

            $row = $input.closest('tr');

            groupIdentifier = $row.data('group');

            if (typeof groupIdentifier !== 'undefined') {
                // This is creating a new indicator within an existing capability
                groupIndex    = groupIdentifier.split('-')[2];
                $groupIdInput = $rb.find('[data-group="'+groupIdentifier+'"][data-groupname] input[data-group-id]');
                groupId       = $groupIdInput.val();
            } else {
                // This is creating a new capability
                groupIndex    = $rb.find('[data-group][data-groupname]').length;
                $groupIdInput = $();
                groupId       = null;
            }

            var message = 'Creating "' + name + '"...';
            $toastFeedback.toastActivate({text: message});

            /*
             * Ensure user is not trying to add a duplicate group. The server-side validation will prevent
             * duplicate criterion, but cannot prevent duplicate groups as they are not persisted until a indicator
             * name is entered.
             */
            if ('group' === type) {
                var normalisedNewGroup = normaliseForComparison(name);
                var $groups =  $('[data-groupname]');
                for (var i = 0; i < $groups.length; i++) {
                    var existingGroup = $($groups.get(i)).data('groupname').toString();
                    if(normalisedNewGroup === normaliseForComparison(existingGroup)){
                        $toastFeedback.toastTimeout({text: opt.localisedGroupName + ' name already exists!', css: 'alert'});
                        $input.val('');
                        return false;
                    }
                }
            }

            // prevent mods
            $input.val(message).prop('disabled', true);

            // Pass in group index too
            var payload = {
                type: type,
                rubricId: opt.rubricId,
                groupId: groupId,
                groupIndex: groupIndex,
                name: name,
                levelCount: $rb.children('thead').find('input[data-level]').length,
                folderId: $('input[name=folderId]').val(),
                markPerCapability: $('#markPerCapability').prop('checked')
            };

            if ('criterion' === type) {
                payload.groupName = $rb.find(
                    '[name="'
                    + ( opt.formField + '[' + [ payload.rubricId, 'group', groupIndex, 'name' ].join('][') + ']' )
                    + '"]'
                ).val();
            }

            $.post(opt.url, payload)
                .done(function (data) {
                    var $data = $(data);

                    // simple case: prepend the row
                    $row.before($data);

                    var cellTypeName = '';

                    if ('criterion' === type) {
                        cellTypeName = opt.localisedCriterionName;

                        // just gotta extend that rowspan
                        var $span = $row.parent().children('[data-group="' + $row.data('group') + '"][data-groupname]').children('[rowspan]');
                        $span.attr('rowspan', parseInt($span.attr('rowspan')) + 1);

                        // and show the sequencer
                        $span.find('[data-sequencer-v]').removeClass('hide');

                        // And apply the group ID from this element to the new one
                        // find data-sequencer-group=something
                        // update its ID to whatever is in this ID
                        $groupIdInput.val($data.find('input[data-group-id]').val());

                    } else {
                        cellTypeName = opt.localisedGroupName;
                    }
                    $toastFeedback.toastTimeout({text: cellTypeName + ' created!', css: 'success'});

                    $rb.sequencer('refresh');

                    //re start the onchange maxlength check for new objects....
                    $('[data-group-edit-text]').on('change', (event) => {
                        event.target.setAttribute('maxlength', maxCapabilityLength);
                    });
                    $('[data-criterion-text]').on('change', (event) => {
                        event.target.setAttribute('maxlength', maxIndicatorLength);
                    });
                })
                .fail(function (data){
                    $toastFeedback.toastTimeout({text: data.responseText, css: 'alert'});
                })
                .always(function(){
                    // clear and re-enable the input
                    $input.val('').prop('disabled', false);
                });

            // probability is high the user pressed return, so prevent form submission
            e.preventDefault();
            e.stopPropagation();
            return false;
        });

        $rb.off('change', '[type=checkbox]');
        $rb.on('change', '[type=checkbox]', function () {

            var $input  = $(this),
                $cells  = $input.closest('tr').children('td'),
                checked = $input.prop('checked');

            if (checked) {
                $cells.removeClass(opt.hideVerticalClass).find(opt.inputSelector).prop('disabled', false);
            } else {
                $cells.addClass(opt.hideVerticalClass).find(opt.inputSelector).prop('disabled', true);
            }

        });

        $rb.off('focus keyup paste', 'textarea');
        $rb.on('focus keyup paste', 'textarea', function () {

            // get mah row, and resize its inputs
            rubricEditable.resizeDescriptorTextInputs($(this.closest('[data-group]')));
        });

        $rb.off('click', 'td.' + opt.hideVerticalClass);
        $rb.on('click', 'td.' + opt.hideVerticalClass, function () {

            var $cell     = $(this),
                $check    = $cell.closest('tr').find('input[type=checkbox]'),
                $textarea = $cell.find('textarea');

            $check.prop('checked', true).trigger('change');
            $textarea.focus().trigger('focus');
        });

        $rb.off('change', 'th [data-level]');
        $rb.on('change', 'th [data-level]', function () {

            // locate all descriptors in column and update text

            var $level      = $(this);
            var levelText   = $level.val();
            var columnIndex = $level.closest('th').index() - 2;

            $rb.children('tbody').children('[data-group]').each(function () {

                var $row = $(this);
                var crit = $row.find('[data-criterion-name]').data('criterion-name');

                var $textarea = $row.children('td').eq(columnIndex).children('textarea');

                $textarea.attr('placeholder', 'Descriptor for ' + levelText + ' ' + crit);

            });

        });

        // effect these listeners
        $rb.find('input[data-level]').trigger('change');
        $rb.find('[data-group]').each(function() {
            rubricEditable.resizeDescriptorTextInputs($(this));
        });

        $rb.sequencer({
            swap: function (axis, first, second) {

                var move = first.index() > second.index() ? 'after' : 'before';

                // sigh... special case for moving groups
                if (first.filter('[data-groupname]').length) {

                    // figure out what we're moving
                    var firstElem   = $(this).find('[data-group="' + first.data('group')  + '"]').eq(first.index() > second.index() ? -1 : 0);
                    var secondGroup = $(this).find('[data-group="' + second.data('group') + '"]');

                    // move the thing! and... the other thing! move it!
                    opt.swap(move, firstElem, secondGroup);

                    return;
                }

                if (axis === 'horizontal') {
                    // horizontal swap

                    var rows = $(this).children('tbody').children('[data-sequencer-group]');

                    var firstCells  = rows.children(':nth-child(' + first.index()  + ')');
                    var secondCells = rows.children(':nth-child(' + second.index() + ')');

                    // iterate manually
                    for (var i = 0; i < firstCells.length; ++i) {
                        // too abstract? :P
                        opt.swap(move, $(firstCells[i]), $(secondCells[i]));
                    }
                }

                // finally, swap the elements themselves
                opt.swap(move, first, second);

            }
        });

    };


    /**
     * Adds a level to the grid, on the right-hand side
     * @param {String} [levelName]
     * @param {*} [descriptorValue]
     */
    RubricEditable.prototype.addLevel = function (levelName, descriptorValue) {

        // allow undefined
        levelName       = levelName || '';
        descriptorValue = typeof descriptorValue === 'undefined' ? '' : descriptorValue;

        var $rb = $(this.element),
            opt = this.options;

        // prevent programmatic addition of too-many-levels
        if (opt.levelLimit > 0 && $rb.children('thead').find('input[data-level]').length >= opt.levelLimit) {
            return false;
        }

        // determine the new index
        // Need the maximum index + 1, not just the count
        var nextIndex = 0;
        $rb.find('th[data-sequencer-group=levels] input[data-level]').each(function(i, v) {
            var thisIndex = v.name.match(/^rb\[\d+]\[level]\[(\d+)]\[name]$/);
            if (thisIndex) {
                nextIndex = Math.max(nextIndex, thisIndex[1]);
            }
        });
        nextIndex += 1;

        // iterate all rows
        $rb.find('tr').each(function () {
            // locate the second-from-right-most cell, and replicate it

            var $row = $(this);

            var $cells = $row.children('td,th');

            var $last = $cells.eq(-1);

            // don't replicate, just extend the colspan
            if ($last.attr('colspan')) {
                $last.attr('colspan', parseInt($last.attr('colspan')) + 1);

            } else {
                var $td = $cells.eq(-2);

                var $clone = $td.clone();

                // start hidden away so we can all slide out together
                var inputs = $clone.addClass(opt.hideHorizontalClass).find(opt.inputSelector + ', input[type="hidden"]');

                // assign levelname by default
                inputs.val(levelName);

                // override to the value if this is a value field
                inputs.filter('[data-descriptor-value]').val(descriptorValue);

                // ID for added levels and descriptors must always be empty
                inputs.filter('[data-id]').val('');

                // rename inputs to accommodate new level "identity"
                inputs.each(function () {

                    var split = this.name.split(/]\[/);

                    // increment the level index in the name (second-last index)
                    split[split.length - 2] = nextIndex;

                    this.name = split.join('][');

                    var $elem = $(this);
                    var attr = $(this).attr('data-descriptor-value');
                    if(attr !== undefined && attr !== false){
                        $elem.attr('data-descriptor-level-index', nextIndex);
                    }
                });

                $td.after($clone);

            }

        });

        // aaand meaningful-transition out
        $rb.find('td.' + opt.hideHorizontalClass + ', th.' + opt.hideHorizontalClass).removeClass(opt.hideHorizontalClass);

        // focus on the newly-created level input
        var $levels = $rb.children('thead').find('input[data-level]');
        $levels.eq(-1).focus();

        // Once level is added, scroll to it
        $rb.closest('.scrollable').scrollTo(
            $rb.find('[data-spawn="level"]'),
            {
                scrollX: true,
                scrollY: false,
                offsetLeft: 0,
                duration: 0
            }
        );

        // permit up to 11 levels
        if (opt.levelLimit > 0 && $levels.length >= opt.levelLimit) {
            $levels.find('[data-spawn]').addClass('hide');
        }

        // refresh the sequencer
        $rb.sequencer('refresh');

        return true;
    };

    /**
     * How many levels are there in this rubric?
     *
     * @returns int
     */
    RubricEditable.prototype.getLevelCount = function () {
        return $(this.element)
            .find('tr')
            .eq(0)
            .find('[data-sequencer-group="levels"]').length;
    };

    /**
     * Delete the nominated column, zero-indexed from the first LEVEL column
     *
     * @param {Number} index
     */
    RubricEditable.prototype.deleteLevel = function (index) {

        var $rb = $(this.element),
            opt = this.options;

        var rows = $rb.find('tr');

        var levelCount = this.getLevelCount();

        // how can it be less than zer0? this is a just-in-case...
        if (levelCount <= 0) {
            throw "No levels exist, nothing to delete";
        }

        // disallow removal of the last level (because we lose our template)
        if (levelCount === 1) {
            throw "Only 1 level, cannot remove it";
        }

        // bounds-check
        if (index < 0 || index >= levelCount) {
            throw "Index out of bounds: " + index + " must be between 0 and " + (levelCount - 1);
        }

        // ok... NOW we can delete the level given. we count backwards from the end, to accommodate colspans and such
        index -= (levelCount + 1);

        rows.each(function () {
            var $row = $(this);

            var $cells = $row.children('td,th');

            // either the column given, or the last (for colspan)
            var $selected = $($cells.eq(index)[0] || $cells.eq(-1)[0]);

            if ($selected.attr('rowspan')) {
                // say nothing.

            } else if ($selected.attr('colspan')) {
                $selected.attr('colspan', parseInt($selected.attr('colspan')) - 1);

            } else {
                $selected.detach();
            }

        });

        var $levels = $rb.children('thead').find('input[data-level]');

        // permit up to opt.levelLimit levels
        if (opt.levelLimit === 0 || $levels.length < opt.levelLimit) {
            $levels.find('[data-spawn]').removeClass('hide');
        }

        // refresh the sequencer
        $rb.sequencer('refresh');

        // Inputs for levels and descriptors need to be renumbered after deleting a row.
        this.renumberColumnInputs();

        return true;
    };

    /**
     * Fixes numbering of inputs of all columns.
     *
     * Levels and descriptors (text and points) are renumbered.
     */
    RubricEditable.prototype.renumberColumnInputs = function()
    {
        var $rb = $(this.element),
            rows = $rb.find('tr');

        // Re-number all the IDs, levels, descriptors and values in the rubric grid
        rows.each(function() {
            var $row = $(this);

            // Renumber IDs
            $row.find('[data-id]').each(function(i) {
                this.name = this.name.replace(/\[\d+]\[id]$/, '[' + String(i) + '][id]');
            });

            // Renumber levels
            $row.find('[data-level]').each(function(i) {
                this.name = this.name.replace(/\[\d+]\[name]$/, '[' + String(i) + '][name]');
            });

            // Renumber descriptor texts
            $row.find('[data-descriptor-text]').each(function(i) {
                this.name = this.name.replace(/\[\d+]\[text]$/, '[' + String(i) + '][text]');
            });

            // Renumber descriptor values
            $row.find('[data-descriptor-value]').each(function(i) {
                this.name = this.name.replace(/\[\d+]\[points]$/, '[' + String(i) + '][points]');
                $(this).attr('data-descriptor-level-index', String(i));
            });
        });
    };

    /**
     * Replace the existing levels and descriptors with the array given
     *
     * @param {Array} options
     */
    RubricEditable.prototype.applyResultSet = function (options) {
        var rubricEditable = this;

        // delete all but the first level
        var i = 0;
        while (i++ < 100) {
            var levelCount = this.getLevelCount();
            if (levelCount <= 1) {
                break;
            }
            this.deleteLevel(levelCount - 1);
        }

        // Add each level.
        // After adding the first level in this list - delete the first
        // (now unneeded) level, so that there's always room for adding
        // the maximum number of levels.
        var isFirstDeleted = false;
        $.each(options, function (levelValue, levelName) {
            rubricEditable.addLevel(levelName, levelValue);
            if (!isFirstDeleted) {
                isFirstDeleted = true;
                rubricEditable.deleteLevel(0);
            }
        });

        this.options.afterApplyResultSet.call(this);
    };

    /**
     * Resize all descriptor text inputs within the row.
     *
     * @param row
     */
    RubricEditable.prototype.resizeDescriptorTextInputs = function (row) {
        // get mah inputs
        var $areas = row.find('[data-descriptor-text]');

        var h = 0;

        $areas.each(function() {
            this.style.height = 'auto';
            h = Math.max(h, this.scrollHeight - parseInt($(this).css('paddingTop')) - parseInt($(this).css('paddingBottom')));
        });

        $areas.height(h);
    };


    $.fn[pluginName] = function (options) {

        var plugin = 'plugin_' + pluginName;

        if (typeof options === 'object' || typeof options === 'undefined') {
            // if this is configuration
            return this.each(function () {
                if (!$.data(this, plugin)) {
                    // initialise if not already
                    $.data(this, plugin, new RubricEditable(this, options));

                } else {
                    // just update existing config
                    setOptions.call($.data(this, plugin), options);
                }
            });

        } else if (typeof options === 'string') {
            // if this is a function request
            var rtn = [];

            this.each(function () {
                var rbe = $(this).data(plugin);

                if (typeof rbe === 'undefined') {
                    throw "RubricEditable not initialised";
                }

                // return the thing I'm pointing at
                if (options === 'next' || options === 'target') {
                    rtn.push($('[data-mage="' + $(rbe.element).data('mage-next') + '"]'));
                }

                // attempt a method call
                if (options in rbe) {
                    rtn.push(rbe[options].call(rbe));
                }
            });

            return $($.map(rtn, function (el) { return $.makeArray(el) } ));
        }
    };

    /**
     * Expects to be applied to the Mage object (but is not a prototype method so as to keep it private).
     *
     * @param {Object} opts
     */
    function setOptions(opts) {

        // if options are already defined, merge with that, otherwise defaults
        var merge = typeof this.options === 'undefined' ? defaults : this.options;

        // and apply the merged set
        this.options = $.extend(true, {}, merge, opts);
    }

    /**
     * Returns the normalised string for comparison to avoid duplicates.
     *
     * MUST use same logic as RubricMapper->normaliseForComparison()
     *
     * @param {string} string
     * @return string
     */
    function normaliseForComparison(string)
    {
        // Ensure var really is a string before performing string operations
        return string.toString().replace(/\s+/g, ' ').trim().toLowerCase();
    }

})(jQuery, window, document);
