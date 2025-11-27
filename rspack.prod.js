/**
 * Webpack configuration for production, primarily used to provide
 * minified versions of our vendor files.
 */

const { merge } = require("webpack-merge");
const common = require("./webpack.common.js");
const { VueLoaderPlugin } = require("vue-loader"); // https://www.npmjs.com/package/vue-loader
const RemoveEmptyScriptsPlugin = require("webpack-remove-empty-scripts"); // https://www.npmjs.com/package/webpack-remove-empty-scripts
const MergeIntoSingleFilePlugin = require("webpack-merge-and-include-globally"); // https://www.npmjs.com/package/webpack-merge-and-include-globally

module.exports = merge(common, {
    mode: "production",
    plugins: [
        new VueLoaderPlugin(), // to handle .vue files
        new RemoveEmptyScriptsPlugin(), // to remove .js files that are empty after exporting css
        new MergeIntoSingleFilePlugin({
            // to merge all vendor files into a single file
            files: {
                "vendors.js": [
                    // Vue
                    "./node_modules/vue/dist/vue.global.prod.js",
                    // jQuery
                    "./node_modules/jquery/dist/jquery.min.js",
                    "./node_modules/jquery-validation/dist/jquery.validate.min.js",
                    // jQuery UI
                    "./node_modules/jquery-ui/dist/jquery-ui.min.js",
                    // jQueryUI Touch Punch
                    "./node_modules/@andxor/jquery-ui-touch-punch-fix/jquery.ui.touch-punch.js",
                    // Sound / video player
                    "./node_modules/mediaelement/build/mediaelement-and-player.min.js",
                    // For drag/drop of resources
                    "./node_modules/dropzone/dist/dropzone-min.js",
                    // Flatpickr date selector
                    "./node_modules/flatpickr/dist/flatpickr.min.js",
                    // For feature tours
                    "./node_modules/intro.js/minified/intro.min.js",
                    // For FullCalendar within the date picker ONLY
                    "./www/static/js/fullcalendar/schoolbox-fullcalendar.min.js",
                ],
                "dataTables.js": [
                    // DataTables
                    "./node_modules/datatables.net/js/jquery.dataTables.min.js",
                    "./node_modules/datatables.net-fixedcolumns/js/dataTables.fixedColumns.min.js",
                ],
            },
        }),
    ],
});
