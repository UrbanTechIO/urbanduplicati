import Vue from 'vue'
import Vuex, { Store } from 'vuex'
import tasks from './tasks.js'
import protection from './protection.js'
import audit from './audit.js'
import settings from './settings.js'

Vue.use(Vuex)

export default new Store({
  modules: { tasks, protection, audit, settings },
  strict: process.env.NODE_ENV !== 'production',
})
