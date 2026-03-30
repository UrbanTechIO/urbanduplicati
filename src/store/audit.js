import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'
import { showError } from '@nextcloud/dialogs'

const state = { log: [], stats: {}, loading: false, page: 0, filterAction: null }
const mutations = {
  setLog(state, data)       { state.log = data.entries || data; state.stats = data.stats || {} },
  setLoading(state, v)      { state.loading = v },
  setPage(state, p)         { state.page = p },
  setFilter(state, f)       { state.filterAction = f },
}
const getters = {
  auditLog:     s => s.log,
  auditStats:   s => s.stats,
  auditLoading: s => s.loading,
  auditPage:    s => s.page,
  auditFilter:  s => s.filterAction,
}
const actions = {
  async getAuditLog({ commit, state }) {
    commit('setLoading', true)
    try {
      const params = { page: state.page, limit: 50 }
      if (state.filterAction) params.action = state.filterAction
      const res = await axios.get(generateUrl('/apps/urbanduplicati/api/v1/audit'), { params })
      commit('setLog', res.data)
    } catch(e) { showError('Failed to load audit log') }
    finally    { commit('setLoading', false) }
  },
  async exportCsv() {
    window.location.href = generateUrl('/apps/urbanduplicati/api/v1/audit/export')
  },
  auditNextPage({ commit, dispatch, state }) { commit('setPage', state.page + 1); dispatch('getAuditLog') },
  auditPrevPage({ commit, dispatch, state }) { if (state.page > 0) { commit('setPage', state.page - 1); dispatch('getAuditLog') } },
}
export default { state, mutations, getters, actions }
