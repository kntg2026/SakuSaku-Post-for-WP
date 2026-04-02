import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '../stores/auth'

const routes = [
  {
    path: '/login',
    component: () => import('../pages/auth/Login.vue'),
    meta: { guest: true },
  },
  {
    path: '/auth/callback',
    component: () => import('../pages/auth/Callback.vue'),
    meta: { guest: true },
  },
  {
    path: '/',
    component: () => import('../layouts/PosterLayout.vue'),
    meta: { auth: true },
    children: [
      { path: '', redirect: '/submit' },
      { path: 'submit', component: () => import('../pages/poster/Submit.vue') },
      { path: 'posts', component: () => import('../pages/poster/MyPosts.vue') },
      { path: 'posts/:id', component: () => import('../pages/poster/PostDetail.vue'), props: true },
    ],
  },
  {
    path: '/admin',
    component: () => import('../layouts/AdminLayout.vue'),
    meta: { auth: true, admin: true },
    children: [
      { path: '', component: () => import('../pages/admin/Dashboard.vue') },
      { path: 'posts', component: () => import('../pages/admin/Posts.vue') },
      { path: 'posts/:id', component: () => import('../pages/admin/PostReview.vue'), props: true },
      { path: 'users', component: () => import('../pages/admin/Users.vue') },
      { path: 'categories', component: () => import('../pages/admin/Categories.vue') },
      { path: 'notifications', component: () => import('../pages/admin/Notifications.vue') },
      { path: 'settings', component: () => import('../pages/admin/Settings.vue') },
      { path: 'billing', component: () => import('../pages/admin/Billing.vue') },
    ],
  },
]

const router = createRouter({
  history: createWebHistory(),
  routes,
})

router.beforeEach((to) => {
  const auth = useAuthStore()

  if (to.meta.auth && !auth.isAuthenticated) {
    return '/login'
  }
  if (to.meta.admin && !auth.isAdmin) {
    return '/'
  }
  if (to.meta.guest && auth.isAuthenticated) {
    return '/'
  }
})

export default router
