// Fixed router - eager loading to avoid chunk caching issues
import { generateUrl } from '@nextcloud/router'
import VueRouter from 'vue-router'
import Vue from 'vue'

import TaskList       from '../views/TaskList.vue'
import TaskDetail     from '../views/TaskDetail.vue'
import ProtectionView from '../views/ProtectionView.vue'
import AuditView      from '../views/AuditView.vue'
import SettingsView   from '../views/SettingsView.vue'

Vue.use(VueRouter)

export default new VueRouter({
  mode: 'history',
  base: generateUrl('/apps/urbanduplicati', ''),
  linkActiveClass: 'active',
  routes: [
    { path: '/',           name: 'tasks',       component: TaskList },
    { path: '/tasks/:id',  name: 'task-detail', component: TaskDetail, props: true },
    { path: '/protection', name: 'protection',  component: ProtectionView },
    { path: '/audit',      name: 'audit',       component: AuditView },
    { path: '/settings',   name: 'settings',    component: SettingsView },
  ],
})
