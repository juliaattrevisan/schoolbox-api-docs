const path = require('path');
const { VueLoaderPlugin } = require('vue-loader')

module.exports = {
  entry: {
    hybridbar: path.resolve(__dirname, 'resources/js/hybrid-bar/index.js'),
    clickoutside: path.resolve(__dirname, 'resources/js/click-outside/index.js'),
    fullCalendar: "./library/Schoolbox/Calendar/vue/fullCalendar.main.js"
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
            presets: ['@babel/preset-env'],
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
