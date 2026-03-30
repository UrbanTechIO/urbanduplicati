<template>
  <div class="ud-page">
    <div class="ud-page-header">
      <h2>{{ t('urbanduplicati', 'Duplicate Scans') }}</h2>
      <NcButton type="primary" @click="showNewTask = true">
        + {{ t('urbanduplicati', 'New Scan') }}
      </NcButton>
    </div>

    <div v-if="loading && tasks.length === 0" class="ud-loading">
      <NcLoadingIcon /> {{ t('urbanduplicati', 'Loading…') }}
    </div>

    <div v-else-if="tasks.length === 0" class="ud-empty">
      <p>{{ t('urbanduplicati', 'No scans yet. Create your first scan to find duplicate media.') }}</p>
      <NcButton type="primary" @click="showNewTask = true">{{ t('urbanduplicati', 'Create Scan') }}</NcButton>
    </div>

    <ul v-else class="ud-task-list">
      <li v-for="task in tasks" :key="task.id" class="ud-task-item">
        <div class="ud-task-item__body">
          <div class="ud-task-item__left">
            <span :class="['ud-badge', 'ud-badge--' + getStatus(task)]">{{ getStatusLabel(task) }}</span>
          </div>
          <div class="ud-task-item__center"
            :class="{ 'ud-clickable': hasResults(task) }"
            @click="hasResults(task) && $router.push({ name: 'task-detail', params: { id: String(task.id) } })">
            <div class="ud-task-item__title">{{ taskTitle(task) }}</div>
            <div class="ud-task-item__dates">
              {{ formatDate(task.created_time) }}
              <template v-if="Number(task.finished_time) > 0"> — {{ formatDate(task.finished_time) }}</template>
            </div>
            <NcProgressBar
              class="ud-task-progress"
              :value="progressPct(task)"
              size="small"
              :error="getStatus(task) === 'error'" />
          </div>
          <div class="ud-task-item__actions">
            <NcButton v-if="hasResults(task)" type="secondary" size="small"
              @click="$router.push({ name: 'task-detail', params: { id: String(task.id) } })">
              🔍 {{ t('urbanduplicati', 'Review') }}
            </NcButton>
            <NcButton v-if="isRunning(task)" type="error" size="small"
              :disabled="stopping === task.id"
              @click="stopTask(task.id)">
              ⏹ {{ stopping === task.id ? t('urbanduplicati', 'Stopping…') : t('urbanduplicati', 'Stop') }}
            </NcButton>
            <NcButton v-if="!isRunning(task)" type="secondary" size="small"
              @click="restartTask(task)">
              🔄 {{ t('urbanduplicati', 'Rescan') }}
            </NcButton>
            <NcButton type="tertiary" size="small" @click="confirmDelete(task)">🗑</NcButton>
          </div>
        </div>
      </li>
    </ul>

    <NcModal v-if="showNewTask" @closing="showNewTask = false">
      <div class="ud-modal-content">
        <h3>{{ t('urbanduplicati', 'New Scan') }}</h3>
        <TasksNew @created="onCreated" @cancel="showNewTask = false" />
      </div>
    </NcModal>

    <NcModal v-if="deleteTarget" @closing="deleteTarget = null">
      <div class="ud-modal-content">
        <h3>{{ t('urbanduplicati', 'Delete scan?') }}</h3>
        <p>{{ t('urbanduplicati', 'This removes the scan record but does not delete any files.') }}</p>
        <div class="ud-form-actions">
          <NcButton @click="deleteTarget = null">{{ t('urbanduplicati', 'Cancel') }}</NcButton>
          <NcButton type="error" @click="doDelete">{{ t('urbanduplicati', 'Delete') }}</NcButton>
        </div>
      </div>
    </NcModal>
  </div>
</template>

<script>
import { NcButton, NcModal, NcLoadingIcon, NcProgressBar } from '@nextcloud/vue'
import { mapGetters, mapActions } from 'vuex'
import TasksNew from '../components/tasks/TasksNew.vue'

