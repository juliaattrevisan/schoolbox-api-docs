// usage: log('inside coolFunc', this, arguments);
// paulirish.com/2009/log-a-lightweight-wrapper-for-consolelog/
window.log = function f(){
    log.history = log.history || [];
    log.history.push(arguments);
    if(this.console) {
        var args = arguments, newarr;
        args.callee = args.callee.caller;
        newarr = [].slice.call(args);
        if (typeof console.log === 'object') log.apply.call(console.log, console, newarr);
        else console.log.apply(console, newarr);
    }
};

// make it safe to use console.log always
(function(a){function b(){}for(var c="assert,count,debug,dir,dirxml,error,exception,group,groupCollapsed,groupEnd,info,log,markTimeline,profile,profileEnd,time,timeEnd,trace,warn".split(","),d;!!(d=c.pop());){a[d]=a[d]||b;}})
    (function(){try{console.log();return window.console;}catch(a){return (window.console={});}}());


// place any jQuery/helper plugins in here, instead of separate, slower script files.




/*
 plugin: flopsyDropsy()
 ..for dropdown menus. Also works on an iPhone! and keyboard nav1!!

 Tom Gordon
 Alaress
 2011
 www.alaress.com.au
 Requirements;
 Ya gots ta have a minimum of: jQuery 1.6 because of the :focus selector.

 className: Appended to the UL you are applying the plugin to.
 hasChildrenAppend: The html markup applied to the that are parents of another UL eg. <a href="#">WORD <em></em></a> the 'em' would be the additional markup.
 hasChildrenClass: The class added to the LI html markup applied to the links that are parents of another UL
 activeParentClass: A class that appears or disappears on the parent that is being hovered over.
 hasVisibleChildrenClass: This class is applicable for touch devices. It is appended to the LI when the child Ul is visible.
 mouseOverDelay: The delay before a child UL appears.
 animationMethod: The method of animation. This currently accepts slide, fade and none. The default is none.
 animationSpeed: "fast",
 easingMethod: This allows the jQuery easing plug9in to be integrated into the animation effects. You'll need to include it if you want this to work.
 detectBottom: Turn this on to allow the navigation's vertical position to be dependant on the bottom of its parent instead of its top.

 */

(function($) {

    $.fn.flopsyDropsy = function(options) {
        var defaults = {
            className: 'jquery-drop',
            hasChildrenAppend: '<em class="parent-arrow"></em>',
            hasChildrenClass: 'jquery-drop-parent',
            activeParentClass: 'jquery-drop-current',
            hasVisibleChildrenClass: 'jquery-has-visible-children',
            mouseOverDelay: 100,
            animationMethod: null,
            animationSpeed: "fast",
            easingMethod: null,
            detectBottom: false
        },
            settings = $.extend({}, defaults, options);

        function animateDropDown(showOrHide, childElement) {
            if (showOrHide == "show") {
                switch(settings.animationMethod) {
                    case "fade":
                        childElement
                            .stop(true, true)
                            .delay(settings.mouseOverDelay)
                            .fadeIn(settings.animationSpeed, settings.easingMethod);
                        break;
                    case "slide":
                        childElement
                            .stop(true, true)
                            .delay(settings.mouseOverDelay)
                            .slideDown(settings.animationSpeed, settings.easingMethod);
                        break;
                    default:
                        childElement.show();
                }
            } else {
                switch(settings.animationMethod) {
                    case "fade":
                        childElement
                            .stop(true, true)
                            .delay(settings.mouseOverDelay)
                            .fadeOut(settings.animationSpeed, settings.easingMethod);
                        break;
                    case "slide":
                        childElement
                            .stop(true, true)
                            .delay(settings.mouseOverDelay)
                            .slideUp(settings.animationSpeed, settings.easingMethod);
                        break;
                    default:
                        childElement.hide();
                }
            }
        }

        return this.each(function() {
            var $this = $(this);
            $this.addClass(settings.className);
            $this.blur(function() {
                $this.find('ul').hide();
            });
            //tab key functionality..
            $this.keyup(function(e) {
                var keyCode = e.keyCode || e.which;
                if (keyCode == 9) {
                    var focussedParents = $('a:focus').parents('ul');
                    if ($this.find('a').is(':focus')) {
                        if (focussedParents.length == 1) {
                            $this.find('ul').hide();
                        } else if (focussedParents.length == 2) {
                            $this.find('ul ul').hide();
                        }
                        if (($('a:focus').siblings('ul').length > 0)) {
                            $('a:focus').siblings('ul:first').show();
                        }
                    } else {
                        $this.find('ul').hide();
                    }
                }
            });
            $this.find('li').each(function(i) {
                //commonly used elements
                var navItem = $(this);
                var navItemLink = $(this).children('a:first-child');
                var navItemChildUL = $("ul:first", navItem);

                navItemLink.attr('tabindex', i + 1);

                if (navItem.find('ul').length > 0) {
                    navItem.children('a:first-child')
                        .append(settings.hasChildrenAppend);
                    navItem.addClass(settings.hasChildrenClass);
                }


                //This is for touch devices. Note that the click function should
                // only execute if there is no ability to hover
                navItem.click(function() {
                    if (navItem.find('ul').length > 0) {
                        if (!$(this).hasClass(settings.hasVisibleChildrenClass) && (!$(this).hasClass(settings.activeParentClass))) {
                            if (settings.animationMethod == "fade") {
                                navItemChildUL.fadeToggle(settings.animationSpeed);
                            } else if (settings.animationMethod == "slide") {
                                navItemChildUL.slideToggle(settings.animationSpeed);
                            } else {
                                navItemChildUL.toggle();
                            }
                            $(this).addClass(settings.hasVisibleChildrenClass);
                            return false;
                        }
                    }
                });
                navItem.hover(function() {
                    navItem.addClass(settings.activeParentClass);
                    navItemChildUL.removeAttr('style');
                    if (settings.detectBottom) {
                        if (!isScrolledIntoView($("ul:first", navItem))) {
                            navItemChildUL.css({
                                'top': 'auto',
                                'bottom': '0'
                            });
                        }
                    }
                    animateDropDown('show', navItemChildUL);
                    navItem.addClass(settings.activeParentClass);
                }, function() {
                    navItem.removeClass(settings.activeParentClass);
                    animateDropDown('hide', navItemChildUL);
                });
            });
        });
    };
})(jQuery);

