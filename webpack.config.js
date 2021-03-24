const path = require('path');
const {VueLoaderPlugin} = require('vue-loader');
const FixStyleOnlyEntriesPlugin = require('webpack-fix-style-only-entries'); //https://www.npmjs.com/package/webpack-fix-style-only-entries

module.exports = {
    entry: {

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
        visitationListFull: './library/Schoolbox/Comms/News/Statistics/templates/visitation/init-visitation-list-full.js',
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
        publicPath: "/javascript/components/",
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
