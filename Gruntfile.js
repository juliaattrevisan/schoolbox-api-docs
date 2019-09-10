var glob  = require('glob');
var shell = require('shelljs');

var jsSrc = {
    main: [
        // Modernizr
        'www/javascript/components/modernizr/modernizr.js',

        // Modernizr custom tests
        'www/javascript/shared/modernizr/custom-tests.js',

        // Fastclick
        'www/javascript/components/fastclick/lib/fastclick.js',

        // jQuery
        'www/javascript/components/jquery/dist/jquery.js',

        // jQuery placeholder shim
        'www/javascript/components/jquery-placeholder/jquery.placeholder.js',

        // jQuery Validation
        'www/javascript/components/jquery-validation/dist/jquery.validate.js',
        'www/javascript/shared/jquery.validate.extensions.js',
        'www/javascript/jquery.alaressValidate.js',

        // jQuery UI
        // dependencies
        'www/javascript/components/jquery-ui/ui/version.js',
        'www/javascript/components/jquery-ui/ui/plugin.js',
        'www/javascript/components/jquery-ui/ui/widget.js',
        'www/javascript/components/jquery-ui/ui/data.js',
        'www/javascript/components/jquery-ui/ui/disable-selection.js',
        'www/javascript/components/jquery-ui/ui/ie.js',
        'www/javascript/components/jquery-ui/ui/keycode.js',
        'www/javascript/components/jquery-ui/ui/safe-active-element.js',
        'www/javascript/components/jquery-ui/ui/safe-blur.js',
        'www/javascript/components/jquery-ui/ui/scroll-parent.js',
        'www/javascript/components/jquery-ui/ui/widgets/mouse.js',
        // interactions
        'www/javascript/components/jquery-ui/ui/widgets/draggable.js',
        'www/javascript/components/jquery-ui/ui/widgets/resizable.js',
        'www/javascript/components/jquery-ui/ui/widgets/sortable.js',
        // widgets
        'www/javascript/components/jquery-ui/ui/widgets/datepicker.js',

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
        'www/javascript/components/datatables.net-fixedcolumns/js/dataTables.fixedColumns.js',

        // Toasts, popups to notify something has happened
        'www/javascript/shared/jquery.toast-timeout.js',

        // File uploading
        'www/javascript/resource/jquery.resourceAttach.js',

        // Image/File previewing
        'www/javascript/preview/preview.js',

        // Course selector
        'www/javascript/shared/course/jquery.course-selector.js',

        // Date syncing
        'www/javascript/shared/datetime/jquery.sync-dates.js',

        // Flatpickr date selector
        'www/javascript/components/flatpickr/dist/flatpickr.js',

        // Loads Flatpickr into a modal
        'www/javascript/shared/datetime/attacher.datepicker.js',
        'www/javascript/shared/alaress-datetime-picker/alaress-datetime-picker.js',
        'www/javascript/shared/alaress-datetime-picker/alaress-datetime-modal.js',

        // Allow deference of form submit actions
        'www/javascript/shared/jquery.stallSubmit.js',

        // Random Scripts
        // Various javascripts that are so tiny they didn't get
        // their own files/plugins
        'www/javascript/shared/miscellaneous.js',

        //Maths formulas
        //'www/javascript/math/jscurry-0.3.0.js',
        //'www/javascript/math/jqmath-0.4.0.js'

        // Feature tours
        'www/javascript/components/intro.js/intro.js',
        'www/javascript/shared/tour/tour.js',

        // Lodash
        'node_modules/lodash/lodash.js',

        // Promise polyfill
        'node_modules/promise-polyfill/dist/polyfill.js',

        'www/javascript/shared/calendar/jquery.calendar.js'
    ],
    masonry: [
        // Colour handling
        'www/javascript/components/spectrum/spectrum.js',

        // homepage editing
        'www/javascript/shared/masonry/jquery.masonry.js',
        'www/javascript/shared/masonry/jquery.masonry.spectrum.js'
    ],
    vue: [ // Death to jQuery!
        'node_modules/es6-object-assign/dist/object-assign-auto.js',
        'node_modules/vue/dist/vue.min.js',
        'node_modules/vuejs-paginate/dist/index.js',
        'node_modules/axios/dist/axios.js'
    ]
};
var jsDest = {
    main: 'www/javascript/components/<%= pkg.name %>.min.js',
    masonry: 'www/javascript/components/<%= pkg.name %>-masonry.min.js',
    vue: 'www/javascript/components/<%= pkg.name %>-vue.min.js'
};

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
        sass: { // Only used for development, skips the skin build process
            dist: {
                files: {
                    'www/css/demo/chrome-foundation.css': 'www/css/foundation/chrome-foundation.scss'
                }
            }
        },
        concat: { // Concat JS files (used for development)
            options: {
                separator: ';'
            },
            main: {
                src: jsSrc.main,
                dest: jsDest.main
            },
            masonry: {
                src: jsSrc.masonry,
                dest: jsDest.masonry
            },
            vue: {
                src: jsSrc.vue,
                dest: jsDest.vue
            }
        },
        uglify: {
            options: {
                banner: '/*! <%= pkg.name %> <%= grunt.template.today("yyyy-mm-dd") %> */\n'//,
                // sourceMap: true
            },
            main: {
                // Bower files
                src: jsSrc.main,
                dest: jsDest.main
            },
            masonry: {
                src: jsSrc.masonry,
                dest: jsDest.masonry
            },
            vue: {
                src: jsSrc.vue,
                dest: jsDest.vue
            }
        },
        clean: {
            js: ['www/javascript/components/schoolbox*.min.js*']
        },
        watch: {
            scripts: {
                files: ['www/javascript/**/*.js', '!www/javascript/components/*'],
                tasks: 'concat',
                options: {
                    interrupt: true
                }
            },
            css: {
                files: ['www/css/**/*.scss', '!www/css/foundation/utils/*.scss'],
                tasks: 'sass',
                options: {
                    interrupt: true
                }
            }
        }
    });

    require('jit-grunt')(grunt, {bower: 'grunt-bower-task'});

    grunt.loadNpmTasks('grunt-contrib-watch');
    grunt.loadNpmTasks('grunt-contrib-sass');
    grunt.loadNpmTasks('grunt-contrib-concat');

    // Dev tasks
    grunt.registerTask('js', 'concat');
    grunt.registerTask('dev', ['bower', 'concat', 'sass']);

    // Build tasks
    grunt.registerTask('build.js', 'uglify');
    grunt.registerTask('build.sass', 'Builds SASS stylesheet (through tools/skin/builder.php)', function() {
        // Run sass build
        var result = shell.exec('php ./tools/skin/builder.php --force');
        if (result.code === 0) {
            grunt.log.writeln('Skin built successfully');
        } else {
            grunt.fail.warn("Skin may not have been built successfully! (error code: "+String(result.code)+')');
        }
    });
    grunt.registerTask('build',      ['bower', 'build.js', 'build.sass']);
    grunt.registerTask('default',    'build');
};
