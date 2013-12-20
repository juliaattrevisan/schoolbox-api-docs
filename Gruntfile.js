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
        less: {
            main: {
                files: [{
                    expand: true,
                    cwd: 'www/css',
                    src: ['*/{chrome,mchrome}.less', '!core/{chrome,mchrome}.less'],
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
    grunt.loadNpmTasks('grunt-shell');

    // Build tasks
    grunt.registerTask('build.deps', ['shell:jqueryui.install', 'shell:jqueryui.build']);
    grunt.registerTask('build.js',   ['uglify']);
    grunt.registerTask('build.css',  ['less']);
    grunt.registerTask('build',      ['bower', 'build.deps', 'build.js', 'build.css']);
    grunt.registerTask('default',    ['build']);
};