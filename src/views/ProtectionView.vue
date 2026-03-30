<template>
  <div class="ud-page">
    <div class="ud-page-header">
      <h2>🛡 {{ t('urbanduplicati','Protection Rules') }}</h2>
      <NcButton type="primary" @click="showAdd = true">+ {{ t('urbanduplicati','Add protected folder') }}</NcButton>
    </div>
    <p class="ud-page-intro">{{ t('urbanduplicati','Files inside protected folders are never deleted, even during bulk operations.') }}</p>
    <div v-if="protectionLoading" class="ud-loading"><NcLoadingIcon /></div>
    <div v-else-if="rules.length === 0" class="ud-empty">{{ t('urbanduplicati','No protection rules yet.') }}</div>
    <table v-else class="ud-rules-table">
      <thead><tr>
        <th>{{ t('urbanduplicati','Folder') }}</th>
        <th>{{ t('urbanduplicati','Label') }}</th>
        <th>{{ t('urbanduplicati','Subfolders') }}</th>
        <th>{{ t('urbanduplicati','Scope') }}</th>
        <th>{{ t('urbanduplicati','Added') }}</th>
        <th></th>
      </tr></thead>
      <tbody>
        <tr v-for="rule in rules" :key="rule.id" :class="{ 'ud-rule--admin': rule.scope === 'admin' }">
          <td class="ud-rule-path" :title="rule.path">📁 {{ shortPath(rule.path) }}</td>
          <td>{{ rule.label }}</td>
          <td>{{ rule.recursive ? '✅' : '—' }}</td>
          <td><span :class="['ud-badge','ud-badge--'+rule.scope]">{{ rule.scope }}</span></td>
          <td>{{ formatDate(rule.created_at) }}</td>
          <td><NcButton type="error" size="small" @click="deleteRule(rule.id)">{{ t('urbanduplicati','Remove') }}</NcButton></td>
        </tr>
      </tbody>
    </table>
    <NcModal v-if="showAdd" @closing="cancelAdd">
      <div class="ud-modal-content">
        <h3>{{ t('urbanduplicati','Add protection rule') }}</h3>
        <div class="ud-form-row">
          <label>{{ t('urbanduplicati','Protected folder') }}</label>
          <div class="ud-folder-picker-row">
            <input :value="newRule.path || t('urbanduplicati','No folder selected')" type="text" readonly class="ud-folder-input" />
            <NcButton type="secondary" @click="pickFolder">{{ t('urbanduplicati','Browse…') }}</NcButton>
          </div>
        </div>
        <div class="ud-form-row">
          <label>{{ t('urbanduplicati','Label') }}</label>
          <input v-model="newRule.label" type="text" :placeholder="t('urbanduplicati','e.g. My originals')" />
        </div>
        <div class="ud-form-row">
          <label><input v-model="newRule.recursive" type="checkbox" /> {{ t('urbanduplicati','Protect all subfolders too') }}</label>
        </div>
        <div class="ud-form-actions">
          <NcButton @click="cancelAdd">{{ t('urbanduplicati','Cancel') }}</NcButton>
          <NcButton type="primary" :disabled="!newRule.path || saving" @click="addRule">
            {{ saving ? t('urbanduplicati','Adding…') : t('urbanduplicati','Add rule') }}
          </NcButton>
        </div>
      </div>
    </NcModal>
  </div>
</template>
<script>
import { NcButton, NcModal, NcLoadingIcon } from '@nextcloud/vue'
import { mapGetters, mapActions } from 'vuex'
export default {
  name: 'ProtectionView',
  components: { NcButton, NcModal, NcLoadingIcon },
  data() { return { showAdd: false, saving: false, newRule: { path: '', label: '', recursive: true, scope: 'user' } } },
  computed: { ...mapGetters(['rules', 'protectionLoading']) },
  created() { this.getRules() },
  methods: {
    ...mapActions(['getRules', 'createRule', 'deleteRule']),
    pickFolder() {
      OC.dialogs.filepicker(
        this.t('urbanduplicati', 'Select folder to protect'),
        (path) => {
          if (path) {
            this.newRule.path = path
            if (!this.newRule.label) {
              const parts = path.split('/').filter(Boolean)
              this.newRule.label = parts[parts.length - 1] || path
            }
          }
        },
        false, 'httpd/unix-directory', true, OC.dialogs.FILEPICKER_TYPE_CHOOSE
      )
    },
    async addRule() {
      this.saving = true
      try { await this.createRule({ ...this.newRule }); this.cancelAdd() }
      finally { this.saving = false }
    },
    cancelAdd() { this.showAdd = false; this.newRule = { path: '', label: '', recursive: true, scope: 'user' } },
    shortPath(p) { if(!p)return''; const parts=p.split('/').filter(Boolean); return parts.length>2?'…/'+parts.slice(-2).join('/'):p },
    formatDate(ts) { return ts ? new Date(ts*1000).toLocaleDateString() : '—' },
  },
}
</script>

<style scoped>
.ud-page { max-width: 1000px; margin: 0 auto; padding: 20px 40px; box-sizing: border-box; }
.ud-page-header { display: flex; align-items: center; gap: 16px; margin-bottom: 20px; }
.ud-page-header h2 { margin: 0; font-size: 1.3em; font-weight: 600; flex: 1; }
</style>
