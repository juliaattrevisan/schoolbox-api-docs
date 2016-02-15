/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2016, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
;(function ($, window, document, undefined) {

    var pluginName = 'rubricEditable';

    var defaults = {

        hideHorizontalClass: 'hide-h',
        hideVerticalClass: 'hide-v',
        inputSelector: 'input[type=text],textarea',
        levelLimit: 0,
        url: '',
        swap: function (move, first, second) {
            first[move](second);
        },
        rubricId: null,
        formField: 'rb'

};

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

            if ($(this).data('spawn') == 'level') {
                rubricEditable.addLevel();
            }

        });

        $rb.off('blur keydown', '[data-spawn]');
        $rb.on('blur keydown', '[data-spawn]', function (e) {

            // ignore anything except hitting return
            if (e.type == 'keydown' && e.keyCode != 13) {
                return true;
            }

            var $input = $(this),
                type   = $input.data('spawn'),
                name   = this.value ? this.value.trim() : '',
                $row, groupId;

            // prevent blank, but also prevent double-fire from keydown to blur
            if (name.length == 0 || $input.prop('disabled')) {
                return true;
            }

            $row = $input.closest('tr');

            groupId = $row.data('group');

            if (typeof groupId != 'undefined') {
                groupId = groupId.split('-')[2];
            } else {
                groupId = null;
            }

            // prevent mods
            $input.val('Creating "' + name + '"...').prop('disabled', true);

            var payload = {
                type: type,
                rubricId: opts.rubricId,
                groupId: groupId,
                name: name,
                levelCount: $rb.children('thead').find('input').length
            };

            if (type == 'criterion') {
                payload.groupName = $rb.find('input[name="' + ( opts.formField + '[' + [ payload.rubricId, 'group', groupId, 'name' ].join('][') + ']' ) + '"]').val();
                payload.folderId  = $('input[name=category]').val();
            }

            $.post(opt.url, payload, function (data) {

                var $data = $(data);

                // simple case: prepend the row
                $row.before($data);

                // clear and re-enable the input
                $input.val('').prop('disabled', false);

                if (type == 'criterion') {
                    // just gotta extend that rowspan
                    var $span = $row.parent().children('[data-group="'+$row.data('group')+'"][data-groupname]').children('[rowspan]');
                    $span.attr('rowspan', parseInt($span.attr('rowspan')) + 1);

//                    $data.find('[type=checkbox]').prop('checked', true).trigger('change');
                }

                $rb.sequencer('refresh');
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

            // get mah siblangs
            var $areas = $(this).closest('tr').find('textarea');

            var h = 0;

            $areas.each(function() {
                this.style.height = 'auto';
                h = Math.max(h, this.scrollHeight - parseInt($(this).css('paddingTop')) - parseInt($(this).css('paddingBottom')));
            });

            $areas.height(h);
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
                var crit = $row.find('input[data-criterion-name]').data('criterion-name');

                var $textarea = $row.children('td').eq(columnIndex).children('textarea');

                $textarea.attr('placeholder', 'Descriptor for ' + levelText + ' ' + crit);

            });

        });

        // effect these listeners
        $rb.find('input[data-level]').trigger('change');
        $rb.find('textarea').trigger('keyup');

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

                if (axis == 'horizontal') {
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
     * @param {Number} [descriptorValue]
     */
    RubricEditable.prototype.addLevel = function (levelName, descriptorValue) {

        // allow undefined
        levelName       = levelName || '';
        descriptorValue = descriptorValue || '';

        var $rb = $(this.element),
            opt = this.options;

        // determine the new index
        var nextIndex = $rb.find('th[data-sequencer-group=levels]').length;

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
                var inputs = $clone.addClass(opt.hideHorizontalClass).find(opt.inputSelector);

                // assign levelname by default
                inputs.val(levelName);

                // override to the value if this is a value field
                inputs.filter('input.rb-value').val(descriptorValue);

                // rename inputs to accommodate new level "identity"
                inputs.each(function () {

                    var split = this.name.split(/]\[/);

                    // increment the level index in the name (second-last index)
                    split[split.length - 2] = nextIndex;

                    this.name = split.join('][');

                });

                $td.after($clone);

            }

        });

        // aaand meaningful-transition out
        $rb.find('td.' + opt.hideHorizontalClass + ', th.' + opt.hideHorizontalClass).removeClass(opt.hideHorizontalClass);

        // focus on the newly-created level input
        var $levels = $rb.children('thead').find('input[data-level]');
        $levels.eq(-1).focus();

        // permit up to 11 levels
        if (opt.levelLimit > 0 && $levels.length >= opt.levelLimit) {
            $(this).detach();
        }

        // refresh the sequencer
        $rb.sequencer('refresh');
    };


    $.fn[pluginName] = function (options) {
        return this.each(function () {
            if (!$.data(this, 'plugin_' + pluginName)) {
                $.data(this, 'plugin_' + pluginName, new RubricEditable(this, options));
            }
        });
    }

})(jQuery, window, document);
