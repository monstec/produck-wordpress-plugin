//const webpack = require("webpack");

module.exports = {
  output: {
    filename: "[name].js",
    libraryTarget: "umd",
    library: "[name]Lib",
    umdNamedDefine: true,
  },
  devtool: "source-map",
  module: {
    rules: [
      {
        test: /\.js$/,
        exclude: /\.min.js$/,
        use: {
          loader: "babel-loader",
          options: {
            presets: ["@babel/preset-env"],
          },
        },
      },
    ],
  },
};