/*******

 jQuery prettyFileInput
 Author: Tom Gordon
 Company: Alaress

 + inputHolderClass: this wraps all the appended elements and the file input itself.
 + buttonClass: this is the class for the 'button', the thing underneath the invisible file input. how sneaky!
 + buttonActiveClass: for when a file has been selected.
 + additionalButtonClasses: if you need extra classes on the button whack em in here.
 + defaultText: This is the text on the button

 Modified by Chris
 - removed file holder span
 - moved filename to button
 *******/

(function($) {
    $.fn.prettyFileInput = function(options) {
        var defaults = {
            inputHolderClass: 'file-input',
            buttonClass: 'btn',
            additionalButtonClasses: 'btn-file-input',
            buttonActiveClass: 'btn-file-input-active',
            defaultText: 'Choose file'
        };
        var options = $.extend(defaults, options);



        $('input[type="reset"]').click(function(){
            $button.html(options.defaultText);
        });

        return this.each(function() {

            var obj = $(this);

            obj.wrap('<span class="' + options.inputHolderClass + '"></span>');
            obj.after('<span class="' + options.buttonClass + ' ' + options.additionalButtonClasses + '">' + options.defaultText + '</span>');

            obj.bind('change focus click', function() {

                $val = obj.val();

                valArray = $val.split('\\');
                newVal = valArray[valArray.length - 1];
                $button = obj.siblings('.' + options.buttonClass + '');

                if (newVal !== '') {
                    $button.html(newVal)
                           .addClass(options.buttonActiveClass);
                    if(obj.hasClass('removable') && (obj.siblings('.remove-file').length <= 0) ) {
                        $button.after('<span class="add-on active remove-file" title="Remove '+newVal+'">x</span>');
                    }
                } else {
                    $button.html(options.defaultText)
                           .removeClass()
                           .addClass(options.buttonClass + ' ' + options.additionalButtonClasses);
                }

            });
        });
    };
})(jQuery);

$('input[type=file]').prettyFileInput();


(function(){
    // remove layerX and layerY
    var all = $.event.props,
        len = all.length,
        res = [];
    while (len--) {
      var el = all[len];
      if (el != 'layerX' && el != 'layerY') res.push(el);
    }
    $.event.props = res;
}());