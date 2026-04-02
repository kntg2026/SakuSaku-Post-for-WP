<template>
  <div>
    <div class="flex items-center justify-between mb-6">
      <h1 class="text-xl font-bold">カテゴリ管理</h1>
      <div class="flex gap-2">
        <button @click="handleSync" :disabled="syncing"
          class="bg-indigo-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-indigo-700 disabled:opacity-50">
          {{ syncing ? '同期中...' : 'WPから同期' }}
        </button>
        <button @click="showCreateForm = !showCreateForm"
          class="bg-gray-100 text-gray-700 px-4 py-2 rounded-lg text-sm hover:bg-gray-200">
          新規作成
        </button>
      </div>
    </div>

    <div v-if="showCreateForm" class="bg-white rounded-lg shadow p-6 mb-4">
      <h2 class="text-sm font-medium text-gray-500 mb-3">カテゴリを作成</h2>
      <form @submit.prevent="handleCreate" class="flex gap-3 items-end">
        <div class="flex-1">
          <label class="block text-sm text-gray-700 mb-1">名前</label>
          <input v-model="newCategory.name" type="text" required
            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500" />
        </div>
        <div class="flex-1">
          <label class="block text-sm text-gray-700 mb-1">スラッグ（任意）</label>
          <input v-model="newCategory.slug" type="text"
            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500" />
        </div>
        <button type="submit" :disabled="creating"
          class="bg-green-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-green-700 disabled:opacity-50">
          {{ creating ? '作成中...' : '作成' }}
        </button>
      </form>
    </div>

    <div v-if="syncMessage" class="bg-blue-50 text-blue-700 text-sm p-3 rounded-lg mb-4">{{ syncMessage }}</div>
    <div v-if="error" class="bg-red-50 text-red-600 text-sm p-3 rounded-lg mb-4">{{ error }}</div>

    <div v-if="loading" class="text-gray-400 text-sm">読み込み中...</div>

    <div v-else-if="categories.length === 0" class="bg-white rounded-lg shadow p-8 text-center text-gray-500">
      カテゴリがありません。WPから同期するか、新規作成してください。
    </div>

    <div v-else class="bg-white rounded-lg shadow overflow-hidden">
      <table class="w-full text-sm">
        <thead class="bg-gray-50 text-gray-600">
          <tr>
            <th class="text-left px-4 py-3">名前</th>
            <th class="text-left px-4 py-3">スラッグ</th>
            <th class="text-left px-4 py-3">WP ID</th>
            <th class="text-left px-4 py-3">投稿数</th>
            <th class="text-left px-4 py-3">有効</th>
            <th class="px-4 py-3">操作</th>
          </tr>
        </thead>
        <tbody class="divide-y">
          <tr v-for="cat in categories" :key="cat.id" class="hover:bg-gray-50">
            <td class="px-4 py-3 font-medium">{{ cat.name }}</td>
            <td class="px-4 py-3 text-gray-500">{{ cat.slug }}</td>
            <td class="px-4 py-3 text-gray-500">{{ cat.wp_category_id || '—' }}</td>
            <td class="px-4 py-3 text-gray-500">{{ cat.posts_count ?? 0 }}</td>
            <td class="px-4 py-3">
              <button @click="handleToggleActive(cat)"
                :class="cat.is_active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-400'"
                class="px-2 py-0.5 rounded-full text-xs font-medium">
                {{ cat.is_active ? '有効' : '無効' }}
              </button>
            </td>
            <td class="px-4 py-3 text-right">
              <button @click="handleDelete(cat)"
                class="text-red-500 hover:text-red-700 text-xs" :disabled="cat.posts_count > 0">
                削除
              </button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { useApi } from '../../composables/useApi'

const api = useApi()
const loading = ref(true)
const syncing = ref(false)
const creating = ref(false)
const categories = ref([])
const error = ref(null)
const syncMessage = ref(null)
const showCreateForm = ref(false)

const newCategory = reactive({ name: '', slug: '' })

onMounted(async () => {
  await fetchCategories()
})

async function fetchCategories() {
  loading.value = true
  try {
    const { data } = await api.get('/admin/categories')
    categories.value = data.data
  } finally {
    loading.value = false
  }
}

async function handleSync() {
  syncing.value = true
  error.value = null
  syncMessage.value = null
  try {
    const { data } = await api.post('/admin/categories/sync')
    syncMessage.value = data.message
    await fetchCategories()
  } catch (e) {
    error.value = e.response?.data?.error || 'WPからの同期に失敗しました'
  } finally {
    syncing.value = false
  }
}

async function handleCreate() {
  creating.value = true
  error.value = null
  try {
    await api.post('/admin/categories', {
      name: newCategory.name,
      slug: newCategory.slug || undefined,
    })
    newCategory.name = ''
    newCategory.slug = ''
    showCreateForm.value = false
    await fetchCategories()
  } catch (e) {
    error.value = e.response?.data?.error || 'カテゴリの作成に失敗しました'
  } finally {
    creating.value = false
  }
}

async function handleToggleActive(cat) {
  error.value = null
  try {
    const { data } = await api.put(`/admin/categories/${cat.id}`, { is_active: !cat.is_active })
    cat.is_active = data.data.is_active
  } catch (e) {
    error.value = e.response?.data?.error || '更新に失敗しました'
  }
}

async function handleDelete(cat) {
  if (!confirm(`カテゴリ「${cat.name}」を削除しますか？`)) return
  error.value = null
  try {
    await api.delete(`/admin/categories/${cat.id}`)
    await fetchCategories()
  } catch (e) {
    error.value = e.response?.data?.error || '削除に失敗しました'
  }
}
</script>
