import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'
import { showError, showSuccess } from '@nextcloud/dialogs'

const state = { rules: [], loading: false }
const mutations = {
  setRules(state, rules)  { state.rules = rules },
  setLoading(state, v)    { state.loading = v },
}
const getters = { rules: s => s.rules, protectionLoading: s => s.loading }
const actions = {
  async getRules({ commit }) {
    commit('setLoading', true)
    try { const res = await axios.get(generateUrl('/apps/urbanduplicati/api/v1/protection')); commit('setRules', res.data.rules || res.data) }
    catch(e) { showError('Failed to load protection rules') }
    finally  { commit('setLoading', false) }
  },
  async createRule({ dispatch }, rule) {
    try { await axios.post(generateUrl('/apps/urbanduplicati/api/v1/protection'), rule); await dispatch('getRules'); showSuccess('Protection rule added') }
    catch(e) { showError('Failed to add rule') }
  },
  async deleteRule({ dispatch }, id) {
    try { await axios.delete(generateUrl(`/apps/urbanduplicati/api/v1/protection/${id}`)); await dispatch('getRules'); showSuccess('Rule removed') }
    catch(e) { showError('Failed to remove rule') }
  },
}
export default { state, mutations, getters, actions }
