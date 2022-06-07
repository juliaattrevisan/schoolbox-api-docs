const path = require('path');
const webpack = require('webpack');
const {VueLoaderPlugin} = require('vue-loader');
const RemoveEmptyScriptsPlugin = require('webpack-remove-empty-scripts'); //https://www.npmjs.com/package/webpack-remove-empty-scripts


module.exports = {
    entry: { //https://webpack.js.org/configuration/entry-context/

        main: [

            // jQuery
            './node_modules/jquery/dist/jquery.js',

            // jQuery Validation
            './node_modules/jquery-validation/dist/jquery.validate.js',
            './www/static/js/form/jquery.alaressValidate.js',

            // jQuery UI
            // dependencies
            './node_modules/jquery-ui/ui/version.js',
            './node_modules/jquery-ui/ui/plugin.js',
            './node_modules/jquery-ui/ui/widget.js',
            './node_modules/jquery-ui/ui/disable-selection.js',
            './node_modules/jquery-ui/ui/widgets/mouse.js',
            // interactions
            './node_modules/jquery-ui/ui/widgets/draggable.js',
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
            './www/static/js/foundation/foundation.joyride.js',
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
            './www/static/js/vendors/dayjs/dayjsWithPlugins.js',

            // Files And Resource Browser
            './www/static/js/socialstream/browser/browser.js',
            './node_modules/dropzone/dist/dropzone.js',

            // Magnific Popup Lightbox
            './node_modules/magnific-popup/dist/jquery.magnific-popup.js',

            // Notifier
            './www/static/js/notifications/jquery.notifier.js',

            // Confirm Dialog
            './www/static/js/popup/jquery.confirm-dialog.js',

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

            // MS Immersive Reader
            './www/static/js/immersive-reader.js',

            './www/static/js/calendar/jquery.getExportCalendarLink.js'
        ],
        spectrum: {
            import: [
                './node_modules/spectrum-colorpicker/spectrum.js', // Colour handling
            ],
            dependOn: 'main' //ensures jQuery is loaded prior
        },
        masonry: {
            import: [
                // Colour handling
                './node_modules/spectrum-colorpicker/spectrum.js',

                // homepage editing
                './www/static/js/masonry/jquery.masonry.js',
                './www/static/js/masonry/jquery.masonry.spectrum.js',
            ],
            dependOn: 'main' //ensures jQuery is loaded prior
        },
        mage: {
            import: [
                './www/static/js/form/jquery.mage.js',
            ],
            dependOn: 'main' //ensures jQuery is loaded prior
        },
        dataTables: {
            import: [
                './node_modules/datatables.net/js/jquery.dataTables.js',
                './node_modules/datatables.net-fixedcolumns/js/dataTables.fixedColumns.js',
            ],
            dependOn: 'main' //ensures jQuery is loaded prior
        },
        /*
        // Libraries
        clipboard: './www/static/js/clipboard/clipboard.js',
        strtotime: './www/static/js/alaress-datetime-picker/strtotime.js',
        highcharts: './www/static/js/vendors/highcharts/highcharts.js',
        highchartsMore: './www/static/js/vendors/highcharts/highchartsMore.js',

        //Search Bar
        searchBar: './vue/components/searchBar/searchBar.js',

        //Calendars
        sbFullCalendar: './library/Schoolbox/Calendar/vue/sbFullCalendar.js',
        timetableCalendar: './library/Schoolbox/Calendar/vue/timetableCalendar.js',
        assessmentCalendar: './library/Schoolbox/Learning/Assessment/Calendar/vue/assessmentCalendar.js',
        upcomingDueWorkCalendar: './library/Schoolbox/Learning/Upcoming/vue/upcomingDueWorkCalendar.js',
        resourceBookingItemCalendar: './library/Schoolbox/ResourceBooking/Item/vue/resourceBookingItemCalendar.js',
        resourceBookingCategoryCalendar: './library/Schoolbox/ResourceBooking/Category/vue/resourceBookingCategoryCalendar.js',
        calendarComponent: './library/Schoolbox/Calendar/vue/calendarComponent.js',
        calendarEventDetails: './library/Schoolbox/Calendar/Event/templates/vue/calendarEventDetails.js',

        //News related
        visitationListFull: './library/Schoolbox/Comms/News/Statistics/templates/visitation/visitationListFull.js',
        articleList: './library/Schoolbox/Comms/News/templates/vue/articleList.js',
        articleThreads: './library/Schoolbox/Comms/News/templates/vue/articleThreads.js',
        newsAuthored: './library/Schoolbox/Comms/News/vue/newsAuthored.js',
        newsItemAudienceSelect: './library/Schoolbox/Comms/News/vue/newsItemAudienceSelect.js',
        newsItemAudienceInfo: './library/Schoolbox/Comms/News/vue/newsItemAudienceInfo.js',
        newsItemFormActions: './library/Schoolbox/Comms/News/vue/newsItemFormActions.js',
        newsRssLink: './library/Schoolbox/Comms/News/vue/newsRssLink.js',

        //Learning
        learningMomentsEvidenceDetail: './library/Schoolbox/Learning/Moment/Evidence/vue/learningMomentsEvidenceDetail.js',
        userProfileFeeds: './library/Schoolbox/Learning/vue/user-profile/userProfileFeeds.js',
        badgeList: './library/Schoolbox/Learning/Badge/vue/badgeList.js',
        badgeListForm: './library/Schoolbox/Learning/Badge/Award/vue/badgeListForm.js',
        badgeReport: './library/Schoolbox/Learning/Badge/Award/vue/badgeReport.js',
        evidenceMediaRecording: './library/Schoolbox/Learning/Moment/Evidence/vue/evidenceMediaRecording.js',
        checkShareWorkButtons: './library/Schoolbox/Learning/Moment/Evidence/templates/js/checkShareWorkButtons.js',
        similarityReport: './library/Schoolbox/Learning/Assessment/templates/dueWork/vue/similarityReport/similarityReport.js',

        // Textboxes
        textboxList: './library/Schoolbox/Resource/Textbox/vue/textboxList.js',

        //Admin
        adminGrades: './library/Schoolbox/Admin/Grades/vue/adminGrades.js',
        adminSettingsSearch: './library/Schoolbox/Admin/Navigation/vue/adminSettingsSearch.js',
        staleUsersReport: './library/Schoolbox/Admin/User/vue/staleUsersReports/staleUsersReport.js',
        userBulkModify:  './library/Schoolbox/Admin/User/vue/userBulkModify/userBulkModify.js',
        adminList: './library/Schoolbox/Admin/Lists/templates/vue/adminList.js',
        saveAdminSettings: './library/Schoolbox/Admin/Navigation/vue/saveAdminSettings.js',
        usageGlobal: './library/Schoolbox/Admin/Usage/vue/global/usageGlobal.js',
        usageSpecific: './library/Schoolbox/Admin/Usage/vue/specific/usageSpecific.js',

        //Record Media Modal
        recordMediaModal: './templates/core/resources/filesystem/vue/recordMediaModal.js',

        // Class
        classList: './library/Schoolbox/Learning/vue/classList.js',
        classSelector: './library/Schoolbox/Assessment/Course/templates/vue/classSelector.js',

        // Groups
        manageGroups: './library/Schoolbox/Groups/vue/manageGroups.js',

        // QR codes
        manageQrCodes: './library/Schoolbox/Qrcode/vue/manageQrCodes.js',

        // Pastoral
        pastoralRecordCommentsPublic: './library/Schoolbox/Pastoral/templates/vue/pastoralRecordCommentsPublic.js',
        pastoralRecordCommentsPrivate: './library/Schoolbox/Pastoral/templates/vue/pastoralRecordCommentsPrivate.js',

        //Resource
        googleFiles: './library/Schoolbox/Resource/GoogleFile/templates/component/js/googleFiles.js',
        viewAllFiles: './library/Schoolbox/Resource/Browser/templates/vue/viewAllFiles.js',

        //Most functionality for a home page
        updateHomepage: './templates/core/homepage/js/updateHomepage.js',

        //Canvas
        canvasAssignments: './library/Schoolbox/Canvas/templates/vue/canvasAssignments.js',
        */
        //Sass
        main: './www/static/css/core/main.scss',
        editor: './www/static/css/core/editor.scss',

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
                        { globalName: '$', override: true },
                        { globalName: 'jQuery', override: true }
                    ],
                },
            },
            {
                test: require.resolve('dropzone'),
                loader: 'expose-loader',
                options: {
                    exposes: { globalName: 'Dropzone', override: true }
                },
            },
            {
                test: require.resolve('dayjs'),
                loader: 'expose-loader',
                options: {
                    exposes: { globalName: 'dayjs', override: true }
                },
            },
            {
                test: require.resolve('intro.js'),
                loader: 'expose-loader',
                options: {
                    exposes: { globalName: 'IntroJs', moduleLocalName: 'introJs', override: true }
                },
            },
            {
                test: require.resolve('highcharts'),
                loader: 'expose-loader',
                options: {
                    exposes: { globalName: 'Highcharts', override: true }
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
                    appendTsSuffixTo: [/\.vue$/],
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
                            { loader: 'extract-loader' },
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
                                        includePaths: ['../../css/core/'],
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
            'vue$': 'vue/dist/vue.esm-bundler.js',
            '@schoolboxLibrary': path.resolve(__dirname, 'library/Schoolbox'),
            '@schoolboxCalendarEvent': path.resolve(__dirname, 'library/Schoolbox/Calendar/Event/templates/vue'),
            '@schoolboxCommsNews': path.resolve(__dirname, 'library/Schoolbox/Comms/News'),
            '@schoolboxLearningMomentEvidence': path.resolve(__dirname, 'library/Schoolbox/Learning/Moment/Evidence/vue'),
            '@schoolboxVue': path.resolve(__dirname, 'vue'),
            '@js': path.resolve(__dirname, 'www/static/js'),
            '@css': path.resolve(__dirname, 'www/static/css'),
        },
        extensions: ['*', '.js', '.vue', '.json']
    },
    plugins: [ //https://webpack.js.org/configuration/plugins/
        new webpack.DefinePlugin({ //to enable bundler feature flags, to enable build when compiling in production:
            __VUE_OPTIONS_API__: true,
            __VUE_PROD_DEVTOOLS__: false,
        }),
        new VueLoaderPlugin(), //to handle .vue files
        new RemoveEmptyScriptsPlugin(),
    ]
};
