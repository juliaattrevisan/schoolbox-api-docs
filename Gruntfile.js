const jsSrc = {
    main: [

        // jQuery
        'www/static/javascript/components/jquery/dist/jquery.js',

        // jQuery Validation
        'www/static/javascript/components/jquery-validation/dist/jquery.validate.js',
        'www/static/javascript/jquery.alaressValidate.js',

        // jQuery UI
        // dependencies
        'www/static/javascript/components/jquery-ui/ui/version.js',
        'www/static/javascript/components/jquery-ui/ui/plugin.js',
        'www/static/javascript/components/jquery-ui/ui/widget.js',
        'www/static/javascript/components/jquery-ui/ui/data.js',
        'www/static/javascript/components/jquery-ui/ui/disable-selection.js',
        'www/static/javascript/components/jquery-ui/ui/ie.js',
        'www/static/javascript/components/jquery-ui/ui/keycode.js',
        'www/static/javascript/components/jquery-ui/ui/safe-active-element.js',
        'www/static/javascript/components/jquery-ui/ui/safe-blur.js',
        'www/static/javascript/components/jquery-ui/ui/scroll-parent.js',
        'www/static/javascript/components/jquery-ui/ui/widgets/mouse.js',
        // interactions
        'www/static/javascript/components/jquery-ui/ui/widgets/draggable.js',
        'www/static/javascript/components/jquery-ui/ui/widgets/resizable.js',
        'www/static/javascript/components/jquery-ui/ui/widgets/sortable.js',

        // jQueryUI Touch Punch
        'www/static/javascript/components/jqueryui-touch-punch-improved/jquery.ui.touch-punch-improved.js',

        // Reforge The Shards of Foundation
        // Choose the specific things we are utilising, rather
        // than just including everything
        // Note all of foundations has been placed in javascript/shared to remove references to Modernizr
        'www/static/javascript/shared/foundation/foundation.js',
        'www/static/javascript/shared/foundation/foundation.accordion.js',
        'www/static/javascript/shared/foundation/foundation.alert.js',
        'www/static/javascript/shared/foundation/foundation.equalizer.js',
        'www/static/javascript/shared/foundation/foundation.offcanvas.js',
        'www/static/javascript/shared/foundation/foundation.reveal.js',
        'www/static/javascript/shared/foundation/foundation.slider.js',
        'www/static/javascript/shared/foundation/foundation.tab.js',
        'www/static/javascript/shared/foundation/foundation.tooltip.js',
        'www/static/javascript/shared/foundation/foundation.dropdown.js',
        'www/static/javascript/shared/jquery.tab-activator.js',
        'www/static/javascript/shared/jquery.collapsable.js',

        // Side Navigation
        // This includes the Flyout menu, on user profile drop down
        // Also fixes touch on side navigation and adds scroll to top
        'www/static/javascript/shared/sidemenu/jquery.flyout.js',
        'www/static/javascript/shared/sidemenu/jquery.touch-menu.js',
        'www/static/javascript/shared/sidemenu/jquery.scroll-to.js',

        // CKEditor
        'www/static/javascript/shared/editor/jquery.modal.js',
        'www/static/javascript/shared/editor/jquery.proxy-replacement.js',
        'www/static/javascript/components/mediaelement/build/mediaelement-and-player.min.js',

        // DateTime
        'node_modules/moment/moment.js',

        // Files And Resource Browser
        'www/static/socialstream/browser/browser.js',
        'www/static/javascript/components/dropzone/dist/dropzone.js',

        // Magnific Popup Lightbox
        'www/static/javascript/components/magnific-popup/dist/jquery.magnific-popup.js',

        // Notifier
        'www/static/javascript/shared/notifications/jquery.notifier.js',

        // Confirm Dialog
        'www/static/javascript/shared/jquery.confirm-dialog.js',

        // Data tables
        'www/static/javascript/components/datatables/media/js/jquery.dataTables.min.js',
        'www/static/javascript/components/datatables.net-fixedcolumns/js/dataTables.fixedColumns.js',

        // Toasts, popups to notify something has happened
        'www/static/javascript/shared/jquery.toast-timeout.js',

        // File uploading
        'www/static/javascript/resource/jquery.resourceAttach.js',

        // Image/File previewing
        'www/static/javascript/preview/preview.js',

        // Course selector
        'www/static/javascript/shared/course/jquery.course-selector.js',

        // Date syncing
        'www/static/javascript/shared/alaress-datetime-picker/jquery.sync-dates.js',

        // Flatpickr date selector
        'node_modules/flatpickr/dist/flatpickr.js',

        // Loads Flatpickr into a modal
        'www/static/javascript/shared/alaress-datetime-picker/attacher.datepicker.js',
        'www/static/javascript/shared/alaress-datetime-picker/alaress-datetime-picker.js',
        'www/static/javascript/shared/alaress-datetime-picker/alaress-datetime-modal.js',

        // This is a Webpack-built module for strtotime
        'www/static/javascript/components/strtotime.bundle.js',

        // Allow deference of form submit actions
        'www/static/javascript/shared/jquery.stallSubmit.js',

        // Random Scripts
        // Various javascripts that are so tiny they didn't get
        // their own files/plugins
        'www/static/javascript/shared/miscellaneous.js',

        //Maths formulas
        //'www/static/javascript/math/jscurry-0.3.0.js',
        //'www/static/javascript/math/jqmath-0.4.0.js'

        // Feature tours
        'www/static/javascript/components/intro.js/intro.js',
        'www/static/javascript/shared/tour/tour.js',

        // Lodash
        'node_modules/lodash/lodash.js',

        // MS Immersive Reader
        'node_modules/@microsoft/immersive-reader-sdk/lib/immersive-reader-sdk.js',

        'www/static/javascript/shared/calendar/jquery.getExportCalendarLink.js'
    ],
    masonry: [
        // Colour handling
        'www/static/javascript/components/spectrum/spectrum.js',

        // homepage editing
        'www/static/javascript/shared/masonry/jquery.masonry.js',
        'www/static/javascript/shared/masonry/jquery.masonry.spectrum.js'
    ],
    fullcalendar: [
        // Required for the datepicker calendar for now
        'node_modules/@fullcalendar/core/main.js',
        'node_modules/@fullcalendar/list/main.js'
    ],
    vue: [ // Death to jQuery!
        'node_modules/vue/dist/vue.min.js',
        'node_modules/vuejs-paginate/dist/index.js',
        'node_modules/axios/dist/axios.js'
    ]
};
const jsDest = {
    main: 'www/static/javascript/components/<%= pkg.name %>.min.js',
    masonry: 'www/static/javascript/components/<%= pkg.name %>-masonry.min.js',
    fullcalendar: 'www/static/javascript/components/<%= pkg.name %>-fullcalendar.min.js',
    vue: 'www/static/javascript/components/<%= pkg.name %>-vue.min.js'
};

