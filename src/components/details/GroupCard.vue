<template>
  <div :class="['ud-group-card', { 'ud-group-card--selected': selected, 'ud-group-card--all-protected': group.all_protected }]">
    <div class="ud-group-card__header" @click="$emit('toggle')">
      <input type="checkbox" :checked="selected" @click.stop @change="$emit('toggle')" class="ud-group-check" />
      <span class="ud-group-label">{{ t('dupli', 'Group') }} {{ group.group_id }}</span>
      <span class="ud-group-meta">{{ group.files.length }} {{ t('dupli', 'files') }} · {{ groupSize }}</span>
      <span v-if="group.all_protected" class="ud-all-protected-tag">🛡 {{ t('dupli', 'All protected') }}</span>
      <button class="ud-toggle-btn" @click.stop="expanded = !expanded">{{ expanded ? '▲' : '▼' }}</button>
    </div>
    <div v-if="group.has_protected_duplicates" class="ud-protected-dupe-notice">
      <label class="ud-protected-dupe-label">
        <input type="checkbox" :checked="deleteProtectedDuplicates"
               @change="$emit('protected-dupe-change', { groupId: group.group_id, value: $event.target.checked })" />
        <span>⚠️ {{ t('dupli', 'This group has') }} {{ protectedCount }} {{ t('dupli', 'protected duplicates.') }}
          {{ t('dupli', 'Delete all but one protected copy?') }}</span>
      </label>
    </div>
    <div v-if="expanded" class="ud-group-files">
      <div v-for="file in group.files" :key="file.fileid"
           :class="['ud-file-card', { 'ud-file-card--protected': file.protected, 'ud-file-card--filter-match': file.filter_match }]">
        <div class="ud-file-card__thumb">
          <img :src="thumb(file.fileid)" @error="e => e.target.style.display='none'"
               @click="openViewer(file)" style="cursor:pointer" class="ud-thumb-img" />
        </div>
        <div class="ud-file-card__info">
          <div class="ud-file-card__name" :title="file.filename">{{ file.filename }}</div>
          <div class="ud-file-card__path" :title="file.filepath">{{ shortPath(file.filepath) }}</div>
          <div class="ud-file-card__size">{{ formatBytes(file.filesize) }}</div>
          <div v-if="file.protected" class="ud-protected-badge">🛡 {{ t('dupli', 'Protected') }}</div>
          <div v-if="file.filter_match" class="ud-filter-match-badge">🗑 {{ t('dupli', 'Will be deleted') }}</div>
        </div>
        <div class="ud-file-card__actions">
          <button :class="['ud-delete-btn', { 'ud-delete-btn--disabled': file.protected || deleting === file.fileid }]"
                  :disabled="file.protected || deleting === file.fileid"
                  :title="file.protected ? t('dupli', 'Protected — cannot delete') : t('dupli', 'Delete this file')"
                  @click="handleDelete(file)">
            {{ deleting === file.fileid ? '…' : (file.protected ? '🛡 ' + t('dupli', 'Protected') : t('dupli', 'Delete')) }}
          </button>
        </div>
      </div>
    </div>
    <div v-if="previewFile" class="ud-lightbox" @click.self="closePreview">
      <div class="ud-lightbox__box">
        <button class="ud-lightbox__close" @click="closePreview">✕</button>
        <img :src="previewSrc" class="ud-lightbox__img" @error="e => e.target.src = thumb(previewFile.fileid)" />
        <div class="ud-lightbox__name">{{ previewFile.filename }}</div>
      </div>
    </div>
  </div>
