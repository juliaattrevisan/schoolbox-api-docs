/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2015, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
;
(function ($, window, document, undefined) {

    var pluginName = 'infiniscroll',
        noop = function () {},
        defaults = {
            threshold: 0.2,
            maxPages: 3,
            url: '',
            pageParam: 'p',
            filterParam: 'filter',
            onUpdate: noop
        };

    /**
     * @param {string} param
     * @param {*}      alt
     * @returns {string}
     */
    function queryStringValue(param, alt)
    {
        if (typeof alt == 'undefined') {
            alt = '';
        }

        var result = window.location.search.match(new RegExp(param + '=([^&]*)'));

        return result ? result[1] : alt;
    }

    /**
     * Determine which is the next page to fetch
     *
     * @param {number} increment
     * @returns {number}
     */
    Infiniscroll.prototype.pageToFetch = function (increment)
    {
        if (increment > 0) {
            // are we about to increment/decrement to a place we already know doesn't exist?
            if (typeof this.lastPage != 'undefined' && this.currentLastPage >= this.lastPage) {
                // if we know we're on the first page, bug out
                // console.log('We have the last page, not going down any further');
                return -1;
            }

        } else {
            // are we about to increment/decrement to a place we already know doesn't exist?
            if (this.currentFirstPage == 1 || (typeof this.firstPage != 'undefined' && this.currentFirstPage <= this.firstPage)) {
                // if we know we're on the first page, bug out
                // console.log('We have the first page, not going up any further');
                return -1;
            }

        }

        var targetPage = (increment > 0 ? this.currentLastPage : this.currentFirstPage) + increment;

        if (this.pagesFetched.indexOf(targetPage) > -1) {
            // we've already fetched this page
            // console.log('Avoiding fetch: already got page ' + targetPage);
            return -1;
        }

        return targetPage;
    };

    /**
     * @param element
     * @param {object} options
     * @constructor
     */
    function Infiniscroll(element, options) {
        var self   = this,
            $elem  = $(element),
            $inner = $elem.children().eq(0);

        this.element          = element;
        this.options          = $.extend(true, {}, defaults, options);
        this.prevPosition     = 0;     // where we were previously
        this.fetchInProgress  = false; // are we currently fetching a page?
        this.firstPage        = undefined;
        this.lastPage         = undefined;
        this.currentFirstPage = queryStringValue(this.options.pageParam, 1);
        this.currentLastPage  = this.currentFirstPage;
        this.pagesFetched     = [ this.currentFirstPage ];

        // apply the page number to the content we already have
        $inner.children().attr('data-page', this.currentFirstPage);

        // set up the listener
        $(this.element).on('scroll', function () {

            var opt           = self.options,
                fromTop       = $elem.scrollTop(),
                innerHeight   = $inner.height(),
                wrapperHeight = $elem.height(),
                fromBottom    = innerHeight - wrapperHeight - fromTop,
                relevantValue, percentageDistance, increment, params, filterValue, targetPage;

            if (fromTop == self.prevPosition || self.fetchInProgress) {
                // if no scroll or already fetching, do nothing
                return true;
            }

            if (fromTop > self.prevPosition) {
                // heading toward the bottom, use these values
                relevantValue = fromBottom;
                increment     = 1;

            } else {
                // heading toward the top, use these values
                relevantValue = fromTop;
                increment     = -1;

            }

            // keep track of where we are
            self.prevPosition = fromTop;

            // how far away from our relevant face are we?
            percentageDistance = relevantValue / innerHeight;

            // if we're withing the threshold, ajax another page
            if (percentageDistance <= opt.threshold) {

                targetPage = self.pageToFetch(increment);

                // no page to fetch
                if (targetPage == -1) {
                    return true;
                }

                params = {};
                params[opt.pageParam] = targetPage;

                filterValue = queryStringValue(opt.filterParam, null);

                if (filterValue) {
                    params[opt.filterParam] = filterValue;
                }

                // block other requests
                self.fetchInProgress = true;

                // fetch the next/previous page
                $.get(opt.url, params, function (data) {

                    var pageToPop, indexToPop, DOMToPop, fn, $keepInView,
                        wrapTop, listTop, kievTop, newListTop, newKievTop, dKievTop;

                    // set up a hook we can scroll to once the new data has been jammed in
                    $keepInView = $inner.children().filter(function () {
                        return $(this).offset().top > $elem.offset().top;
                    }).eq(0);

                    // calculate original keepInView position
                    wrapTop = $elem.offset().top;
                    listTop = $inner.offset().top - wrapTop;
                    kievTop = $keepInView.offset().top - listTop;


                    // we need to stamp the returned data with the page number
                    data = $(data).attr('data-page', targetPage);

                    // special delivery! where am I putting this? just over here? ok!
                    if (increment > 0) {
                        $inner.append(data);
                        // console.log('Appended page ' + targetPage);

                        // update our know-last
                        self.currentLastPage = targetPage;
                    } else {
                        $inner.prepend(data);
                        // console.log('Prepended page ' + targetPage);

                        // update our know-last
                        self.currentFirstPage = targetPage;
                    }


                    // and add the page to the list of pages fetched
                    self.pagesFetched.push(targetPage);

                    // dump out stuff we can't see
                    if (self.pagesFetched.length > opt.maxPages) {

                        // which end are we popping?
                        fn = increment > 0 ? Math.min : Math.max;

                        // the page to pop
                        pageToPop = fn.apply(null, self.pagesFetched);

                        // find the page in the array
                        indexToPop = self.pagesFetched.indexOf(pageToPop);

                        // find the corresponding log records
                        DOMToPop = $inner.children('[data-page=' + pageToPop + ']');

                        // chop it!!
                        // TODO cache rather than dump it out completely
                        DOMToPop.detach();
                        self.pagesFetched.splice(indexToPop, 1);

                        // console.log(['Dumped out page', pageToPop].join(' '));
                        // console.log('Current pages indexed', pagesFetched);

                        // pull the bounds in by 1, in whichever direction we're travelling
                        if (increment > 0) {
                            ++self.currentFirstPage;
                        } else {
                            --self.currentLastPage;
                        }
                    }

                    opt.onUpdate.call($elem);

                    // calculate our new scroll position
                    newListTop = $inner.offset().top - wrapTop;
                    newKievTop = $keepInView.offset().top - newListTop;
                    dKievTop   = kievTop - newKievTop;

                    // calculate the new scroll position
                    var scrollPosition = (0 - listTop) - dKievTop;

                    // make sure our scroll-to element still *looks* like it's where it was, even though the DOM has shifted under its feet
                    $elem.scrollTop(scrollPosition);

                }).fail(function () {

                    // so that page didn't exist: depending on which way we were travelling, institute bounds-check for that direction
                    if (increment > 0) {
                        self.lastPage = self.currentLastPage;
                    } else {
                        self.firstPage = self.currentFirstPage;
                    }

                }).always(function () {
                    // prevent permablock of future requests
                    self.fetchInProgress = false;
                });
            }

        });


    }


    $.fn[pluginName] = function (options) {
        return this.each(function () {
            if (!$.data(this, 'plugin_' + pluginName)) {
                $.data(this, 'plugin_' + pluginName, new Infiniscroll(this, options));
            }
        });
    }

})(jQuery, window, document);
