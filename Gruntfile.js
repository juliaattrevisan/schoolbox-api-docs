var glob  = require('glob');
var shell = require('shelljs');

module.exports = function(grunt) {
    // Project configuration.
    grunt.initConfig({
        pkg: grunt.file.readJSON('package.json'),
        bower: {
            options: {
                copy: false
            },
            install: {}
        },
        uglify: {
            options: {
                banner: '/*! <%= pkg.name %> <%= grunt.template.today("yyyy-mm-dd") %> */\n',
                sourceMap: true
            },
            main: {
                // Bower files
                src:  [

                    // Modernizr
                    'www/javascript/components/modernizr/modernizr.js',

                    // Modernizr custom tests
                    'www/javascript/shared/modernizr/custom-tests.js',

                    // Fastclick
                    'www/javascript/components/fastclick/lib/fastclick.js',

                    // jQuery
                    'www/javascript/components/jquery/jquery.js',
                    'www/javascript/components/jquery/jquery-migrate.min.js',

                    // jQuery placeholder shim
                    'www/javascript/components/jquery-placeholder/jquery.placeholder.js',

                    // jQuery Validation
                    'www/javascript/components/jquery-validation/jquery.validate.js',
                    'www/javascript/shared/jquery.validate.extensions.js',
                    'www/javascript/jquery.alaressValidate.js',

                    // jQueryUI
                    'www/javascript/components/jquery-ui/ui/jquery-ui.js',

                    // jQueryUI Touch Punch
                    'www/javascript/components/jqueryui-touch-punch-improved/jquery.ui.touch-punch-improved.js',

                    // Reforge The Shards of Foundation
                    // Choose the specific things we are utilising, rather
                    // than just including everything
                    'www/javascript/components/foundation/js/foundation/foundation.js',
                    'www/javascript/components/foundation/js/foundation/foundation.accordion.js',
                    'www/javascript/components/foundation/js/foundation/foundation.alert.js',
                    'www/javascript/components/foundation/js/foundation/foundation.equalizer.js',
                    'www/javascript/components/foundation/js/foundation/foundation.offcanvas.js',
                    'www/javascript/components/foundation/js/foundation/foundation.reveal.js',
                    'www/javascript/components/foundation/js/foundation/foundation.slider.js',
                    'www/javascript/components/foundation/js/foundation/foundation.tab.js',
                    'www/javascript/components/foundation/js/foundation/foundation.tooltip.js',
                    'www/javascript/shared/foundation.dropdown.js',
                    'www/javascript/shared/jquery.tab-activator.js',
                    'www/javascript/shared/jquery.collapsable.js',

                    // Side Navigation
                    // This includes the Flyout menu, on user profile drop down
                    // Also fixes touch on side navigation and adds scroll to top
                    'www/javascript/shared/sidemenu/jquery.flyout.js',
                    'www/javascript/shared/sidemenu/jquery.touch-menu.js',
                    'www/javascript/shared/sidemenu/jquery.scroll-to.js',

                    // CKEditor
                    'www/javascript/shared/editor/jquery.proxy-replacement.js',
                    'www/javascript/components/mediaelement/build/mediaelement-and-player.min.js',

                    // DateTime
                    'www/javascript/components/moment/moment.js',

                    // Calendar-related popups
                    // (included in main because this can be used outside of a fullcalendar)
                    'www/javascript/shared/calendar/jquery.calendar.js',

                    // Files And Resource Browser
                    'www/socialstream/browser/browser.js',
                    'www/javascript/components/dropzone/dist/dropzone.js',

                    // Magnific Popup Lightbox
                    'www/javascript/components/magnific-popup/dist/jquery.magnific-popup.js',

                    // Notifier
                    'www/javascript/shared/notifications/jquery.notifier.js',

                    // Confirm Dialog
                    'www/javascript/shared/jquery.confirm-dialog.js',

                    // Device Dimensions
                    'www/javascript/shared/jquery.device.js',

                    // Data tables
                    'www/javascript/components/datatables/media/js/jquery.dataTables.min.js',

                    // Toasts, popups to notify something has happened
                    'www/javascript/shared/jquery.toast-timeout.js',

                    // File uploading
                    'www/javascript/resource/jquery.resourceAttach.js',

                    // Image/File previewing
                    'www/javascript/preview/preview.js',

                    // Course selector
                    'www/javascript/shared/course/jquery.course-selector.js',

                    // Random Scripts
                    // Various javascripts that are so tiny they didn't get
                    // their own files/plugins
                    'www/javascript/shared/miscellaneous.js'

                    //Maths formulas
                    //'www/javascript/math/jscurry-0.3.0.js',
                    //'www/javascript/math/jqmath-0.4.0.js'
                ],
                dest: 'www/javascript/components/<%= pkg.name %>.min.js'
            },
            masonry: {
                src:  [
                    // Colour handling
                    'www/javascript/components/spectrum/spectrum.js',

                    // homepage editing
                    'www/javascript/shared/masonry/jquery.masonry.js',
                    'www/javascript/shared/masonry/jquery.masonry.spectrum.js'
                ],
                dest: 'www/javascript/components/<%= pkg.name %>-masonry.min.js'
            },
            fullcalendar: {
                src:  [
                    // System for displaying calendars and associated popups
                    'www/javascript/components/fullcalendar/dist/fullcalendar.js',
                    'www/javascript/shared/calendar/jquery.fullcalendar.js'
                ],
                dest: 'www/javascript/components/<%= pkg.name %>-fullcalendar.min.js'
            }
        },
        clean: {
            js: ['www/javascript/components/schoolbox*.{min,map}.js'],
            css: ['www/css/*/chrome.css']
        }
    });

    require('jit-grunt')(grunt, {bower: 'grunt-bower-task'});

    // Build tasks
    grunt.registerTask('build.js',   ['uglify']);
    grunt.registerTask('sass', 'Builds SASS stylesheet (through tools/skin/builder.php)', function() {
        // Run sass build
        var result = shell.exec('php ./tools/skin/builder.php --force');
        if (result.code === 0) {
            grunt.log.writeln('Skin built successfully');
        } else {
            grunt.fail.warn("Skin may not have been built successfully! (error code: "+String(result.code)+')');
        }
    });
    grunt.registerTask('build',      ['bower', 'build.js', 'sass']);
    grunt.registerTask('default',    ['build']);
};
