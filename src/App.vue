<template>
  <NcContent app-name="urbanduplicati">
    <NcAppNavigation>
      <template #list>
        <NcAppNavigationItem :name="t('urbanduplicati','Scans')"
          :to="{ name: 'tasks' }" exact>
          <template #icon><span>🔍</span></template>
        </NcAppNavigationItem>
        <NcAppNavigationItem :name="t('urbanduplicati','Protection')"
          :to="{ name: 'protection' }">
          <template #icon><span>🛡</span></template>
        </NcAppNavigationItem>
        <NcAppNavigationItem :name="t('urbanduplicati','Audit Log')"
          :to="{ name: 'audit' }">
          <template #icon><span>📋</span></template>
        </NcAppNavigationItem>
        <NcAppNavigationItem :name="t('urbanduplicati','Settings')"
          :to="{ name: 'settings' }">
          <template #icon><span>⚙️</span></template>
        </NcAppNavigationItem>
      </template>
    </NcAppNavigation>
    <NcAppContent>
      <router-view />
    </NcAppContent>
  </NcContent>
</template>
<script>
import { NcContent, NcAppNavigation, NcAppNavigationItem, NcAppContent } from '@nextcloud/vue'
export default {
  name: 'UrbanDuplicati',
  components: { NcContent, NcAppNavigation, NcAppNavigationItem, NcAppContent },
}
</script>
