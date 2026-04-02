<template>
  <div class="min-h-screen bg-gray-100">
    <nav class="bg-indigo-700 text-white shadow">
      <div class="max-w-7xl mx-auto px-4 py-3 flex items-center justify-between">
        <div class="flex items-center gap-6">
          <span class="font-bold text-lg">SakuSaku Admin</span>
          <router-link to="/admin" class="text-sm text-indigo-200 hover:text-white" exact-active-class="text-white font-medium">ダッシュボード</router-link>
          <router-link to="/admin/posts" class="text-sm text-indigo-200 hover:text-white" active-class="text-white font-medium">投稿管理</router-link>
          <router-link to="/admin/users" class="text-sm text-indigo-200 hover:text-white" active-class="text-white font-medium">ユーザー</router-link>
          <router-link to="/admin/categories" class="text-sm text-indigo-200 hover:text-white" active-class="text-white font-medium">カテゴリ</router-link>
          <router-link to="/admin/settings" class="text-sm text-indigo-200 hover:text-white" active-class="text-white font-medium">設定</router-link>
        </div>
        <div class="flex items-center gap-3 text-sm">
          <router-link to="/" class="text-indigo-200 hover:text-white">投稿者画面</router-link>
          <span class="text-indigo-300">{{ auth.user?.name }}</span>
        </div>
      </div>
    </nav>
    <main class="max-w-7xl mx-auto px-4 py-6">
      <router-view />
    </main>
  </div>
</template>

<script setup>
import { useAuthStore } from '../stores/auth'
const auth = useAuthStore()
</script>
