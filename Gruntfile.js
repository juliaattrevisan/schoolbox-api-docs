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
                stdout: true,
                stederr: true,
                failOnError: true
            },
            "jqueryui.install": {
                command: 'npm install',
                tasks: ['build'],
                options: {
                    execOptions: {
                        cwd: 'www/javascript/components/jquery.ui/'
                    }
                }
            },
            "videojs.install": {
                command: 'npm install',
                options: {
                    execOptions: {
                        cwd: 'www/javascript/components/jquery.ui/'
                    }
                }
            }
        },
        hub: {
            jqueryui: {
                src: ['www/javascript/components/jquery.ui/Gruntfile.js'],
                tasks: ['build']
            },
            videojs: {
                src: ['www/javascript/components/videojs/Gruntfile.js']
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
    grunt.loadNpmTasks('grunt-hub');
    grunt.loadNpmTasks('grunt-shell');

    // Build tasks
    grunt.registerTask('build.jqueryui', ['shell:jqueryui.install', 'hub:jqueryui']);
    grunt.registerTask('build.videojs',  ['shell:videojs.install',  'hub:videojs']);
    grunt.registerTask('build.js',  ['build.jqueryui', 'build.videojs', 'uglify']);
    // CSS build depends on JQUI
    grunt.registerTask('build.css', ['build.jqueryui', 'less']);
    grunt.registerTask('build',     ['bower', 'build.js', 'build.css']);
    grunt.registerTask('default',   ['build']);
};