<template>
  <div class="ud-new-task-form">
    <div class="ud-form-row">
      <label>{{ t('urbanduplicati','Scan name') }}</label>
      <input v-model="taskName" type="text" :placeholder="t('urbanduplicati','e.g. Full media scan')" />
    </div>
    <div class="ud-form-row">
      <label>{{ t('urbanduplicati','Target folders') }}</label>
      <NcButton type="secondary" @click="openDirectoriesExplorer">
        + {{ t('urbanduplicati','Browse and add folder') }}
      </NcButton>
      <div v-if="Object.keys(targetDirectoriesPaths).length === 0" class="ud-folder-hint">
        {{ t('urbanduplicati','No folders selected yet.') }}
      </div>
      <div v-for="(path, id) in targetDirectoriesPaths" :key="id" class="ud-folder-tag">
        📁 <span class="ud-folder-tag__path">{{ path }}</span>
        <button class="ud-folder-tag__remove" @click="removeFolder(id)">✕</button>
      </div>
    </div>
    <div class="ud-form-row">
      <label>{{ t('urbanduplicati','Media type') }}</label>
      <select v-model.number="targetMimeType">
        <option :value="0">{{ t('urbanduplicati','Images only') }}</option>
        <option :value="1">{{ t('urbanduplicati','Videos only') }}</option>
        <option :value="2">{{ t('urbanduplicati','Images + Videos') }}</option>
      </select>
    </div>
    <div class="ud-form-row">
      <label>{{ t('urbanduplicati','Algorithm') }}</label>
      <select v-model="hashingAlgorithm">
        <option value="dhash">dHash — {{ t('urbanduplicati','fast, recommended') }}</option>
        <option value="phash">pHash — {{ t('urbanduplicati','perceptual similarity') }}</option>
        <option value="whash">wHash — {{ t('urbanduplicati','wavelet') }}</option>
        <option value="average">{{ t('urbanduplicati','Average hash') }}</option>
      </select>
    </div>
    <div class="ud-form-row">
      <label>{{ t('urbanduplicati','Similarity') }}: {{ similarityThreshold }}%</label>
      <input v-model.number="similarityThreshold" type="range" min="50" max="100" step="1" />
    </div>
    <div class="ud-form-row">
      <label>
        <input v-model="finishNotification" type="checkbox" />
        {{ t('urbanduplicati','Notify me when scan finishes') }}
      </label>
    </div>
    <div class="ud-form-actions">
      <NcButton @click="$emit('cancel')">{{ t('urbanduplicati','Cancel') }}</NcButton>
      <NcButton type="primary"
        :disabled="Object.keys(targetDirectoriesPaths).length === 0 || running"
        @click="runCollectorTask">
        {{ running ? t('urbanduplicati','Starting…') : t('urbanduplicati','Start Scan') }}
      </NcButton>
    </div>
  </div>
</template>
<script>
import { NcButton } from '@nextcloud/vue'
import { mapGetters, mapActions } from 'vuex'
import { requestFileInfo, getFileId } from '../../utils/files.js'

export default {
  name: 'TasksNew',
  components: { NcButton },
  data() {
    return {
      taskName: '',
      targetDirectoriesPaths: {},
      targetDirectoriesIds: [],
      targetMimeType: 2,
      hashingAlgorithm: 'dhash',
      similarityThreshold: 90,
      finishNotification: true,
      running: false,
    }
  },
  computed: { ...mapGetters(['settings', 'settingByName']) },
  methods: {
    ...mapActions(['runTask', 'getTasks']),
    getDirectoriesPicker(title) {
      return OC.dialogs.filepicker(
        title,
        (dir) => this.handlePickedDir(dir),
        false,
        'httpd/unix-directory',
        true,
        OC.dialogs.FILEPICKER_TYPE_CHOOSE
      )
    },
    openDirectoriesExplorer() {
      this.getDirectoriesPicker(this.t('urbanduplicati', 'Choose target directory'))
    },
    handlePickedDir(dir) {
      if (!dir) return
      const path = dir.startsWith('/') ? dir : '/' + dir
      requestFileInfo(path).then(res => {
        const fileid = getFileId(res.data)
        if (fileid !== -1 && !(fileid in this.targetDirectoriesPaths)) {
          this.targetDirectoriesIds.push(fileid)
          this.$set(this.targetDirectoriesPaths, String(fileid), path)
        }
      }).catch(() => {
        // fallback: use path as key
        if (!(path in this.targetDirectoriesPaths)) {
          this.$set(this.targetDirectoriesPaths, path, path)
        }
      })
    },
    removeFolder(id) {
      this.targetDirectoriesIds = this.targetDirectoriesIds.filter(i => String(i) !== String(id))
      this.$delete(this.targetDirectoriesPaths, id)
    },
    async runCollectorTask() {
      this.running = true
      try {
        const ids = this.targetDirectoriesIds.length
          ? this.targetDirectoriesIds
          : Object.keys(this.targetDirectoriesPaths)
        const res = await this.runTask({
          targetDirectoryIds: JSON.stringify(ids),
          excludeList: JSON.stringify({ admin: { mask: [], fileid: [] }, user: { mask: [], fileid: [] } }),
          collectorSettings: JSON.stringify({
            hashing_algorithm: this.hashingAlgorithm,
            similarity_threshold: this.similarityThreshold,
            hash_size: 16,
            target_mtype: this.targetMimeType,
            finish_notification: this.finishNotification,
            exif_transpose: true,
          }),
          name: this.taskName || Object.values(this.targetDirectoriesPaths).map(p => p.split('/').filter(Boolean).pop()).join(', '),
        })
        if (res && res.data && res.data.success) {
          this.$emit('created')
          await this.getTasks()
        }
      } finally { this.running = false }
    },
  },
}
</script>
