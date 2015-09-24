var glob  = require('glob');
var shell = require('shelljs');
var fs    = require('fs');

// Before anything else: ensure /www/css/foundation/utils/_skin.scss exists
var skinFile = __dirname+'/www/css/foundation/utils/_skin.scss';
if (!fs.existsSync(skinFile)) {
    fs.writeFileSync(skinFile, '');
}

module.exports = function(grunt) {

    // Core watch task: builds CSS for all schools
    var cssDirs = glob.sync('www/css/*/');
    var watchTasks = {
        core: {
            options: {
                interrupt: true,
                interval: 5007
            },
            files: ['www/css/core/*.less'],
            tasks: ['build.css:core', 'build.css.current']
        }
    };
    var buildTasks = {};

    // Create watch and build tasks for each CSS directory.
    for (var i in cssDirs) {
        var dir     = cssDirs[i];
        var matches = dir.match(/^www\/css\/(.*?)\/$/)
        if (!matches) {
            continue;
        }
        var cssDir = matches[1];
        buildTasks[cssDir] = {
            files: [{
                expand: true,
                cwd: 'www/css',
                src: [cssDir+'/{chrome,print}.less'],
                dest: 'www/css/',
                ext: '.css'
            }]
        }
        // Don't clobber core's watcher
        if (cssDir === 'core') {
            continue;
        }
        watchTasks[cssDir] = {
            options: {
                interrupt: true,
                interval: 5007
            },
            files: ['www/css/'+cssDir+'/*.less'],
            tasks: ['build.css:'+cssDir]
        }
    }

    // Project configuration.
    grunt.initConfig({
        pkg: grunt.file.readJSON('package.json'),
        bower: {
            options: {
                copy: false
            },
            install: {}
        },
        shell: {
            options: {
                stdout: false,
                stederr: true,
                failOnError: true
            },
            "jqueryui.install": {
                command: 'npm install',
                options: {
                    execOptions: {
                        cwd: 'www/javascript/components/jquery.ui/'
                    }
                }
            },
            "jqueryui.build": {
                command: 'grunt build',
                options: {
                    execOptions: {
                        cwd: 'www/javascript/components/jquery.ui/'
                    }
                }
            }
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

                    // jQueryUI
                    'www/javascript/components/jquery.ui/dist/jquery-ui.js',

                    // jQueryUI Touch Punch
                    'www/javascript/components/jqueryui-touch-punch-improved/jquery.ui.touch-punch-improved.js',

                    // Reforge The Shards of Foundation
                    // Choose the specific things we are utilising, rather
                    // than just including everything
                    'www/javascript/components/foundation/js/foundation/foundation.js',
                    'www/javascript/components/foundation/js/foundation/foundation.accordion.js',
                    'www/javascript/components/foundation/js/foundation/foundation.alert.js',
                    'www/javascript/components/foundation/js/foundation/foundation.clearing.js',
                    'www/javascript/components/foundation/js/foundation/foundation.dropdown.js',
                    'www/javascript/components/foundation/js/foundation/foundation.equalizer.js',
                    'www/javascript/components/foundation/js/foundation/foundation.offcanvas.js',
                    'www/javascript/components/foundation/js/foundation/foundation.reveal.js',
                    'www/javascript/components/foundation/js/foundation/foundation.slider.js',
                    'www/javascript/components/foundation/js/foundation/foundation.tab.js',
                    'www/javascript/components/foundation/js/foundation/foundation.tooltip.js',

                    // Side Navigation
                    // This includes the Flyout menu, on user profile drop down
                    // Also fixes touch on side navigation and adds scroll to top
                    'www/javascript/shared/sidemenu/jquery.flyout.js',
                    'www/javascript/shared/sidemenu/jquery.touch-menu.js',
                    'www/javascript/shared/sidemenu/jquery.scroll-to.js',

                    // DateTime
                    'www/javascript/components/moment/moment.js',

                    // Fullcalendar
                    'www/javascript/components/fullcalendar/dist/fullcalendar.min.js',

                    // Files And Resource Browser
                    'www/socialstream/browser/browser.js',
                    'www/javascript/components/dropzone/downloads/dropzone.js',

                    // Magnific Popup Lightbox
                    'www/javascript/components/magnific-popup/dist/jquery.magnific-popup.js',

                    // Notifier
                    'www/javascript/shared/notifications/jquery.notifier.js',

                    // Confirm Dialog
                    'www/javascript/shared/jquery.confirm-dialog.js',

                    // Device Dimensions
                    'www/javascript/shared/jquery.device.js',

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
            css: ['www/css/*/{chrome,print}.css']
        },
        less: buildTasks,
        sass: {
            // Don't run from bundle for now, as installing bundle runs out of memory
            //options: {
            //    bundleExec: true
            //},
            dist: {
                files: {
                    'www/css/foundation/chrome.css': 'www/css/foundation/chrome-foundation.scss'
                }
            }
        },
        watch: watchTasks
    });

    require('jit-grunt')(grunt, {bower: 'grunt-bower-task'})

    // Build tasks
    grunt.registerTask('build.deps', [
        'shell:jqueryui.install', 'shell:jqueryui.build'
    ]);
    grunt.registerTask('build.js',   ['uglify']);
    grunt.registerTask('build.css', 'Builds CSS (build.css:$skin builds for $skin only)', function(skin) {
        // Run less:main task.
        // For skin-specific builds: run less:main, but for that skin only.
        var task = 'less';
        if (skin) {
            task += ':'+skin;
        }
        grunt.task.run(task)
    });
    grunt.registerTask('build.css.current', 'Builds CSS for current skin', function() {
        var task = 'less';
        var currentSkin = shell.exec('./tools/school.sh', {silent: true}).output
        grunt.task.run((task+':'+currentSkin).trim())
    });
    grunt.registerTask('build',      ['bower', 'build.deps', 'build.js', 'build.css']);
    grunt.registerTask('default',    ['build']);
};
