var glob = require('glob');

module.exports = function(grunt) {

    // Core watch task: builds CSS for all schools
    var cssDirs = glob.sync('www/css/*/');
    var watchTasks = {
        core: {
            options: {
                spawn: false,
                interrupt: true
            },
            files: ['www/css/core/*.less'],
            tasks: ['build.css']
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
        if (cssDir === 'core') {
            continue;
        }
        watchTasks[cssDir] = {
            options: {
                spawn: false
            },
            files: ['www/css/'+cssDir+'/*.less'],
            tasks: ['build.css:'+cssDir]
        }
        buildTasks[cssDir] = {
            files: [{
                expand: true,
                cwd: 'www/css',
                src: [cssDir+'/{chrome,mchrome}.less'],
                dest: 'www/css/',
                ext: '.css'
            }]
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
                    // jQuery
                    'www/javascript/components/jquery/jquery.js',
                    'www/javascript/components/jquery/jquery-migrate.js',
                    // jQuery placeholder shim
                    'www/javascript/components/jquery-placeholder/jquery.placeholder.js',
                    // jQuery Validation
                    'www/javascript/components/jquery-validation/jquery.validate.js',
                    // jQuery Cookie
                    'www/javascript/components/jquery.cookie/jquery.cookie.js',
                    // jQueryUI
                    'www/javascript/components/jquery.ui/dist/jquery-ui.js',
                    // jQueryUI Touch Punch
                    'www/javascript/components/jqueryui-touch-punch-improved/jquery.ui.touch-punch-improved.js'
                ],
                dest: 'www/javascript/components/<%= pkg.name %>.min.js'
            }
        },
        clean: {
            js: ['www/javascript/components/schoolbox.{min,map}.js'],
            css: ['www/css/*/{chrome,mchrome}.css']
        },
        less: buildTasks,
        watch: watchTasks
    });

    grunt.loadNpmTasks('grunt-contrib-clean');
    grunt.loadNpmTasks('grunt-contrib-uglify');
    grunt.loadNpmTasks('grunt-contrib-less');
    grunt.loadNpmTasks('grunt-bower-task');
    grunt.loadNpmTasks('grunt-shell');
    grunt.loadNpmTasks('grunt-contrib-watch');

    // Build tasks
    grunt.registerTask('build.deps', ['shell:jqueryui.install', 'shell:jqueryui.build']);
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
    grunt.registerTask('build',      ['bower', 'build.deps', 'build.js', 'build.css']);
    grunt.registerTask('default',    ['build']);
};