module.exports = function (grunt) {
    // Project configuration.
    grunt.initConfig({
        pkg: grunt.file.readJSON('package.json'),
        bower: {
            options: {
                copy: false
            },
            install: {}
        },
        concat: {
            // Concat JS files (used for development)
            options: {
                separator: ";\n;"
            },
            main: {
                src: jsSrc.main,
                dest: jsDest.main
            },
            masonry: {
                src: jsSrc.masonry,
                dest: jsDest.masonry
            },
            fullcalendar: {
                src: jsSrc.fullcalendar,
                dest: jsDest.fullcalendar
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
            fullcalendar: {
                src: jsSrc.fullcalendar,
                dest: jsDest.fullcalendar
            },
            vue: {
                src: jsSrc.vue,
                dest: jsDest.vue
            }
        },
        clean: {
            js: ['www/static/javascript/components/schoolbox*.min.js*']
        },
        watch: {
            scripts: {
                files: ['www/static/javascript/**/*.js', '!www/static/javascript/components/*'],
                tasks: 'concat',
                options: {
                    interrupt: true
                }
            }
        }
    });

    require('jit-grunt')(grunt, {bower: 'grunt-bower-task'});

    grunt.loadNpmTasks('grunt-contrib-watch');
    grunt.loadNpmTasks('grunt-contrib-concat');

    // Dev tasks
    grunt.registerTask('js', 'concat');
    grunt.registerTask('dev', ['bower', 'concat']);

    // Build tasks
    grunt.registerTask('build.js', 'uglify');
    grunt.registerTask('build',    ['bower', 'build.js']);
    grunt.registerTask('default',  'build');
};
