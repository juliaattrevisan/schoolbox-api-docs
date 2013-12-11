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
                    // jQuery
                    'www/javascript/components/jquery/jquery.js',
                    'www/javascript/components/jquery/jquery-migrate.js',
                    // jQuery Cookie
                    'www/javascript/components/jquery.cookie/jquery.cookie.js',
                    // jQueryUI core
                    'www/javascript/components/jquery.ui/ui/jquery.ui.core.js',
                    'www/javascript/components/jquery.ui/ui/jquery.ui.widget.js',
                    'www/javascript/components/jquery.ui/ui/jquery.ui.mouse.js',
                    'www/javascript/components/jquery.ui/ui/jquery.ui.draggable.js',
                    'www/javascript/components/jquery.ui/ui/jquery.ui.droppable.js',
                    'www/javascript/components/jquery.ui/ui/jquery.ui.resizable.js',
                    'www/javascript/components/jquery.ui/ui/jquery.ui.selectable.js',
                    'www/javascript/components/jquery.ui/ui/jquery.ui.sortable.js',
                    'www/javascript/components/jquery.ui/ui/jquery.ui.effect.js',
                    // jQueryUI addons
                    'www/javascript/components/jquery.ui/ui/jquery.ui.accordion.js',
                    'www/javascript/components/jquery.ui/ui/jquery.ui.autocomplete.js',
                    'www/javascript/components/jquery.ui/ui/jquery.ui.button.js',
                    'www/javascript/components/jquery.ui/ui/jquery.ui.datepicker.js',
                    'www/javascript/components/jquery.ui/ui/jquery.ui.dialog.js',
                    'www/javascript/components/jquery.ui/ui/jquery.ui.effect-blind.js',
                    'www/javascript/components/jquery.ui/ui/jquery.ui.effect-bounce.js',
                    'www/javascript/components/jquery.ui/ui/jquery.ui.effect-clip.js',
                    'www/javascript/components/jquery.ui/ui/jquery.ui.effect-drop.js',
                    'www/javascript/components/jquery.ui/ui/jquery.ui.effect-explode.js',
                    'www/javascript/components/jquery.ui/ui/jquery.ui.effect-fade.js',
                    'www/javascript/components/jquery.ui/ui/jquery.ui.effect-fold.js',
                    'www/javascript/components/jquery.ui/ui/jquery.ui.effect-highlight.js',
                    'www/javascript/components/jquery.ui/ui/jquery.ui.effect-pulsate.js',
                    'www/javascript/components/jquery.ui/ui/jquery.ui.effect-scale.js',
                    'www/javascript/components/jquery.ui/ui/jquery.ui.effect-shake.js',
                    'www/javascript/components/jquery.ui/ui/jquery.ui.effect-slide.js',
                    'www/javascript/components/jquery.ui/ui/jquery.ui.effect-transfer.js',
                    'www/javascript/components/jquery.ui/ui/jquery.ui.menu.js',
                    'www/javascript/components/jquery.ui/ui/jquery.ui.position.js',
                    'www/javascript/components/jquery.ui/ui/jquery.ui.progressbar.js',
                    'www/javascript/components/jquery.ui/ui/jquery.ui.slider.js',
                    'www/javascript/components/jquery.ui/ui/jquery.ui.spinner.js',
                    'www/javascript/components/jquery.ui/ui/jquery.ui.tabs.js',
                    'www/javascript/components/jquery.ui/ui/jquery.ui.tooltip.js',
                    // jQueryUI Touch Punch
                    'www/javascript/components/jqueryui-touch-punch-improved/jquery.ui.touch-punch-improved.js'
                ],
                dest: 'www/javascript/components/<%= pkg.name %>.min.js'
            }
        },
        less: {
            chrome: {
                files: [{
                    expand: true,
                    cwd: 'www/css',
                    src: ['*/chrome.less', '!core/chrome.less'],
                    dest: 'www/css/',
                    ext: '.css'
                }]
            },
            mchrome: {
                files: [{
                    expand: true,
                    cwd: 'www/css',
                    src: ['*/mchrome.less', '!core/mchrome.less'],
                    dest: 'www/css/',
                    ext: '.css'
                }]
            }
        },
        clean: {
            js: ['www/javascript/components/*'],
            css: [
                'www/css/*/chrome.css',
                'www/css/*/mchrome.css'
            ]
        }
    });

    grunt.loadNpmTasks('grunt-contrib-clean');
    grunt.loadNpmTasks('grunt-contrib-uglify');
    grunt.loadNpmTasks('grunt-contrib-less');
    grunt.loadNpmTasks('grunt-bower-task');

    grunt.registerTask('build-js',  ['bower', 'uglify']);
    grunt.registerTask('build-css', ['bower', 'less']);
    grunt.registerTask('build',     ['bower', 'uglify', 'less']);
    grunt.registerTask('default',   ['build']);
};