const path = require('path')
const webpackConfig = require('@nextcloud/webpack-vue-config')
const webpackRules = require('@nextcloud/webpack-vue-config/rules')

const buildMode = process.env.NODE_ENV
const isDev = buildMode === 'development'
webpackConfig.devtool = isDev ? 'cheap-source-map' : 'source-map'
webpackConfig.stats = { colors: true, modules: false }

const appId = 'urbanduplicati'
webpackConfig.entry = {
  main:  { import: path.join(__dirname, 'src', 'main.js'),       filename: appId + '-main.js' },
  admin: { import: path.join(__dirname, 'src', 'main-admin.js'), filename: appId + '-admin.js' },
}

webpackConfig.module.rules = Object.values(webpackRules)
module.exports = webpackConfig
