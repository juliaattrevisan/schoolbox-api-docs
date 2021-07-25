const path = require('path');
const {VueLoaderPlugin} = require('vue-loader');
const RemoveEmptyScriptsPlugin = require('webpack-remove-empty-scripts'); //https://www.npmjs.com/package/webpack-remove-empty-scripts

module.exports = {
    entry: { //https://webpack.js.org/configuration/entry-context/

        main: [

            // jQuery
            './node_modules/jquery/dist/jquery.js',
            './node_modules/jquery-migrate/dist/jquery-migrate.js',

            // jQuery Validation
            './node_modules/jquery-validation/dist/jquery.validate.js',
            './www/static/js/form/jquery.alaressValidate.js',

            // jQuery UI
            // dependencies
            './node_modules/jquery-ui/ui/version.js',
            './node_modules/jquery-ui/ui/plugin.js',
            './node_modules/jquery-ui/ui/widget.js',
            './node_modules/jquery-ui/ui/data.js',
            './node_modules/jquery-ui/ui/disable-selection.js',
            './node_modules/jquery-ui/ui/ie.js',
            './node_modules/jquery-ui/ui/keycode.js',
            './node_modules/jquery-ui/ui/safe-active-element.js',
            './node_modules/jquery-ui/ui/safe-blur.js',
            './node_modules/jquery-ui/ui/scroll-parent.js',
            './node_modules/jquery-ui/ui/widgets/mouse.js',
            // interactions
            './node_modules/jquery-ui/ui/widgets/draggable.js',
            './node_modules/jquery-ui/ui/widgets/resizable.js',
            './node_modules/jquery-ui/ui/widgets/sortable.js',

            // jQueryUI Touch Punch
            './node_modules/@andxor/jquery-ui-touch-punch-fix/jquery.ui.touch-punch.js',

            // Reforge The Shards of Foundation
            // Choose the specific things we are utilising, rather
            // than just including everything
            // Note all of foundations has been placed in javascript/shared to remove references to Modernizr
            './www/static/js/foundation/foundation.js',
            './www/static/js/foundation/foundation.accordion.js',
            './www/static/js/foundation/foundation.alert.js',
            './www/static/js/foundation/foundation.equalizer.js',
            './www/static/js/foundation/foundation.offcanvas.js',
            './www/static/js/foundation/foundation.reveal.js',
            './www/static/js/foundation/foundation.slider.js',
            './www/static/js/foundation/foundation.tab.js',
            './www/static/js/foundation/foundation.tooltip.js',
            './www/static/js/foundation/foundation.dropdown.js',
            './www/static/js/foundation/jquery.tab-activator.js',
            './www/static/js/foundation/jquery.collapsable.js',

            // Side Navigation
            // This includes the Flyout menu, on user profile drop down
            // Also fixes touch on side navigation and adds scroll to top
            './www/static/js/sidemenu/jquery.flyout.js',
            './www/static/js/sidemenu/jquery.touch-menu.js',
            './www/static/js/sidemenu/jquery.scroll-to.js',

            // CKEditor
            './www/static/js/editor/jquery.modal.js',
            './www/static/js/editor/jquery.proxy-replacement.js',
            './node_modules/mediaelement/build/mediaelement-and-player.min.js',

            // DateTime
            './node_modules/moment/moment.js',

            // Files And Resource Browser
            './www/static/js/socialstream/browser/browser.js',
            './node_modules/dropzone/dist/dropzone.js',

            // Magnific Popup Lightbox
            './node_modules/magnific-popup/dist/jquery.magnific-popup.js',

            // Notifier
            './www/static/js/notifications/jquery.notifier.js',

            // Confirm Dialog
            './www/static/js/popup/jquery.confirm-dialog.js',

            // Data tables
            './node_modules/datatables.net/js/jquery.dataTables.js',
            './node_modules/datatables.net-fixedcolumns/js/dataTables.fixedColumns.js',

            // Toasts, popups to notify something has happened
            './www/static/js/popup/jquery.toast-timeout.js',

            // File uploading
            './www/static/js/resource/jquery.resourceAttach.js',

            // Image/File previewing
            './www/static/js/preview/preview.js',

            // Course selector
            './www/static/js/course/jquery.course-selector.js',

            // Date syncing
            './www/static/js/alaress-datetime-picker/jquery.sync-dates.js',

            // Flatpickr date selector
            './node_modules/flatpickr/dist/flatpickr.js',

            // Loads Flatpickr into a modal
            './www/static/js/alaress-datetime-picker/attacher.datepicker.js',
            './www/static/js/alaress-datetime-picker/alaress-datetime-picker.js',
            './www/static/js/alaress-datetime-picker/alaress-datetime-modal.js',

            // This is a Webpack-built module for strtotime
            './www/static/js/strtotime/strtotime.bundle.js',

            // Allow deference of form submit actions
            './www/static/js/form/jquery.stallSubmit.js',

            // Random Scripts
            // Various javascripts that are so tiny they didn't get
            // their own files/plugins
            './www/static/js/miscellaneous/miscellaneous.js',

            // Feature tours
            './node_modules/intro.js/intro.js',
            './www/static/js/tour/tour.js',

            // Lodash
            './node_modules/lodash/lodash.js',

            // MS Immersive Reader
            './node_modules/@microsoft/immersive-reader-sdk/lib/immersive-reader-sdk.js',

            './www/static/js/calendar/jquery.getExportCalendarLink.js'
        ],
        masonry: {
            import: [
                // Colour handling
                './node_modules/spectrum-colorpicker/spectrum.js',

                // homepage editing
                './www/static/js/masonry/jquery.masonry.js',
                './www/static/js/masonry/jquery.masonry.spectrum.js'
            ],
            dependOn: 'main' //ensures jQuery is loaded prior
        },

        // Libraries
        clipboard: './www/static/js/clipboard/clipboard.main.js',
        strtotime: './www/static/js/alaress-datetime-picker/strtotime.main.js',

        hybridbar: path.resolve(__dirname, 'resources/js/hybrid-bar/index.js'),

        //Calendars
        fullCalendar: './library/Schoolbox/Calendar/vue/initSbFullCalendar.main.js',
        assessmentCalendar: './library/Schoolbox/Learning/Assessment/Calendar/vue/initAssessmentCalendar.js',
        upcomingDueWorkCalendar: './library/Schoolbox/Learning/Upcoming/vue/initUpcomingDueWorkCalendar.js',
        resourceBookingItemCalendar: './library/Schoolbox/ResourceBooking/Item/vue/initResourceBookingItemCalendar.js',
        resourceBookingCategoryCalendar: './library/Schoolbox/ResourceBooking/Category/vue/initResourceBookingCategoryCalendar.js',
        calendarComponent: './library/Schoolbox/Calendar/vue/initCalendarComponent.js',
        calendarEventDetails: './library/Schoolbox/Calendar/Event/templates/vue/initCalendarEventDetails.js',

        //News related
        visitationListFull: './library/Schoolbox/Comms/News/Statistics/templates/visitation/initVisitationListFull.js',
        articleList: './library/Schoolbox/Comms/News/templates/vue/initArticleList.js',
        articleThreads: './library/Schoolbox/Comms/News/templates/vue/initArticleThreads.js',
        newsAuthored: './library/Schoolbox/Comms/News/vue/initNewsAuthored.js',
        newsItemAudienceSelect: './library/Schoolbox/Comms/News/vue/initNewsItemAudienceSelect.js',
        newsItemAudienceInfo: './library/Schoolbox/Comms/News/vue/initNewsItemAudienceInfo.js',
        newsItemFormActions: './library/Schoolbox/Comms/News/vue/initNewsItemFormActions.js',
        newsRssLink: './library/Schoolbox/Comms/News/vue/initNewsRssLink.js',

        //Learning
        learningMomentsEvidenceDetail: './library/Schoolbox/Learning/Moment/Evidence/vue/evidence-detail.js',
        userProfileFeeds: './library/Schoolbox/Learning/vue/user-profile/feeds.main.js',
        badgeList: './library/Schoolbox/Learning/Badge/vue/badgeList.main.js',
        badgeListForm: './library/Schoolbox/Learning/Badge/Award/vue/badgeListForm.main.js',
        evidenceMediaRecording: './library/Schoolbox/Learning/Moment/Evidence/vue/initEvidenceMediaRecording.js',
        checkShareWorkButtons: './library/Schoolbox/Learning/Moment/Evidence/templates/js/checkShareWorkButtons.js',

        //Admin
        adminGrades: './library/Schoolbox/Admin/Grades/vue/adminGrades.main.js',
        adminSettingsSearch: './library/Schoolbox/Admin/Navigation/vue/initAdminSettingsSearch.js',
        staleUsersReport: './library/Schoolbox/Admin/User/vue/staleUsersReports/initStaleUsersReport.js',
        rolesList: './library/Schoolbox/Admin/Lists/templates/vue/initRolesList.js',

        usageGlobalTotal: './library/Schoolbox/Admin/Usage/vue/global/total.main.js',
        usageGlobalRole: './library/Schoolbox/Admin/Usage/vue/global/role.main.js',
        usageGlobalSystem: './library/Schoolbox/Admin/Usage/vue/global/system.main.js',
        usageGlobalFiletype: './library/Schoolbox/Admin/Usage/vue/global/filetype.main.js',
        usageGlobalUser: './library/Schoolbox/Admin/Usage/vue/global/user.main.js',

        usageSpecificTotal: './library/Schoolbox/Admin/Usage/vue/specific/total.main.js',
        usageSpecificSystem: './library/Schoolbox/Admin/Usage/vue/specific/system.main.js',
        usageSpecificFiletype: './library/Schoolbox/Admin/Usage/vue/specific/filetype.main.js',
        usageSpecificFile: './library/Schoolbox/Admin/Usage/vue/specific/file.main.js',

        //Record Media Modal
        recordMediaModal: './templates/core/resources/filesystem/vue/initRecordMediaModal.js',

        // Class List
        classList: './library/Schoolbox/Learning/vue/init-class-list.js',

        // Google Files
        googleFiles: './library/Schoolbox/Resource/GoogleFile/templates/component/js/googleFiles.js',

        //Sass
        chromeFoundation: './www/static/css/foundation/chrome-foundation.scss',
        editor: './www/static/css/foundation/editor.scss',

    },
    output: {
        path: path.resolve(__dirname, 'www/static/js/bundle'),
        publicPath: '/static/js/bundle/',
        filename: '[name].js'
    },
    module: { //https://webpack.js.org/configuration/module/
        rules: [
            {
                test: require.resolve('jquery'),
                loader: 'expose-loader', //see https://webpack.js.org/loaders/expose-loader/ - expose these to the glocal scope, allowing access to the properties in twig / other JS files
                options: {
                    exposes: [
                        {globalName: '$', override: true},
                        {globalName: 'jQuery', override: true}
                    ],
                },
            },
            {
                test: require.resolve('dropzone'),
                loader: 'expose-loader',
                options: {
                    exposes: {globalName: 'Dropzone', override: true}
                },
            },
            {
                test: require.resolve('moment'),
                loader: 'expose-loader',
                options: {
                    exposes: {globalName: 'moment', override: true}
                },
            },
            {
                test: require.resolve('axios'),
                loader: 'expose-loader',
                options: {
                    exposes: {globalName: 'axios', override: true}
                },
            },
            {
                test: require.resolve('lodash'),
                loader: 'expose-loader',
                options: {
                    exposes: {globalName: '_', override: true}
                },
            },
            {
                test: require.resolve('intro.js'),
                loader: 'expose-loader',
                options: {
                    exposes: {globalName: 'IntroJs', moduleLocalName: 'introJs', override: true}
                },
            },
            {
                test: /\.js$/,
                exclude: /node_modules/,
                use: {
                    loader: 'babel-loader', //to transpile JS code to ensure everything is compatiable with all targeted browsers
                    options: {
                        presets: [
                            [
                                '@babel/preset-env',
                                {
                                    targets: {
                                        browsers: [
                                            '>0.25%',
                                            'not dead',
                                            'not op_mini all',
                                            'not ie 11'
                                        ],
                                    },
                                    useBuiltIns: 'usage',
                                    corejs: 3
                                }
                            ]
                        ],
                        plugins: [
                            '@babel/plugin-proposal-optional-chaining',
                        ]
                    }
                },
            },
            {
                test: /\.vue$/,
                loader: 'vue-loader', //to handle .vue files
                options: {
                    appendTsSuffixTo: [/\.vue$/]
                }
            },
            {
                test: /\.css$/,
                use: ['vue-style-loader', 'style-loader', 'css-loader'],
            },
            {
                test: /\.scss$/,
                oneOf: [ //this allows us to only perform the below on vue files, then other scss files follow the following
                    {
                        resourceQuery: /^\?vue/,
                        use: [
                            'vue-style-loader',
                            'css-loader',
                            'sass-loader'
                        ]
                    },
                    {
                        use: [
                            {
                                loader: 'file-loader',
                                options: {
                                    name: '[name].css',
                                    outputPath: '../../css/core/'
                                }
                            },
                            {loader: 'extract-loader'},
                            {
                                loader: 'css-loader',
                                options: {
                                    url: false,
                                    modules: false
                                }
                            },
                            {
                                loader: 'sass-loader', //to load the sass and export it to css
                                options: {
                                    sassOptions: {
                                        indentWidth: 4,
                                        includePaths: ['../../css/foundation/'],
                                    },
                                },
                            }
                        ]
                    }
                ]
            }
        ]
    },
    resolve: { //https://webpack.js.org/configuration/resolve/
        alias: {
            'vue$': 'vue/dist/vue.esm.js',
            '@schoolbox': path.resolve(__dirname, 'vue/components'),
            '@schoolboxCalendarEvent': path.resolve(__dirname, 'library/Schoolbox/Calendar/Event/templates/vue'),
            '@schoolboxCommsNews': path.resolve(__dirname, 'library/Schoolbox/Comms/News'),
            '@schoolboxLearningMomentEvidence': path.resolve(__dirname, 'library/Schoolbox/Learning/Moment/Evidence/vue'),
            '@js': path.resolve(__dirname, 'www/static/js'),
            '@css': path.resolve(__dirname, 'www/static/css')
        },
        extensions: ['*', '.js', '.vue', '.json']
    },
    plugins: [ //https://webpack.js.org/configuration/plugins/
        new VueLoaderPlugin(),
        new RemoveEmptyScriptsPlugin()
    ]
};
