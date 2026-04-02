<template>
  <div class="min-h-screen bg-gray-50">
    <nav class="bg-white shadow-sm border-b">
      <div class="max-w-5xl mx-auto px-4 py-3 flex items-center justify-between">
        <div class="flex items-center gap-6">
          <span class="font-bold text-lg text-indigo-600">SakuSaku Post</span>
          <router-link to="/submit" class="text-sm hover:text-indigo-600" active-class="text-indigo-600 font-medium">送信</router-link>
          <router-link to="/posts" class="text-sm hover:text-indigo-600" active-class="text-indigo-600 font-medium">投稿一覧</router-link>
          <router-link v-if="auth.isAdmin" to="/admin" class="text-sm hover:text-indigo-600">管理</router-link>
        </div>
        <div class="flex items-center gap-3 text-sm">
          <span class="text-gray-600">{{ auth.user?.name }}</span>
          <button @click="handleLogout" class="text-red-500 hover:text-red-700">ログアウト</button>
        </div>
      </div>
    </nav>
    <main class="max-w-5xl mx-auto px-4 py-6">
      <router-view />
    </main>
  </div>
</template>

<script setup>
import { useAuthStore } from '../stores/auth'
import { useApi } from '../composables/useApi'
import { useRouter } from 'vue-router'

const auth = useAuthStore()
const api = useApi()
const router = useRouter()

async function handleLogout() {
  try { await api.post('/auth/logout') } catch {}
  auth.logout()
  router.push('/login')
}
</script>
