/**
 * All webpack configuration thats shared between dev and prod
 */

const path = require("path");
const { styles } = require("@ckeditor/ckeditor5-dev-utils"); // https://www.npmjs.com/package/@ckeditor/ckeditor5-dev-utils

module.exports = {
    entry: {
        // https://webpack.js.org/configuration/entry-context/
        main: [
            // custom validation built on top of jQuery Validation
            "./www/static/js/form/jquery.alaressValidate.js",

            // Reforge The Shards of Foundation
            // Choose the specific things we are utilising, rather
            // than just including everything
            // Note all of foundations has been placed in javascript/shared to remove references to Modernizr
            "./www/static/js/foundation/foundation.js",
            "./www/static/js/foundation/foundation.accordion.js",
            "./www/static/js/foundation/foundation.alert.js",
            "./www/static/js/foundation/foundation.equalizer.js",
            "./www/static/js/foundation/foundation.offcanvas.js",
            "./www/static/js/foundation/foundation.reveal.js",
            "./www/static/js/foundation/foundation.tab.js",
            "./www/static/js/foundation/foundation.tooltip.js",
            "./www/static/js/foundation/foundation.joyride.js",
            "./www/static/js/foundation/jquery.tab-activator.js",
            "./www/static/js/foundation/jquery.collapsable.js",

            // Custom Web Components
            "./vue/initWebComponents.ts",

            // Side Navigation
            // This includes the Flyout menu, on user profile drop down
            // Also fixes touch on side navigation and adds scroll to top
            "./www/static/js/sidemenu/jquery.flyout.js",
            "./www/static/js/sidemenu/jquery.touch-menu.js",
            "./www/static/js/sidemenu/jquery.scroll-to.js",

            // Modal and video player replacement
            "./www/static/js/editor/jquery.modal.js",
            "./www/static/js/editor/jquery.proxy-replacement.js",

            // DateTime
            "./www/static/js/vendors/dayjs/dayjsWithPlugins.js",

            // Files And Resource Browser
            "./www/static/js/socialstream/browser/browser.js",

            // Notifier
            "./www/static/js/notifications/jquery.notifier.js",

            // Confirm Dialog
            "./www/static/js/popup/jquery.confirm-dialog.js",

            // Toasts, popups to notify something has happened
            "./www/static/js/popup/jquery.toast-timeout.js",

            // File uploading
            "./www/static/js/resource/jquery.resourceAttach.js",

            // Image/File previewing
            "./www/static/js/preview/preview.js",

            // Image Lightbox
            "./www/static/js/image-lightbox/imageLightboxInit.ts",

            // Date syncing
            "./www/static/js/alaress-datetime-picker/jquery.sync-dates.js",

            // Loads Flatpickr into a modal
            "./www/static/js/alaress-datetime-picker/attacher.datepicker.js",
            "./www/static/js/alaress-datetime-picker/alaress-datetime-picker.js",
            "./www/static/js/alaress-datetime-picker/alaress-datetime-modal.js",

            // This is a Webpack-built module for strtotime
            "./www/static/js/strtotime/strtotime.bundle.js",

            // Allow deference of form submit actions
            "./www/static/js/form/jquery.stallSubmit.js",

            // Random Scripts
            // Various javascripts that are so tiny they didn't get
            // their own files/plugins
            "./www/static/js/miscellaneous/miscellaneous.js",

            // Feature tours
            "./www/static/js/tour/tour.js",

            // MS Immersive Reader
            "./www/static/js/immersive-reader.js",

            // For exporting a calendar link.
            "./www/static/js/calendar/jquery.getExportCalendarLink.js",

            // Global property storage
            "./www/static/js/global/sbProps.ts",
        ],
        masonry: {
            import: ["./www/static/js/masonry/masonry.js"],
            dependOn: "main", // ensures jQuery is loaded prior
        },
        mage: {
            import: ["./www/static/js/form/jquery.mage.js"],
            dependOn: "main", // ensures jQuery is loaded prior
        },

        // Libraries
        intellischools: "./www/static/js/intellischools/schoolboxIntellischools.js",
        clipboard: "./www/static/js/clipboard/clipboard.js",
        strtotime: "./www/static/js/alaress-datetime-picker/strtotime.js",
        highcharts: "./www/static/js/vendors/highcharts/highcharts.js",
        highchartsMore: "./www/static/js/vendors/highcharts/highchartsMore.js",

        // Search
        headerSearch: "./templates/core/include/vue/headerSearch.js",
        searchPage: "./library/Schoolbox/Search/templates/vue/searchPage.ts",

        // Toast
        sbxToast: "./vue/components/toast/sbxToast.ts",

        // Calendars
        sbFullCalendar: "./library/Schoolbox/Calendar/vue/sbFullCalendar.js",
        timetableCalendar: "./library/Schoolbox/Calendar/vue/timetableCalendar.js",
        assessmentCalendar: "./library/Schoolbox/Learning/Assessment/Calendar/vue/assessmentCalendar.js",
        upcomingDueWorkCalendar: "./library/Schoolbox/Learning/Upcoming/vue/upcomingDueWorkCalendar.js",
        resourceBookingItemCalendar: "./library/Schoolbox/ResourceBooking/Item/vue/resourceBookingItemCalendar.js",
        resourceBookingCategoryCalendar:
            "./library/Schoolbox/ResourceBooking/Category/vue/resourceBookingCategoryCalendar.js",
        calendarComponent: "./library/Schoolbox/Calendar/vue/calendarComponent.js",
        calendarEventDetails: "./library/Schoolbox/Calendar/Event/templates/vue/calendarEventDetails.js",

        // News related
        visitationListFull: "./library/Schoolbox/Comms/News/Statistics/templates/visitation/visitationListFull.js",
        articleList: "./library/Schoolbox/Comms/News/templates/vue/articleList.js",
        articleThreads: "./library/Schoolbox/Comms/News/templates/vue/articleThreads.js",
        newsAuthored: "./library/Schoolbox/Comms/News/vue/newsAuthored.js",
        newsItemAudienceSelect: "./library/Schoolbox/Comms/News/vue/newsItemAudienceSelect.js",
        newsItemAudienceInfo: "./library/Schoolbox/Comms/News/vue/newsItemAudienceInfo.js",
        newsItemFormActions: "./library/Schoolbox/Comms/News/vue/newsItemFormActions.js",
        newsRssLink: "./library/Schoolbox/Comms/News/vue/newsRssLink.js",

        // Learning
        learningMomentsEvidenceDetail:
            "./library/Schoolbox/Learning/Moment/Evidence/vue/learningMomentsEvidenceDetail.js",
        userProfileFeeds: "./library/Schoolbox/Learning/vue/user-profile/userProfileFeeds.js",
        badgeList: "./library/Schoolbox/Learning/Badge/vue/badgeList.js",
        badgeListForm: "./library/Schoolbox/Learning/Badge/Award/vue/badgeListForm.js",
        badgeReport: "./library/Schoolbox/Learning/Badge/Award/vue/badgeReport.js",
        awardWithEvidenceBadgeList: "./library/Schoolbox/Learning/Badge/Award/vue/awardWithEvidenceBadgeList.js",
        evidenceMediaRecording: "./library/Schoolbox/Learning/Moment/Evidence/vue/evidenceMediaRecording.js",
        checkShareWorkButtons: "./library/Schoolbox/Learning/Moment/Evidence/templates/js/checkShareWorkButtons.js",
        similarityReport:
            "./library/Schoolbox/Learning/Assessment/templates/dueWork/vue/similarityReport/similarityReport.js",
        linkedActivities: "./library/Schoolbox/Learning/Assessment/templates/lessonPlan/vue/linkedActivities.ts",

        // Assessment
        unitPlanner: "./library/Schoolbox/Assessment/Course/Plan/templates/vue/unitPlanner.ts",
        horizontalMarking:
            "./library/Schoolbox/Learning/Learnosity/templates/vue/horizontalMarking/horizontalMarking.ts",
        quizFeedback: "./library/Schoolbox/Learning/Learnosity/templates/vue/feedback/quizFeedback.ts",
        quizContent: "./library/Schoolbox/Learning/Learnosity/templates/js/quizContent.ts",

        // Textboxes
        textboxList: "./library/Schoolbox/Resource/Textbox/vue/textboxList.js",

        // Admin
        adminGrades: "./library/Schoolbox/Admin/Grades/vue/adminGrades.js",
        adminSettingsSearch: "./library/Schoolbox/Admin/Navigation/vue/adminSettingsSearch.ts",
        staleUsersReport: "./library/Schoolbox/Admin/User/vue/staleUsersReports/staleUsersReport.js",
        userBulkModify: "./library/Schoolbox/Admin/User/vue/userBulkModify/userBulkModify.js",
        adminList: "./library/Schoolbox/Admin/Lists/templates/vue/adminList.js",
        saveAdminSettings: "./library/Schoolbox/Admin/Navigation/vue/saveAdminSettings.js",
        shapsList: "./library/Schoolbox/Admin/SHAPS/vue/shapsList.js",
        usageGlobal: "./library/Schoolbox/Admin/Usage/vue/global/usageGlobal.js",
        usageSpecific: "./library/Schoolbox/Admin/Usage/vue/specific/usageSpecific.js",
        emailDkim: "./library/Schoolbox/Admin/EmailDKIM/vue/emailDkim.js",
        loadLayout: "./www/static/js/masonry/layout/loadLayout.ts",
        courseBuilder: "./library/Schoolbox/Admin/CourseBuilder/templates/vue/courseBuilder.ts",
        oauthClientList: "./library/Schoolbox/Admin/Oauth/Client/vue/oauthClientList.ts",

        //Vue component to display data in a table
        dataTable: "./vue/components/dataTable/dataTable.ts",

        // Record Media Modal
        recordMediaModal: "./templates/core/resources/filesystem/vue/recordMediaModal.js",

        // Class
        classList: "./library/Schoolbox/Learning/vue/classList.js",
        classSelector: "./library/Schoolbox/Assessment/Course/templates/vue/classSelector.js",

        // Groups
        manageGroups: "./library/Schoolbox/Group/vue/manageGroups.js",
        manageGroup: "./library/Schoolbox/Group/Manage/vue/manageGroup.js",

        // Curriculum
        manageCurriculum: "./library/Schoolbox/Curriculum/templates/vue/manageCurriculum/manageCurriculum.js",
        curriculumSelector: "./library/Schoolbox/Curriculum/templates/vue/curriculumSelector/curriculumSelector.js",
        assessmentCodes: "./library/Schoolbox/Curriculum/templates/vue/assessmentCodes/assessmentCodes.js",
        overviewComponent: "./library/Schoolbox/Curriculum/templates/vue/overviewComponent/overviewComponent.js",

        // QR codes
        manageQrCodes: "./library/Schoolbox/Qrcode/vue/manageQrCodes.js",

        // Pastoral
        pastoralRecordCommentsPublic: "./library/Schoolbox/Pastoral/templates/vue/pastoralRecordCommentsPublic.js",
        pastoralRecordCommentsPrivate: "./library/Schoolbox/Pastoral/templates/vue/pastoralRecordCommentsPrivate.js",
        pastoralClassReport: "./library/Schoolbox/Pastoral/ClassReport/vue/pastoralClassReport.js",

        // Resource
        googleFiles: "./library/Schoolbox/Resource/GoogleFile/templates/component/js/googleFiles.js",
        viewAllFiles: "./library/Schoolbox/Resource/Browser/templates/vue/viewAllFiles.js",

        // Canvas
        canvasAssignments: "./library/Schoolbox/Canvas/templates/vue/canvasAssignments.js",

        // Temporary Typescript testing playground, to be deleted in future
        typescriptTest: "./library/Schoolbox/Greenhouse/templates/ts/typescriptTest.ts",

        //CKEditor
        editor: {
            import: ["./www/static/js/editor/editor.ts"],
            // ensure main is loaded first to avoid webpack bundle errors when the 'mage' bundle is on the same page
            // from what I can tell adding dependOn scopes the bundle differently so there's no conflicts
            dependOn: "main",
        },

        //Countdown
        countdown: "./www/static/js/countdown/countdown.ts", // For countdown component on homepage
        evaluation: "./www/static/js/countdown/evaluation.ts", // For quiz evaluation countdown

        // Slideshow
        slideshow: "./www/static/js/slideshow/slideshow.ts",

        // Paperly Dashboard Component
        paperlyDashboardComponent:
            "./library/Schoolbox/Paperly/Component/Dashboard/templates/vue/paperlyDashboardComponent.ts",

        // Image Lightbox
        imageLightbox: "./www/static/js/image-lightbox/imageLightbox.ts",
        imageLightboxInit: "./www/static/js/image-lightbox/imageLightboxInit.ts",

        // Colour Picker
        colourPicker: "./www/static/js/colour-picker/colour-picker.ts",

        // Markbook Pop Out
        popOutMenuMarkbook: "./vue/components/menu/popOutMenuMarkbook.js",

        // Birthday pop up
        birthdaySurprise: "./vue/components/birthday/birthdaySurprise.ts",

        //Sass
        core: "./www/static/css/core/core.scss",
        schoolboxCkStyles: "./www/static/css/core/schoolbox-ck-content.scss",
        learnosity: "./www/static/css/learnosity/learnosity.scss",

        // Firebase
        firebaseInit: "./www/static/js/firebase/firebase-init.ts",
        firebaseMessaging: "./www/static/js/firebase/firebase-messaging.ts",
    },
    output: {
        path: path.resolve(__dirname, "www/static/js/bundle"),
        publicPath: "/static/js/bundle/",
        filename: "[name].js",
    },
    module: {
        // https://webpack.js.org/configuration/module/
        rules: [
            {
                test: require.resolve("dayjs"), // see https://webpack.js.org/loaders/expose-loader/ - expose these to the global scope, allowing access to the properties in twig / other JS files
                loader: "expose-loader",
                options: {
                    exposes: { globalName: "dayjs", override: true },
                },
            },
            {
                test: require.resolve("highcharts"),
                loader: "expose-loader",
                options: {
                    exposes: { globalName: "Highcharts", override: true },
                },
            },
            {
                test: /\.ts(x?)$/, // typescript uses the same babel setup as JS files, but also uses ts-loader to convert the ts to js
                exclude: /node_modules/,
                use: [
                    {
                        loader: "ts-loader",
                        options: {
                            appendTsSuffixTo: [/\.vue$/],
                            transpileOnly: true,
                        },
                    },
                ],
            },
            {
                test: /\.vue$/,
                loader: "vue-loader", // to handle .vue files
                options: {
                    appendTsSuffixTo: [/\.vue$/],
                    compilerOptions: {
                        isCustomElement: (tag) => tag.startsWith("sbx-"), // allow sbx- tags in vue templates, which is our custom element prefix
                        whitespace: "preserve",
                    },
                },
            },
            {
                test: /\.css$/,
                exclude: /ckeditor5-[^/\\]+[/\\]theme[/\\].+\.css$/, // ensure ckeditor5 related styling setup below isn't applied elsewhere
                use: [
                    { loader: "vue-style-loader" },
                    {
                        loader: "style-loader",
                        options: {
                            esModule: false, // enforce CommonJS instead https://www.npmjs.com/package/style-loader#esmodule
                        },
                    },
                    { loader: "css-loader" },
                ],
            },
            {
                test: /ckeditor5-[^/\\]+[/\\]theme[/\\].+\.css$/,
                use: [
                    {
                        loader: "style-loader",
                        options: {
                            injectType: "singletonStyleTag", // applies all styles within in <style> tag
                            attributes: {
                                "data-cke": true, // adds data-cke attribute to the <style> tag
                            },
                        },
                    },
                    "css-loader",
                    {
                        loader: "postcss-loader",
                        options: {
                            postcssOptions: styles.getPostCssConfig({
                                themeImporter: {
                                    themePath: require.resolve("@ckeditor/ckeditor5-theme-lark"),
                                },
                                minify: true,
                            }),
                        },
                    },
                ],
            },
            {
                test: /\.scss$/,
                oneOf: [
                    // this allows us to only perform the below on vue files, then other scss files follow the following
                    {
                        resourceQuery: /^\?vue/,
                        use: [
                            { loader: "vue-style-loader" },
                            {
                                loader: "css-loader",
                                options: {
                                    url: false, // don't validate image urls as they error, so we trust rely on checking in browser
                                },
                            },
                            {
                                loader: "sass-loader",
                            },
                        ],
                    },
                    {
                        use: [
                            {
                                loader: "file-loader", // to emit the css `main` and `editor` into the /core directory
                                options: {
                                    name: "[name].css",
                                    outputPath: "../../css/core/",
                                },
                            },
                            { loader: "extract-loader" },
                            {
                                loader: "css-loader",
                                options: {
                                    url: false, // don't validate image urls as they error, so we just rely on checking in browser
                                    modules: false, // disable css modules for all files: https://github.com/css-modules/css-modules
                                },
                            },
                            {
                                loader: "sass-loader", // to load the sass and export it to css
                                options: {
                                    sassOptions: {
                                        indentWidth: 4,
                                        includePaths: ["../../css/core/"], // where our base css file is loaded
                                    },
                                },
                            },
                        ],
                    },
                ],
            },
            {
                // to process svg files in the ckeditor5/theme/icons folder as well as the @caspingus/lt/src/authoring/extensions/ui/imageUploader/assets/ folder (for the Learnosity image uploader extension)
                test: /(ckeditor5-[^/\\]+[/\\]theme[/\\]icons[/\\][^/\\]+\.svg$)|(@caspingus[/\\]lt[/\\]src[/\\]authoring[/\\]extensions[/\\]ui[/\\]imageUploader[/\\]assets[/\\][^/\\]+\.svg$)/,
                use: ["raw-loader"],
            },
        ],
    },
    externals: {
        vue: "Vue", // as Vue is loaded separately via a <script> tag, we need to notify webpack that it is available globally, so any .vue files will still bundle as expected
    },
    cache: true,
    resolve: {
        // https://webpack.js.org/configuration/resolve/
        alias: {
            "@schoolboxLibrary": path.resolve(__dirname, "library/Schoolbox"),
            "@schoolboxCalendarEvent": path.resolve(__dirname, "library/Schoolbox/Calendar/Event/templates/vue"),
            "@schoolboxCommsNews": path.resolve(__dirname, "library/Schoolbox/Comms/News"),
            "@schoolboxLearningMomentEvidence": path.resolve(
                __dirname,
                "library/Schoolbox/Learning/Moment/Evidence/vue",
            ),
            "@schoolboxCurriculum": path.resolve(__dirname, "library/Schoolbox/Curriculum/templates/"),
            "@schoolboxBadge": path.resolve(__dirname, "library/Schoolbox/Learning/Badge/"),
            "@schoolboxAssessment": path.resolve(__dirname, "library/Schoolbox/Assessment/"),
            "@schoolboxLearnosity": path.resolve(__dirname, "library/Schoolbox/Learning/Learnosity/templates/"),
            "@schoolboxVue": path.resolve(__dirname, "vue"),
            "@js": path.resolve(__dirname, "www/static/js"),
            "@css": path.resolve(__dirname, "www/static/css"),
        },
        extensions: ["*", ".js", ".vue", ".json", ".ts"],
    },
};
