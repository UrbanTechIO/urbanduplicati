import { translate, translatePlural } from '@nextcloud/l10n'
import Vue from 'vue'
import AdminApp from './AdminApp.vue'
import store from './store/index.js'

Vue.prototype.t = translate
Vue.prototype.n = translatePlural
Vue.prototype.OC = window.OC

export default new Vue({
  el: '#urbanduplicati-admin-root',
  store,
  render: h => h(AdminApp),
})
