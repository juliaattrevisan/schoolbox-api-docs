const path = require("path");

module.exports = {
  entry: {
    news: "./library/Schoolbox/Comms/News/react/News.tsx"
  },
  output: {
    path: path.resolve(__dirname, "www/javascript/components"),
    filename: "[name].bundle.js"
  },
  devtool: "source-map",
  resolve: {
    extensions: [".js", ".ts", ".tsx"],
    alias: {
      '@schoolbox': path.resolve(
        __dirname,
        "react/components"
      ),
    }
  },
  module: {
    rules: [
      {
        test: /\.(js)$/,
        exclude: /node_modules/,
        use: {
          loader: "babel-loader"
        }
      },
      { test: /\.tsx?$/, loader: "awesome-typescript-loader" },
      { enforce: "pre", test: /\.js$/, loader: "source-map-loader" }
    ]
  },
  devServer: {
    public: "vagrant.dev.schoolbox.cloud"
  }
};
