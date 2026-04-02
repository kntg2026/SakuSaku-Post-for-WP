<template>
  <div>
    <h1 class="text-xl font-bold mb-6">ユーザー管理</h1>

    <div v-if="loading" class="text-gray-400 text-sm">読み込み中...</div>

    <div v-else-if="users.length === 0" class="bg-white rounded-lg shadow p-8 text-center text-gray-500">
      ユーザーがいません。
    </div>

    <div v-else class="bg-white rounded-lg shadow overflow-hidden">
      <table class="w-full text-sm">
        <thead class="bg-gray-50 text-gray-600">
          <tr>
            <th class="text-left px-4 py-3">ユーザー</th>
            <th class="text-left px-4 py-3">メール</th>
            <th class="text-left px-4 py-3">レベル</th>
            <th class="text-left px-4 py-3">ロール</th>
            <th class="text-left px-4 py-3">投稿数</th>
            <th class="text-left px-4 py-3">最終ログイン</th>
          </tr>
        </thead>
        <tbody class="divide-y">
          <tr v-for="user in users" :key="user.id" class="hover:bg-gray-50">
            <td class="px-4 py-3">
              <div class="flex items-center gap-2">
                <img v-if="user.avatar_url" :src="user.avatar_url" class="w-6 h-6 rounded-full" />
                <span class="font-medium">{{ user.name }}</span>
              </div>
            </td>
            <td class="px-4 py-3 text-gray-500">{{ user.email }}</td>
            <td class="px-4 py-3">
              <select :value="user.level" @change="handleLevelChange(user, $event)"
                class="border border-gray-300 rounded px-2 py-1 text-sm">
                <option :value="1">L1</option>
                <option :value="2">L2</option>
                <option :value="3">L3</option>
              </select>
            </td>
            <td class="px-4 py-3">
              <button @click="handleRoleToggle(user)"
                :class="user.role === 'admin' ? 'bg-indigo-100 text-indigo-700' : 'bg-gray-100 text-gray-700'"
                class="px-2 py-0.5 rounded-full text-xs font-medium">
                {{ user.role === 'admin' ? '管理者' : '投稿者' }}
              </button>
            </td>
            <td class="px-4 py-3 text-gray-500">{{ user.posts_count }}</td>
            <td class="px-4 py-3 text-gray-500">{{ user.last_login_at ? formatDate(user.last_login_at) : '—' }}</td>
          </tr>
        </tbody>
      </table>
    </div>

    <div v-if="error" class="mt-4 bg-red-50 text-red-600 text-sm p-3 rounded-lg">{{ error }}</div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useApi } from '../../composables/useApi'

const api = useApi()
const loading = ref(true)
const users = ref([])
const error = ref(null)

onMounted(async () => {
  await fetchUsers()
})

async function fetchUsers() {
  loading.value = true
  try {
    const { data } = await api.get('/admin/users')
    users.value = data.data
  } finally {
    loading.value = false
  }
}

async function handleLevelChange(user, event) {
  const newLevel = parseInt(event.target.value)
  error.value = null
  try {
    await api.put(`/admin/users/${user.id}/level`, { level: newLevel })
    user.level = newLevel
  } catch (e) {
    error.value = e.response?.data?.error || 'レベルの更新に失敗しました'
    event.target.value = user.level
  }
}

async function handleRoleToggle(user) {
  const newRole = user.role === 'admin' ? 'poster' : 'admin'
  const label = newRole === 'admin' ? '管理者' : '投稿者'
  if (!confirm(`${user.name} のロールを「${label}」に変更しますか？`)) return

  error.value = null
  try {
    await api.put(`/admin/users/${user.id}/role`, { role: newRole })
    user.role = newRole
  } catch (e) {
    error.value = e.response?.data?.error || 'ロールの更新に失敗しました'
  }
}

function formatDate(iso) {
  return new Date(iso).toLocaleDateString('ja-JP', { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' })
}
</script>
