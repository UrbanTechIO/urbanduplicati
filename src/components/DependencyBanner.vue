<template>
  <div v-if="status && (!status.ok || status.missingOptional.length)"
    :class="['ud-dep-banner', status.ok ? 'ud-dep-banner--warn' : 'ud-dep-banner--error']">
    <strong v-if="!status.ok">
      {{ t('urbanduplicati', 'Dupli cannot scan: required server components are missing') }}
    </strong>
    <strong v-else>{{ t('urbanduplicati', 'Optional components are missing') }}</strong>
    <ul>
      <li v-for="item in missing" :key="item.id">
        <a :href="item.url" target="_blank" rel="noopener noreferrer">{{ item.name }}</a>
        <span class="ud-dep-tag">{{ item.required ? t('urbanduplicati', 'required') : t('urbanduplicati', 'optional') }}</span>
        <span v-if="item.detail"> — {{ item.detail }}</span>
      </li>
    </ul>
    <template v-if="!status.ok">
      <p>{{ t('urbanduplicati', 'Run this on the server (inside the Nextcloud container if you use Docker):') }}</p>
      <code>{{ status.installCommand }}</code>
      <p>
        <a :href="status.docsUrl" target="_blank" rel="noopener noreferrer">{{ t('urbanduplicati', 'Installation instructions') }}</a>
        · <a href="#" @click.prevent="load">{{ t('urbanduplicati', 'Check again') }}</a>
      </p>
    </template>
  </div>
</template>
<script>
import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'
export default {
  name: 'DependencyBanner',
  data() { return { status: null } },
  computed: {
    missing() { return this.status.items.filter(i => !i.installed) },
  },
  mounted() { this.load() },
  methods: {
    async load() {
      try {
        const res = await axios.get(generateUrl('/apps/urbanduplicati/api/v1/dependencies'))
        this.status = res.data
      } catch (e) { this.status = null }
    },
  },
}
</script>
<style scoped>
.ud-dep-banner { margin: 12px 40px 0; padding: 12px 16px; border-radius: var(--border-radius-large, 8px); border-left: 4px solid; background: var(--color-background-hover); }
.ud-dep-banner--error { border-color: var(--color-error); }
.ud-dep-banner--warn { border-color: var(--color-warning); }
.ud-dep-banner ul { margin: 6px 0 6px 20px; list-style: disc; }
.ud-dep-banner code { display: block; padding: 8px; background: var(--color-background-dark); border-radius: 6px; word-break: break-all; user-select: all; }
.ud-dep-tag { font-size: 0.8em; color: var(--color-text-maxcontrast); margin-left: 6px; }
</style>
