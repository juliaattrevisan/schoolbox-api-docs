const path = require('path');
const {VueLoaderPlugin} = require('vue-loader');
const FixStyleOnlyEntriesPlugin = require('webpack-fix-style-only-entries'); //https://www.npmjs.com/package/webpack-fix-style-only-entries

module.exports = {
    entry: {

        main: [

            // jQuery
            './www/static/javascript/components/jquery/dist/jquery.js',

            // jQuery Validation
            './www/static/javascript/components/jquery-validation/dist/jquery.validate.js',
            './www/static/javascript/jquery.alaressValidate.js',

            // jQuery UI
            // dependencies
            './www/static/javascript/components/jquery-ui/ui/version.js',
            './www/static/javascript/components/jquery-ui/ui/plugin.js',
            './www/static/javascript/components/jquery-ui/ui/widget.js',
            './www/static/javascript/components/jquery-ui/ui/data.js',
            './www/static/javascript/components/jquery-ui/ui/disable-selection.js',
            './www/static/javascript/components/jquery-ui/ui/ie.js',
            './www/static/javascript/components/jquery-ui/ui/keycode.js',
            './www/static/javascript/components/jquery-ui/ui/safe-active-element.js',
            './www/static/javascript/components/jquery-ui/ui/safe-blur.js',
            './www/static/javascript/components/jquery-ui/ui/scroll-parent.js',
            './www/static/javascript/components/jquery-ui/ui/widgets/mouse.js',
            // interactions
            './www/static/javascript/components/jquery-ui/ui/widgets/draggable.js',
            './www/static/javascript/components/jquery-ui/ui/widgets/resizable.js',
            './www/static/javascript/components/jquery-ui/ui/widgets/sortable.js',

            // jQueryUI Touch Punch
            './www/static/javascript/components/jqueryui-touch-punch-improved/jquery.ui.touch-punch-improved.js',

            // Reforge The Shards of Foundation
            // Choose the specific things we are utilising, rather
            // than just including everything
            // Note all of foundations has been placed in javascript/shared to remove references to Modernizr
            './www/static/javascript/shared/foundation/foundation.js',
            './www/static/javascript/shared/foundation/foundation.accordion.js',
            './www/static/javascript/shared/foundation/foundation.alert.js',
            './www/static/javascript/shared/foundation/foundation.equalizer.js',
            './www/static/javascript/shared/foundation/foundation.offcanvas.js',
            './www/static/javascript/shared/foundation/foundation.reveal.js',
            './www/static/javascript/shared/foundation/foundation.slider.js',
            './www/static/javascript/shared/foundation/foundation.tab.js',
            './www/static/javascript/shared/foundation/foundation.tooltip.js',
            './www/static/javascript/shared/foundation/foundation.dropdown.js',
            './www/static/javascript/shared/jquery.tab-activator.js',
            './www/static/javascript/shared/jquery.collapsable.js',

            // Side Navigation
            // This includes the Flyout menu, on user profile drop down
            // Also fixes touch on side navigation and adds scroll to top
            './www/static/javascript/shared/sidemenu/jquery.flyout.js',
            './www/static/javascript/shared/sidemenu/jquery.touch-menu.js',
            './www/static/javascript/shared/sidemenu/jquery.scroll-to.js',

            // CKEditor
            './www/static/javascript/shared/editor/jquery.modal.js',
            './www/static/javascript/shared/editor/jquery.proxy-replacement.js',
            './www/static/javascript/components/mediaelement/build/mediaelement-and-player.min.js',

            // DateTime
            './node_modules/moment/moment.js',

            // Files And Resource Browser
            './www/static/socialstream/browser/browser.js',
            './www/static/javascript/components/dropzone/dist/dropzone.js',

            // Magnific Popup Lightbox
            './www/static/javascript/components/magnific-popup/dist/jquery.magnific-popup.js',

            // Notifier
            './www/static/javascript/shared/notifications/jquery.notifier.js',

            // Confirm Dialog
            './www/static/javascript/shared/jquery.confirm-dialog.js',

            // Data tables
            './www/static/javascript/components/datatables/media/js/jquery.dataTables.min.js',
            './www/static/javascript/components/datatables.net-fixedcolumns/js/dataTables.fixedColumns.js',

            // Toasts, popups to notify something has happened
            './www/static/javascript/shared/jquery.toast-timeout.js',

            // File uploading
            './www/static/javascript/resource/jquery.resourceAttach.js',

            // Image/File previewing
            './www/static/javascript/preview/preview.js',

            // Course selector
            './www/static/javascript/shared/course/jquery.course-selector.js',

            // Date syncing
            './www/static/javascript/shared/alaress-datetime-picker/jquery.sync-dates.js',

            // Flatpickr date selector
            './node_modules/flatpickr/dist/flatpickr.js',

            // Loads Flatpickr into a modal
            './www/static/javascript/shared/alaress-datetime-picker/attacher.datepicker.js',
            './www/static/javascript/shared/alaress-datetime-picker/alaress-datetime-picker.js',
            './www/static/javascript/shared/alaress-datetime-picker/alaress-datetime-modal.js',

            // This is a Webpack-built module for strtotime
            './www/static/javascript/components/strtotime.bundle.js',

            // Allow deference of form submit actions
            './www/static/javascript/shared/jquery.stallSubmit.js',

            // Random Scripts
            // Various javascripts that are so tiny they didn't get
            // their own files/plugins
            './www/static/javascript/shared/miscellaneous.js',

            //Maths formulas
            //'www/static/javascript/math/jscurry-0.3.0.js',
            //'www/static/javascript/math/jqmath-0.4.0.js'

            // Feature tours
            './www/static/javascript/components/intro.js/intro.js',
            './www/static/javascript/shared/tour/tour.js',

            // Lodash
            './node_modules/lodash/lodash.js',

            // MS Immersive Reader
            './node_modules/@microsoft/immersive-reader-sdk/lib/immersive-reader-sdk.js',

            './www/static/javascript/shared/calendar/jquery.getExportCalendarLink.js'
        ],
        masonry: [
            // Colour handling
            './www/static/javascript/components/spectrum/spectrum.js',

            // homepage editing
            './www/static/javascript/shared/masonry/jquery.masonry.js',
            './www/static/javascript/shared/masonry/jquery.masonry.spectrum.js'
        ],
        fullcalendarOld: [
            // Required for the datepicker calendar for now
            './node_modules/@fullcalendar/core/main.js',
            './node_modules/@fullcalendar/list/main.js'
        ],
        vue: [ // Death to jQuery!
            './node_modules/vue/dist/vue.min.js',
            './node_modules/vuejs-paginate/dist/index.js',
            './node_modules/axios/dist/axios.js'
        ],

        // Libraries
        clipboard: "./www/static/javascript/shared/clipboard/clipboard.main.js",
        strtotime: "./www/static/javascript/shared/alaress-datetime-picker/strtotime.main.js",

        // Vue components
        hybridbar: path.resolve(__dirname, 'resources/js/hybrid-bar/index.js'),
        fullCalendar: "./library/Schoolbox/Calendar/vue/fullCalendar.main.js",
        assessmentCalendar: "./library/Schoolbox/Learning/Assessment/Calendar/vue/calendar.main.js",
        upcomingDueworkCalendar: "./library/Schoolbox/Learning/Upcoming/vue/calendar.main.js",
        resourceBookingItemCalendar: "./library/Schoolbox/ResourceBooking/Item/vue/calendar.main.js",
        resourceBookingCategoryCalendar: "./library/Schoolbox/ResourceBooking/Category/vue/calendar.main.js",
        eventDetails: "./library/Schoolbox/Calendar/Event/templates/vue/index.js",
        calendarComponent: "./library/Schoolbox/Calendar/vue/component.main.js",
        visitationListFull: './library/Schoolbox/Comms/News/Statistics/templates/visitation/initVisitationListFull.js',
        articleList: './library/Schoolbox/Comms/News/templates/vue/article-list.js',
        articleThreads: './library/Schoolbox/Comms/News/templates/vue/init-article-threads.js',
        authored: './library/Schoolbox/Comms/News/vue/authored.main.js',
        adminGrades: './library/Schoolbox/Admin/Grades/vue/adminGrades.main.js',
        learningMomentsEvidenceDetail: './library/Schoolbox/Learning/Moment/Evidence/vue/evidence-detail.js',
        userProfileFeeds: './library/Schoolbox/Learning/vue/user-profile/feeds.main.js',
        badgeList: './library/Schoolbox/Learning/Badge/vue/badgeList.main.js',
        badgeListForm: './library/Schoolbox/Learning/Badge/Award/vue/badgeListForm.main.js',
        authored: './library/Schoolbox/Comms/News/vue/authored.main.js',
        adminSettingsSearch: './library/Schoolbox/Admin/Navigation/vue/initAdminSettingsSearch.js',
        staleUsersReport: './library/Schoolbox/Admin/User/vue/staleUsersReports/initStaleUsersReport.js',

        usageGlobalTotal: './library/Schoolbox/Admin/Usage/vue/global/total.main.js',
        usageGlobalRole: './library/Schoolbox/Admin/Usage/vue/global/role.main.js',
        usageGlobalSystem: './library/Schoolbox/Admin/Usage/vue/global/system.main.js',
        usageGlobalFiletype: './library/Schoolbox/Admin/Usage/vue/global/filetype.main.js',
        usageGlobalUser: './library/Schoolbox/Admin/Usage/vue/global/user.main.js',

        usageSpecificTotal: './library/Schoolbox/Admin/Usage/vue/specific/total.main.js',
        usageSpecificSystem: './library/Schoolbox/Admin/Usage/vue/specific/system.main.js',
        usageSpecificFiletype: './library/Schoolbox/Admin/Usage/vue/specific/filetype.main.js',
        usageSpecificFile: './library/Schoolbox/Admin/Usage/vue/specific/file.main.js',

        //Sass
        chromeFoundation: './www/static/css/foundation/chrome-foundation.scss',
        editor: './www/static/css/foundation/editor.scss'

    },
    output: {
        path: path.resolve(__dirname, "www/static/javascript/components"),
        publicPath: "/static/javascript/components/",
        filename: "[name].bundle.js"
    },
    module: {
        rules: [
            {
                test: /\.js$/,
                exclude: /node_modules/,
                use: {
                    loader: "babel-loader",
                    options: {
                        presets: [
                            [
                                '@babel/preset-env',
                                {
                                    targets: {
                                        browsers: [
                                            ">0.25%",
                                            "not dead",
                                            "not op_mini all",
                                            "not ie 11"
                                        ],
                                    },
                                    useBuiltIns: 'usage',
                                    corejs: 3
                                }
                            ]
                        ],
                        plugins: [
                            "@babel/plugin-proposal-optional-chaining",
                        ]
                    }
                },
            },
            {
                test: /\.vue$/,
                loader: 'vue-loader',
                options: {
                    appendTsSuffixTo: [/\.vue$/]
                }
            },
            {
                test: /\.css$/,
                use: ['vue-style-loader', 'css-loader'],
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
                                loader: "file-loader",
                                options: {
                                    name: "[name].css",
                                    outputPath: "../../css/core/"
                                }
                            },
                            {loader: "extract-loader"},
                            {
                                loader: "css-loader",
                                options: {
                                    url: false,
                                    modules: false
                                }
                            },
                            {
                                loader: "sass-loader",
                                options: {
                                    sassOptions: {
                                        indentWidth: 4,
                                        includePaths: ["../../css/foundation/"],
                                    },
                                },
                            }
                        ]
                    }
                ]
            }
        ]
    },
    resolve: {
        alias: {
            'vue$': 'vue/dist/vue.esm.js',
            '@schoolbox': path.resolve(__dirname, "vue/components"),
            '@schoolboxCalendarEvent': path.resolve(__dirname, "library/Schoolbox/Calendar/Event/templates/vue"),
            '@schoolboxCommsNews': path.resolve(__dirname, 'library/Schoolbox/Comms/News'),
            '@schoolboxLearningMomentEvidence': path.resolve(__dirname, 'library/Schoolbox/Learning/Moment/Evidence/vue'),
            '@shared': path.resolve(__dirname, 'www/static/javascript/shared'),
        },
        extensions: ['*', '.js', '.vue', '.json']
    },
    plugins: [
        new VueLoaderPlugin(),
        new FixStyleOnlyEntriesPlugin()
    ]
};
