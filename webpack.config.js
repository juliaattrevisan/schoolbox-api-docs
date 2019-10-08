const path = require('path');
const { VueLoaderPlugin } = require('vue-loader')

module.exports = {
  entry: {
    strtotime: "./www/javascript/shared/alaress-datetime-picker/strtotime.main.js",
    hybridbar: path.resolve(__dirname, 'resources/js/hybrid-bar/index.js'),
    fullCalendar: "./library/Schoolbox/Calendar/vue/fullCalendar.main.js",
    assessmentCalendar: "./library/Schoolbox/Learning/Assessment/Calendar/vue/calendar.main.js",
    upcomingDueworkCalendar: "./library/Schoolbox/Learning/Upcoming/vue/calendar.main.js",
    resourceBookingItemCalendar: "./library/Schoolbox/ResourceBooking/Item/vue/calendar.main.js",
    resourceBookingCategoryCalendar: "./library/Schoolbox/ResourceBooking/Category/vue/calendar.main.js",
    eventDetails: "./library/Schoolbox/Calendar/Event/templates/vue/index.js"
  },
  output: {
    path: path.resolve(__dirname, "www/javascript/components"),
    filename: "[name].bundle.js"
  },
  devtool: "source-map",
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
                                "defaults",
                                "IE 11"
                            ],
                        },
                        useBuiltIns: 'usage',
                        corejs: 3
                    }
                ]
            ],
            plugins: [
                "@babel/plugin-proposal-optional-chaining",
                "@babel/plugin-transform-object-assign",
                "@babel/plugin-transform-shorthand-properties"
            ]
          }
        }
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
        use: ['vue-style-loader', 'css-loader']
      },
      {
        test: /\.scss$/,
        use: [
          'vue-style-loader',
          'css-loader',
          'sass-loader'
        ]
      },
      { enforce: "pre", test: /\.js$/, loader: "source-map-loader" }
    ]
  },
  resolve: {
    alias: {
      'vue$': 'vue/dist/vue.esm.js',
      '@schoolbox': path.resolve(
          __dirname,
          "vue/components"
      ),
    },
    extensions: ['*', '.js', '.vue', '.json']
  },
  plugins: [
    new VueLoaderPlugin()
  ]
};
