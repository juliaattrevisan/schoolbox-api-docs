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
                sourceMap: 'www/javascript/components/<%= pkg.name %>.map.js',
                sourceMapRoot: '/',
                sourceMapPrefix: 1,
                sourceMappingURL: '<%= pkg.name %>.map.js'
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

                    // Fullcalendar
                    'www/javascript/components/fullcalendar/dist/fullcalendar.min.js',

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

                    // Random Scripts
                    // Various javascripts that are so tiny they didn't get
                    // their own files/plugins
                    'www/javascript/shared/miscellaneous.js'

                    //Maths formulas
                    //'www/javascript/math/jscurry-0.3.0.js',
                    //'www/javascript/math/jqmath-0.4.0.js'
                ],
                dest: 'www/javascript/components/<%= pkg.name %>.min.js'
            }
        },
        clean: {
            js: ['www/javascript/components/schoolbox.{min,map}.js'],
            css: ['www/css/*/chrome.css']
        }
    });

    require('jit-grunt')(grunt, {bower: 'grunt-bower-task'})

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
