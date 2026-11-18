const config = require('flarum-webpack-config')();
const TerserPlugin = require('terser-webpack-plugin');

config.devtool = false;
config.optimization.minimizer = [new TerserPlugin({
  extractComments: false,
  terserOptions: { format: { comments: false } },
})];

module.exports = config;
