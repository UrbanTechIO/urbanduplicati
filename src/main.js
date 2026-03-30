import { generateFilePath } from '@nextcloud/router'
import { getRequestToken } from '@nextcloud/auth'
import { Tooltip } from '@nextcloud/vue'
import { sync } from 'vuex-router-sync'
import { translate, translatePlural } from '@nextcloud/l10n'
import '@nextcloud/dialogs/style.css'
import Vue from 'vue'

import App from './App.vue'
import router from './router/index.js'
import store from './store/index.js'

// eslint-disable-next-line
__webpack_nonce__ = btoa(getRequestToken())
// eslint-disable-next-line
__webpack_public_path__ = generateFilePath('urbanduplicati', '', 'js/')

sync(store, router)

Vue.directive('tooltip', Tooltip)
Vue.prototype.t = translate
Vue.prototype.n = translatePlural
Vue.prototype.OC = window.OC
Vue.prototype.OCA = window.OCA

export default new Vue({
  el: '#urbanduplicati-root',
  router,
  store,
  render: h => h(App),
})