</template>
<script>
import { generateUrl } from '@nextcloud/router'
export default {
  name: 'GroupCard',
  props: {
    group:                     { type: Object,  required: true },
    selected:                  { type: Boolean, default: false },
    taskId:                    { type: Number,  required: true },
    deleteProtectedDuplicates: { type: Boolean, default: false },
  },
  data() { return { expanded: false, deleting: null, previewFile: null } },
  computed: {
    groupSize() { return this.formatBytes(this.group.files.reduce((s, f) => s + Number(f.filesize || 0), 0)) },
    protectedCount() { return (this.group.files || []).filter(f => f.protected).length },
    previewSrc() {
      if (!this.previewFile) return ''
      return generateUrl('/core/preview?fileId=' + this.previewFile.fileid + '&x=1920&y=1920&a=1&forceIcon=0')
    },
  },
  methods: {
    handleDelete(file) { if (file.protected || this.deleting === file.fileid) return; this.doDeleteFile(file) },
    async doDeleteFile(file) {
      this.deleting = file.fileid
      await this.$store.dispatch('deleteFile', { taskId: this.taskId, groupId: this.group.group_id, fileId: file.id })
      this.deleting = null
      this.$emit('file-deleted')
    },
    openViewer(file) { this.previewFile = file },
    closePreview() { this.previewFile = null },
    thumb(id) { return generateUrl('/core/preview?fileId=' + id + '&x=160&y=160&a=1&forceIcon=0') },
    shortPath(p) { if (!p) return ''; const parts = p.split('/').filter(Boolean); return parts.length > 3 ? '…/' + parts.slice(-2).join('/') : p },
    formatBytes(b) { if (!b) return '0 B'; b = Number(b); if (b >= 1073741824) return (b/1073741824).toFixed(1)+' GB'; if (b >= 1048576) return (b/1048576).toFixed(1)+' MB'; if (b >= 1024) return Math.round(b/1024)+' KB'; return b+' B' },
  },
}
</script>
<style scoped>
.ud-group-card { border: 1px solid var(--color-border); border-radius: 12px; margin-bottom: 12px; background: var(--color-main-background); overflow: visible; }
.ud-group-card--selected { border-color: var(--color-primary); box-shadow: 0 0 0 2px var(--color-primary-element-light); }
.ud-group-card--all-protected { border-color: #f8a800; opacity: 0.85; }
.ud-group-card__header { display: flex; align-items: center; gap: 12px; padding: 12px 16px; cursor: pointer; background: var(--color-background-hover); }
.ud-group-check { flex-shrink: 0; }
.ud-group-label { font-weight: 600; flex-shrink: 0; }
.ud-group-meta { color: var(--color-text-maxcontrast); font-size: 0.85em; flex: 1; }
.ud-all-protected-tag { font-size: 0.75em; color: #f8a800; font-weight: 600; background: rgba(248,168,0,0.12); padding: 2px 8px; border-radius: 10px; }
.ud-toggle-btn { background: none; border: none; cursor: pointer; font-size: 0.85em; color: var(--color-text-maxcontrast); }
.ud-protected-dupe-notice { padding: 10px 16px; background: rgba(248,168,0,0.08); border-bottom: 1px solid rgba(248,168,0,0.3); }
.ud-protected-dupe-label { display: flex; align-items: flex-start; gap: 8px; cursor: pointer; font-size: 0.85em; }
.ud-protected-dupe-label input { flex-shrink: 0; margin-top: 2px; }
.ud-group-files { display: flex; flex-wrap: wrap; gap: 12px; padding: 16px; }
.ud-file-card { display: flex; flex-direction: column; width: 180px; border: 1px solid var(--color-border); border-radius: 8px; overflow: hidden; background: var(--color-background-dark); }
.ud-file-card--protected { border-color: #f8a800; background: rgba(248,168,0,0.06); }
.ud-file-card__thumb { width: 100%; height: 140px; background: var(--color-background-darker); display: flex; align-items: center; justify-content: center; overflow: hidden; }
.ud-thumb-img { width: 100%; height: 100%; object-fit: cover; }
.ud-file-card__info { padding: 8px; flex: 1; }
.ud-file-card__name { font-weight: 600; font-size: 0.8em; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; margin-bottom: 2px; }
.ud-file-card__path { font-size: 0.72em; color: var(--color-text-maxcontrast); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; margin-bottom: 2px; }
.ud-file-card__size { font-size: 0.75em; color: var(--color-text-maxcontrast); }
.ud-protected-badge { font-size: 0.72em; color: #f8a800; font-weight: 600; margin-top: 4px; }
.ud-file-card__actions { padding: 6px 8px; border-top: 1px solid var(--color-border); display: flex; justify-content: center; }
.ud-delete-btn { width: 100%; padding: 5px 10px; border-radius: 6px; border: none; font-size: 0.8em; font-weight: 600; cursor: pointer; background: var(--color-error); color: #fff; transition: opacity 0.2s; }
.ud-delete-btn:hover:not(.ud-delete-btn--disabled) { opacity: 0.85; }
.ud-file-card--filter-match { border-color: var(--color-error) !important; background: rgba(233,50,45,0.06) !important; }
.ud-filter-match-badge { font-size: 0.72em; color: var(--color-error); font-weight: 600; margin-top: 4px; }
.ud-delete-btn--disabled { background: var(--color-background-darker) !important; color: var(--color-text-maxcontrast) !important; cursor: not-allowed !important; opacity: 0.6; pointer-events: none; }
.ud-lightbox { position: fixed; top: 0; left: 0; width: 100vw; height: 100vh; background: rgba(0,0,0,0.88); z-index: 10000; display: flex; align-items: center; justify-content: center; }
.ud-lightbox__box { position: relative; display: flex; flex-direction: column; align-items: center; gap: 10px; max-width: 92vw; max-height: 92vh; }
.ud-lightbox__img { max-width: 92vw; max-height: 82vh; object-fit: contain; border-radius: 6px; box-shadow: 0 8px 40px rgba(0,0,0,0.6); }
.ud-lightbox__close { position: absolute; top: -40px; right: -4px; background: none; border: none; color: #fff; font-size: 28px; cursor: pointer; line-height: 1; opacity: 0.85; }
.ud-lightbox__close:hover { opacity: 1; }
.ud-lightbox__name { color: rgba(255,255,255,0.8); font-size: 0.9em; text-align: center; max-width: 90vw; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
</style>
