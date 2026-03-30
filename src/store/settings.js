import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'
import { showError, showSuccess } from '@nextcloud/dialogs'

const state = { settings: {}, systemInfo: {}, loading: false }
const mutations = {
  setSettings(state, s)    { state.settings = s },
  setSystemInfo(state, s)  { state.systemInfo = s },
  setLoading(state, v)     { state.loading = v },
}
const getters = {
  settings:        s => s.settings,
  systemInfo:      s => s.systemInfo,
  settingsLoading: s => s.loading,
  settingByName:   s => name => s.settings[name],
}
const actions = {
  async getSettings({ commit }) {
    commit('setLoading', true)
    try { const res = await axios.get(generateUrl('/apps/urbanduplicati/api/v1/settings')); commit('setSettings', res.data.settings || res.data) }
    catch(e) { showError('Failed to load settings') }
    finally  { commit('setLoading', false) }
  },
  async saveSettings({ commit }, settings) {
    try { const res = await axios.put(generateUrl('/apps/urbanduplicati/api/v1/settings'), settings); commit('setSettings', res.data.settings || settings); showSuccess('Settings saved') }
    catch(e) { showError('Failed to save settings') }
  },
  async getSystemInfo({ commit }) {
    try { const res = await axios.get(generateUrl('/apps/urbanduplicati/api/v1/settings/system')); commit('setSystemInfo', res.data) }
    catch(e) { console.error('Failed to load system info') }
  },
}
export default { state, mutations, getters, actions }
