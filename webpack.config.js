const path = require('path');
const { VueLoaderPlugin } = require('vue-loader')

module.exports = {
  entry: {
    news: "./src/index.js"
  },
  output: {
    path: path.resolve(__dirname, "www/javascript/components"),
    filename: "[name].bundle.js"
  },
  module: {
    rules: [
      {
        test: /\.js$/,
        exclude: /node_modules/,
        use: {
          loader: "babel-loader"
        }
      },
      {
        test: /\.vue$/,
        loader: 'vue-loader'
      }
    ]
  },
  resolve: {
    alias: {
      'vue$': 'vue/dist/vue.esm.js'
    },
    extensions: ['*', '.js', '.vue', '.json']
  },
  plugins: [
      new VueLoaderPlugin()
  ]
};