export default {
  name: 'TaskList',
  components: { NcButton, NcModal, NcLoadingIcon, NcProgressBar, TasksNew },
  data() {
    return { showNewTask: false, deleteTarget: null, stopping: null, pollTimer: null }
  },
  computed: { ...mapGetters(['tasks', 'loading']) },
  created() {
    this.getTasks()
    this.pollTimer = setInterval(() => {
      if (this.tasks.some(t => this.isRunning(t) || this.isPending(t))) this.getTasks(true)
    }, 5000)
  },
  beforeDestroy() { clearInterval(this.pollTimer) },
  methods: {
    ...mapActions(['getTasks', 'terminateTask', 'deleteTask', 'runTask']),
    isRunning(task) { return Number(task.py_pid) > 0 },
    hasResults(task) { return Number(task.files_scanned) > 0 || Number(task.finished_time) > 0 },
    progressPct(task) {
      if (!task.files_total || Number(task.files_total) === 0) return 0
      return Math.min(100, Math.round((Number(task.files_scanned) / Number(task.files_total)) * 100))
    },
    getStatus(task) {
      if (task.errors && task.errors !== '' && !String(task.errors).startsWith('stopped')) return 'error'
      if (this.isRunning(task)) return 'running'
      if (task.errors && String(task.errors).startsWith('stopped')) return 'stopped'
      if (Number(task.finished_time) > 0) return 'finished'
      return 'pending'
    },
    getStatusLabel(task) {
      return { running: 'Running', finished: 'Finished', error: 'Error', stopped: 'Stopped', pending: 'Pending' }[this.getStatus(task)] || ''
    },
    taskTitle(task) {
      const name = task.name || ('Scan #' + task.id)
      const total = Number(task.files_total)
      const scanned = Number(task.files_scanned)
      const size = this.formatBytes(task.files_total_size)
      if (this.isRunning(task)) return `${name} — ${scanned}/${total} files (${size})`
      return `${name} — ${total} files (${size})`
    },
    async stopTask(id) { this.stopping = id; await this.terminateTask(id); this.stopping = null },
    async restartTask(task) {
      const settings = typeof task.collector_settings === 'string' ? JSON.parse(task.collector_settings) : (task.collector_settings || {})
      const dirs = typeof task.target_directory_ids === 'string' ? JSON.parse(task.target_directory_ids) : (task.target_directory_ids || [])
      await this.runTask({
        targetDirectoryIds: JSON.stringify(dirs),
        collectorSettings: JSON.stringify(settings),
        excludeList: JSON.stringify({ admin: { mask: [], fileid: [] }, user: { mask: [], fileid: [] } }),
        name: task.name,
      })
      await this.getTasks()
    },
    confirmDelete(task) { this.deleteTarget = task },
    async doDelete() { await this.deleteTask(this.deleteTarget.id); this.deleteTarget = null },
    onCreated() { this.showNewTask = false; this.getTasks() },
    formatBytes(b) {
      if (!b) return '0 B'; b = Number(b)
      if (b >= 1073741824) return (b / 1073741824).toFixed(1) + ' GB'
      if (b >= 1048576) return (b / 1048576).toFixed(1) + ' MB'
      if (b >= 1024) return Math.round(b / 1024) + ' KB'
      return b + ' B'
    },
    formatDate(ts) {
      if (!ts || ts === 0) return '—'
      return new Date(Number(ts) * 1000).toLocaleString()
    },
  },
}
</script>

<style scoped>
.ud-page { padding: 20px 40px; max-width: 860px; margin: 0 auto; }
.ud-page-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 24px; }
.ud-page-header h2 { margin: 0; font-size: 1.4em; font-weight: 600; }
.ud-loading, .ud-empty { text-align: center; padding: 48px; color: var(--color-text-maxcontrast); }
.ud-task-list { list-style: none; padding: 0; margin: 0; display: flex; flex-direction: column; gap: 10px; }
.ud-task-item { border: 1px solid var(--color-border); border-radius: 16px; background: var(--color-main-background); overflow: hidden; }
.ud-task-item__body { display: flex; align-items: center; gap: 16px; padding: 14px 18px; }
.ud-task-item__left { flex-shrink: 0; width: 80px; }
.ud-task-item__center { flex: 1; min-width: 0; }
.ud-clickable { cursor: pointer; }
.ud-clickable:hover .ud-task-item__title { text-decoration: underline; }
.ud-task-item__title { font-weight: 600; font-size: 0.95em; margin-bottom: 2px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.ud-task-item__dates { font-size: 0.8em; color: var(--color-text-maxcontrast); margin-bottom: 6px; }
.ud-task-progress { width: 100%; }
.ud-task-item__actions { display: flex; gap: 6px; flex-shrink: 0; align-items: center; }
.ud-badge { display: inline-block; padding: 3px 10px; border-radius: 12px; font-size: 0.72em; font-weight: 700; text-transform: uppercase; letter-spacing: 0.4px; white-space: nowrap; }
.ud-badge--running  { background: #0082c9; color: #fff; }
.ud-badge--finished { background: #46ba61; color: #fff; }
.ud-badge--error    { background: #e9322d; color: #fff; }
.ud-badge--stopped  { background: #f8a800; color: #fff; }
.ud-badge--pending  { background: var(--color-background-dark); color: var(--color-text-maxcontrast); }
.ud-modal-content { padding: 24px; min-width: 420px; }
.ud-form-actions { display: flex; gap: 8px; justify-content: flex-end; margin-top: 16px; }
</style>
