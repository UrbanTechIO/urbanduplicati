import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'
import { showError, showSuccess } from '@nextcloud/dialogs'

const state = {
  tasks: [],
  currentTask: null,
  groups: [],
  totals: null,
  loading: false,
  groupsLoading: false,
  selectedGroups: new Set(),
  page: 0,
  limit: 20,
}

const mutations = {
  setTasks(state, tasks) {
    state.tasks = tasks.sort((a, b) => b.created_time - a.created_time)
  },
  setCurrentTask(state, task)    { state.currentTask = task },
  setGroups(state, data)         { state.groups = data.groups || []; state.totals = data.totals || null },
  setLoading(state, v)           { state.loading = v },
  setGroupsLoading(state, v)     { state.groupsLoading = v },
  setPage(state, p)              { state.page = p },
  toggleGroup(state, id)         {
    if (state.selectedGroups.has(id)) state.selectedGroups.delete(id)
    else state.selectedGroups.add(id)
    state.selectedGroups = new Set(state.selectedGroups)
  },
  clearSelection(state)          { state.selectedGroups = new Set() },
  selectAll(state)               { state.selectedGroups = new Set(state.groups.map(g => g.group_id)) },
}

const getters = {
  tasks:          s => s.tasks,
  currentTask:    s => s.currentTask,
  groups:         s => s.groups,
  totals:         s => s.totals,
  loading:        s => s.loading,
  groupsLoading:  s => s.groupsLoading,
  selectedGroups: s => s.selectedGroups,
  page:           s => s.page,
  limit:          s => s.limit,
}

const actions = {
  async getTasks({ commit }, silent = false) {
    if (!silent) commit('setLoading', true)
    try {
      const res = await axios.get(generateUrl('/apps/urbanduplicati/api/v1/tasks'))
      commit('setTasks', res.data.tasks || res.data)
    } catch(e) {
      if (!silent) showError('Failed to load scans')
    } finally {
      if (!silent) commit('setLoading', false)
    }
  },
  async runTask(ctx, data) {
    return axios.post(generateUrl('/apps/urbanduplicati/api/v1/tasks/run'), data)
  },
  async terminateTask({ dispatch }, taskId) {
    try {
      await axios.post(generateUrl(`/apps/urbanduplicati/api/v1/tasks/${taskId}/terminate`))
      await dispatch('getTasks')
      showSuccess('Scan stopped')
    } catch(e) { showError('Failed to stop scan') }
  },
  async deleteTask({ dispatch }, taskId) {
    try {
      await axios.delete(generateUrl(`/apps/urbanduplicati/api/v1/tasks/${taskId}`))
      await dispatch('getTasks')
    } catch(e) { showError('Failed to delete scan') }
  },
  async getTask({ commit }, taskId) {
    try {
      const res = await axios.get(generateUrl(`/apps/urbanduplicati/api/v1/tasks/${taskId}`))
      commit('setCurrentTask', res.data)
    } catch(e) { showError('Failed to load scan') }
  },
  async getGroups({ commit, state }, { taskId, filter = '' } = {}) {
    commit('setGroupsLoading', true)
    try {
      const url = generateUrl(`/apps/urbanduplicati/api/v1/tasks/${taskId}/groups`) + `?page=${state.page}&limit=${state.limit}` + (filter ? `&filter=${encodeURIComponent(filter)}` : '')
      const res = await axios.get(url)
      commit('setGroups', res.data)
    } catch(e) { showError('Failed to load duplicate groups') }
    finally    { commit('setGroupsLoading', false) }
  },
  async deleteFile({ dispatch }, { taskId, groupId, fileId }) {
    try {
      await axios.delete(generateUrl(`/apps/urbanduplicati/api/v1/tasks/${taskId}/groups/${groupId}/files/${fileId}`))
      showSuccess('File deleted')
      return { success: true }
    } catch(e) {
      const data = e?.response?.data
      if (data?.error === 'live_photo') {
        showError('Live Photo: this video is paired with a photo and cannot be deleted independently.')
        return { success: false, live_photo: true }
      }
      showError('Failed to delete file')
      return { success: false }
    }
  },
  async bulkDelete({ commit }, { taskId, groupIds, deleteProtectedFor = [], deleteUnprotectedAndKeepOne = false, keepFromFolder = '', filterPattern = '', bypassTrash = false, onProgress = null, shouldStop = null }) {
    try {
      const BATCH_SIZE = 1
      let totalDeleted = 0
      let totalSkipped = 0
      let totalLivePhotoSkipped = 0
      let allConflicts = []

      for (let i = 0; i < groupIds.length; i += BATCH_SIZE) {
        // Check stop flag before each batch
        if (shouldStop && shouldStop()) break

        const batch = groupIds.slice(i, i + BATCH_SIZE)
        try {
          const res = await axios.post(generateUrl(`/apps/urbanduplicati/api/v1/tasks/${taskId}/bulk-delete`), {
            group_ids: JSON.stringify(batch),
            delete_protected_for: JSON.stringify(deleteProtectedFor),
            delete_unprotected_keep_one: deleteUnprotectedAndKeepOne,
            keep_from_folder: keepFromFolder,
            filter_pattern: filterPattern,
            bypass_trash: bypassTrash,
          })
          if (res.data) {
            totalDeleted += res.data.deleted || 0
            totalSkipped += res.data.skipped || 0
            totalLivePhotoSkipped += res.data.live_photo_skipped || 0
            if (res.data.conflicts && res.data.conflicts.length > 0) {
              allConflicts = allConflicts.concat(res.data.conflicts)
            }
          }
        } catch (batchErr) {
          // Log but continue — don't let one slow/failed batch abort the whole operation
          console.warn('Batch failed, skipping:', batch, batchErr)
          totalSkipped += batch.length
        }
        try {
          if (onProgress) onProgress(Math.min(i + BATCH_SIZE, groupIds.length), groupIds.length, totalDeleted)
        } catch (progressErr) {
          console.warn('onProgress error (ignored):', progressErr)
        }
      }
      commit('clearSelection')
      if (totalLivePhotoSkipped > 0) {
        showError(`${totalLivePhotoSkipped} Live Photo video(s) were skipped — they cannot be deleted independently of their paired photo.`)
      }
      showSuccess('Duplicates deleted')
      return { success: true, deleted: totalDeleted, skipped: totalSkipped, livePhotoSkipped: totalLivePhotoSkipped, conflicts: allConflicts }
    } catch(e) {
      console.error('bulkDelete outer error:', e && e.message, e && e.stack)
      showError('Bulk delete failed: ' + (e && e.message ? e.message : 'unknown error'))
    }
  },
  async bulkRemove({ dispatch, commit }, { taskId, groupIds }) {
    try {
      await axios.post(generateUrl(`/apps/urbanduplicati/api/v1/tasks/${taskId}/bulk-remove`), { group_ids: JSON.stringify(groupIds) })
      commit('clearSelection')
    } catch(e) { showError('Remove failed') }
  },
  async dryRun(ctx, { taskId, groupIds, deleteProtectedFor = [] }) {
    return axios.post(generateUrl(`/apps/urbanduplicati/api/v1/tasks/${taskId}/dry-run`), {
      group_ids: JSON.stringify(groupIds),
      delete_protected_for: JSON.stringify(deleteProtectedFor),
    })
  },
}

export default { state, mutations, getters, actions }
