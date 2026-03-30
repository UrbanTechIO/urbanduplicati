<template>
  <div class="ud-dryrun-panel">
    <div class="ud-dryrun-panel__header">
      <strong>🔍 {{ t('urbanduplicati','Deletion preview') }}</strong>
      <span class="ud-dryrun-summary">
        {{ totalDelete }} {{ t('urbanduplicati','file(s) to delete') }} ({{ formatBytes(totalSize) }})
        <span v-if="totalSkipped > 0" class="ud-dryrun-protected">· {{ totalSkipped }} {{ t('urbanduplicati','protected — will be skipped') }}</span>
      </span>
      <button class="ud-dryrun-close" @click="$emit('close')">✕</button>
    </div>
    <div class="ud-dryrun-groups">
      <div v-for="group in result.preview" :key="group.group_id" class="ud-dryrun-group">
        <div class="ud-dryrun-group__label">{{ t('urbanduplicati','Group') }} {{ group.group_id }}</div>
        <div v-for="f in group.delete" :key="f.fileid" class="ud-dryrun-row ud-dryrun-row--delete">
          🗑 {{ f.path }} <span class="ud-dryrun-size">{{ formatBytes(f.size) }}</span>
        </div>
        <div v-for="f in group.skip_protected" :key="f.fileid" class="ud-dryrun-row ud-dryrun-row--skip">
          🛡 {{ f.path }} <span class="ud-dryrun-reason">{{ f.reason }}</span>
        </div>
      </div>
    </div>
    <div class="ud-dryrun-panel__actions">
      <NcButton @click="$emit('close')">{{ t('urbanduplicati','Cancel') }}</NcButton>
      <NcButton type="error" @click="$emit('confirm')">{{ t('urbanduplicati','Confirm') }} — {{ t('urbanduplicati','delete') }} {{ totalDelete }} {{ t('urbanduplicati','file(s)') }}</NcButton>
    </div>
  </div>
</template>
<script>
import { NcButton } from '@nextcloud/vue'
export default {
  name: 'DryRunPanel',
  components: { NcButton },
  props: { result: Object },
  computed: {
    totalDelete()  { return this.result.preview?.reduce((s,g)=>s+g.delete.length,0)||0 },
    totalSkipped() { return this.result.preview?.reduce((s,g)=>s+g.skip_protected.length,0)||0 },
    totalSize()    { return this.result.preview?.reduce((s,g)=>s+g.delete.reduce((ss,f)=>ss+f.size,0),0)||0 },
  },
  methods: {
    formatBytes(b) { if(!b)return'0 B'; if(b>=1073741824)return(b/1073741824).toFixed(1)+' GB'; if(b>=1048576)return(b/1048576).toFixed(1)+' MB'; return Math.round(b/1024)+' KB' },
  },
}
</script>
