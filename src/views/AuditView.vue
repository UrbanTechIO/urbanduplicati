<template>
  <div class="ud-page">
    <div class="ud-page-header">
      <h2>📋 {{ t('urbanduplicati','Audit Log') }}</h2>
      <NcButton type="secondary" @click="exportCsv">{{ t('urbanduplicati','Export CSV') }}</NcButton>
    </div>
    <div class="ud-audit-stats" v-if="Object.keys(auditStats).length">
      <div v-for="(stat, action) in auditStats" :key="action" class="ud-stat-card">
        <span class="ud-stat-action">{{ action }}</span>
        <span class="ud-stat-count">{{ stat.count }}</span>
        <span class="ud-stat-size">{{ formatBytes(stat.total_size) }}</span>
      </div>
    </div>
    <div class="ud-audit-filter">
      <select v-model="filterValue" @change="applyFilter">
        <option :value="null">{{ t('urbanduplicati','All actions') }}</option>
        <option value="deleted">{{ t('urbanduplicati','Deleted') }}</option>
        <option value="dry_run">{{ t('urbanduplicati','Dry-run') }}</option>
        <option value="protected_skip">{{ t('urbanduplicati','Protected skip') }}</option>
        <option value="removed">{{ t('urbanduplicati','Removed') }}</option>
      </select>
    </div>
    <div v-if="auditLoading" class="ud-loading"><NcLoadingIcon /></div>
    <div v-else-if="auditLog.length === 0" class="ud-empty">{{ t('urbanduplicati','No audit entries yet.') }}</div>
    <table v-else class="ud-audit-table">
      <thead><tr>
        <th>{{ t('urbanduplicati','When') }}</th><th>{{ t('urbanduplicati','User') }}</th>
        <th>{{ t('urbanduplicati','Action') }}</th><th>{{ t('urbanduplicati','File') }}</th>
        <th>{{ t('urbanduplicati','Size') }}</th><th>{{ t('urbanduplicati','Reason') }}</th>
      </tr></thead>
      <tbody>
        <tr v-for="entry in auditLog" :key="entry.id" :class="'ud-audit-row--'+entry.action">
          <td>{{ formatDate(entry.created_at) }}</td><td>{{ entry.user_id }}</td>
          <td><span :class="['ud-badge','ud-badge--'+entry.action]">{{ entry.action }}</span></td>
          <td class="ud-audit-path" :title="entry.file_path">{{ shortPath(entry.file_path) }}</td>
          <td>{{ formatBytes(entry.file_size) }}</td>
          <td class="ud-audit-reason">{{ entry.reason }}</td>
        </tr>
      </tbody>
    </table>
    <div class="ud-pagination">
      <NcButton :disabled="auditPage === 0" @click="auditPrevPage">← {{ t('urbanduplicati','Prev') }}</NcButton>
      <span>{{ t('urbanduplicati','Page') }} {{ auditPage + 1 }}</span>
      <NcButton @click="auditNextPage">{{ t('urbanduplicati','Next') }} →</NcButton>
    </div>
  </div>
</template>
<script>
import { NcButton, NcLoadingIcon } from '@nextcloud/vue'
import { mapGetters, mapActions } from 'vuex'
export default {
  name: 'AuditView',
  components: { NcButton, NcLoadingIcon },
  data() { return { filterValue: null } },
  computed: { ...mapGetters(['auditLog','auditStats','auditLoading','auditPage']) },
  created() { this.getAuditLog() },
  methods: {
    ...mapActions(['getAuditLog','exportCsv','auditNextPage','auditPrevPage']),
    applyFilter() { this.$store.commit('setFilter', this.filterValue); this.getAuditLog() },
    formatDate(ts) { return ts ? new Date(ts*1000).toLocaleString() : '—' },
    formatBytes(b) { if(!b)return'—'; if(b>=1073741824)return(b/1073741824).toFixed(1)+' GB'; if(b>=1048576)return(b/1048576).toFixed(1)+' MB'; return Math.round(b/1024)+' KB' },
    shortPath(p)   { return p ? p.split('/').slice(-2).join('/') : '—' },
  },
}
</script>

<style scoped>
.ud-page { max-width: 1000px; margin: 0 auto; padding: 20px 40px; box-sizing: border-box; }
.ud-page-header { display: flex; align-items: center; gap: 16px; margin-bottom: 20px; }
.ud-page-header h2 { margin: 0; font-size: 1.3em; font-weight: 600; flex: 1; }
.ud-audit-table { width: 100%; border-collapse: collapse; }
.ud-audit-table th,
.ud-audit-table td { padding: 8px 16px; text-align: left; white-space: nowrap; }
.ud-audit-table td.ud-audit-path { white-space: normal; word-break: break-all; max-width: 280px; }
.ud-audit-table td.ud-audit-reason { white-space: normal; max-width: 160px; }
</style>
