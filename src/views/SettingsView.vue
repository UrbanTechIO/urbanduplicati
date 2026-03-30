<template>
  <div class="ud-page">
    <div class="ud-page-header"><h2>⚙️ {{ t('urbanduplicati','Settings') }}</h2></div>
    <div v-if="settingsLoading" class="ud-loading"><NcLoadingIcon /></div>
    <div v-else class="ud-settings-form">
      <section class="ud-settings-section">
        <h3>{{ t('urbanduplicati','Detection defaults') }}</h3>
        <div class="ud-form-row">
          <label>{{ t('urbanduplicati','Algorithm') }}</label>
          <select v-model="local.hashing_algorithm">
            <option value="dhash">dHash ({{ t('urbanduplicati','fast, recommended') }})</option>
            <option value="phash">pHash ({{ t('urbanduplicati','perceptual') }})</option>
            <option value="whash">wHash</option>
            <option value="average">{{ t('urbanduplicati','Average') }}</option>
          </select>
        </div>
        <div class="ud-form-row">
          <label>{{ t('urbanduplicati','Similarity threshold') }}: {{ local.similarity_threshold }}%</label>
          <input v-model.number="local.similarity_threshold" type="range" min="50" max="100" />
        </div>
        <div class="ud-form-row">
          <label>{{ t('urbanduplicati','Hash size') }}</label>
          <select v-model.number="local.hash_size">
            <option :value="8">8</option><option :value="16">16 ({{ t('urbanduplicati','recommended') }})</option>
            <option :value="32">32</option><option :value="64">64</option>
          </select>
        </div>
      </section>
      <section class="ud-settings-section">
        <h3>{{ t('urbanduplicati','Scheduled scans') }}</h3>
        <div class="ud-form-row">
          <label><input v-model="local.auto_scan_enabled" type="checkbox" /> {{ t('urbanduplicati','Enable automatic scans') }}</label>
        </div>
        <div class="ud-form-row" v-if="local.auto_scan_enabled">
          <label>{{ t('urbanduplicati','Interval (seconds)') }}</label>
          <input v-model.number="local.auto_scan_interval" type="number" min="3600" step="3600" />
          <small>86400 = {{ t('urbanduplicati','daily') }}</small>
        </div>
      </section>
      <section class="ud-settings-section">
        <h3>{{ t('urbanduplicati','Audit log') }}</h3>
        <div class="ud-form-row">
          <label>{{ t('urbanduplicati','Retain for (days, 0 = forever)') }}</label>
          <input v-model.number="local.audit_retention_days" type="number" min="0" />
        </div>
      </section>
      <div class="ud-settings-actions">
        <NcButton type="primary" @click="save">{{ t('urbanduplicati','Save settings') }}</NcButton>
      </div>
      <section class="ud-settings-section">
        <h3>{{ t('urbanduplicati','System info') }}</h3>
        <NcButton type="secondary" @click="getSystemInfo">{{ t('urbanduplicati','Refresh') }}</NcButton>
        <dl v-if="Object.keys(systemInfo).length" class="ud-sysinfo">
          <dt>PHP</dt><dd>{{ systemInfo.php_version }}</dd>
          <dt>GD</dt><dd>{{ systemInfo.gd_enabled ? '✅' : '❌' }}</dd>
          <dt>Imagick</dt><dd>{{ systemInfo.imagick_enabled ? '✅' : '❌' }}</dd>
          <dt>FFmpeg</dt><dd>{{ systemInfo.ffmpeg_available ? '✅ '+systemInfo.ffmpeg_path : '❌' }}</dd>
        </dl>
      </section>
    </div>
  </div>
</template>
<script>
import { NcButton, NcLoadingIcon } from '@nextcloud/vue'
import { mapGetters, mapActions } from 'vuex'
export default {
  name: 'SettingsView',
  components: { NcButton, NcLoadingIcon },
  data() { return { local: {} } },
  computed: { ...mapGetters(['settings','settingsLoading','systemInfo']) },
  async created() { await this.getSettings(); this.local = { ...this.settings } },
  watch: { settings: { deep: true, handler(v) { this.local = { ...v } } } },
  methods: {
    ...mapActions(['getSettings','saveSettings','getSystemInfo']),
    save() { this.saveSettings({ ...this.local }) },
  },
}
</script>

<style scoped>
.ud-page { max-width: 1000px; margin: 0 auto; padding: 20px 40px; box-sizing: border-box; }
.ud-page-header { display: flex; align-items: center; gap: 16px; margin-bottom: 20px; }
.ud-page-header h2 { margin: 0; font-size: 1.3em; font-weight: 600; flex: 1; }
</style>
