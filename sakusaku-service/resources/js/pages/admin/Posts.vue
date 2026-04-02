<template>
  <div>
    <h1 class="text-xl font-bold mb-6">投稿管理</h1>

    <div class="flex flex-wrap gap-3 mb-4">
      <select v-model="filters.status"
        class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500">
        <option value="">すべてのステータス</option>
        <option value="pending">処理待ち</option>
        <option value="processing">処理中</option>
        <option value="draft">下書き</option>
        <option value="approved">承認済み</option>
        <option value="published">公開済み</option>
        <option value="rejected">差し戻し</option>
        <option value="failed">失敗</option>
      </select>

      <select v-model="filters.category_id"
        class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500">
        <option value="">すべてのカテゴリ</option>
        <option v-for="cat in categories" :key="cat.id" :value="cat.id">{{ cat.name }}</option>
      </select>

      <input v-model="filters.search" type="text" placeholder="タイトル・投稿者で検索"
        class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 w-64"
        @keyup.enter="fetchPosts" />

      <button @click="fetchPosts"
        class="bg-indigo-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-indigo-700">検索</button>
    </div>

    <div v-if="loading" class="text-gray-400 text-sm">読み込み中...</div>

    <div v-else-if="posts.length === 0" class="bg-white rounded-lg shadow p-8 text-center text-gray-500">
      該当する投稿はありません。
    </div>

    <div v-else class="bg-white rounded-lg shadow overflow-hidden">
      <table class="w-full text-sm">
        <thead class="bg-gray-50 text-gray-600">
          <tr>
            <th class="text-left px-4 py-3">タイトル</th>
            <th class="text-left px-4 py-3">投稿者</th>
            <th class="text-left px-4 py-3">カテゴリ</th>
            <th class="text-left px-4 py-3">ステータス</th>
            <th class="text-left px-4 py-3">日時</th>
            <th class="px-4 py-3">操作</th>
          </tr>
        </thead>
        <tbody class="divide-y">
          <tr v-for="post in posts" :key="post.id" class="hover:bg-gray-50 cursor-pointer"
            @click="$router.push(`/admin/posts/${post.id}`)">
            <td class="px-4 py-3 font-medium">{{ post.title || '(無題)' }}</td>
            <td class="px-4 py-3 text-gray-500">{{ post.user?.name }}</td>
            <td class="px-4 py-3 text-gray-500">{{ post.category?.name || '—' }}</td>
            <td class="px-4 py-3">
              <span :class="statusClass(post.status)" class="px-2 py-0.5 rounded-full text-xs font-medium">
                {{ statusLabel(post.status) }}
              </span>
            </td>
            <td class="px-4 py-3 text-gray-500">{{ formatDate(post.created_at) }}</td>
            <td class="px-4 py-3 text-right" @click.stop>
              <button v-if="canApprove(post)" @click="handleAction(post.id, 'approve')"
                class="text-green-600 hover:text-green-800 text-xs mr-2">承認</button>
              <button v-if="canPublish(post)" @click="handleAction(post.id, 'publish')"
                class="text-emerald-600 hover:text-emerald-800 text-xs mr-2">公開</button>
              <button v-if="canReject(post)" @click="handleAction(post.id, 'reject')"
                class="text-red-500 hover:text-red-700 text-xs">差し戻し</button>
            </td>
          </tr>
        </tbody>
      </table>

      <div v-if="meta.last_page > 1" class="flex justify-center gap-2 p-4 border-t">
        <button v-for="page in meta.last_page" :key="page"
          :class="page === meta.current_page ? 'bg-indigo-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'"
          class="px-3 py-1 rounded text-sm" @click="goToPage(page)">{{ page }}</button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { useApi } from '../../composables/useApi'

const api = useApi()
const loading = ref(true)
const posts = ref([])
const categories = ref([])
const meta = ref({})

const filters = reactive({
  status: '',
  category_id: '',
  search: '',
})

onMounted(async () => {
  await Promise.all([fetchPosts(), fetchCategories()])
})

async function fetchCategories() {
  try {
    const { data } = await api.get('/admin/categories')
    categories.value = data.data
  } catch {
    // silent
  }
}

async function fetchPosts(page = 1) {
  loading.value = true
  try {
    const params = { page }
    if (filters.status) params.status = filters.status
    if (filters.category_id) params.category_id = filters.category_id
    if (filters.search) params.search = filters.search

    const { data } = await api.get('/admin/posts', { params })
    posts.value = data.data
    meta.value = data.meta || {}
  } finally {
    loading.value = false
  }
}

function goToPage(page) {
  fetchPosts(page)
}

async function handleAction(postId, action) {
  const labels = { approve: '承認', publish: '公開', reject: '差し戻し' }
  if (!confirm(`この投稿を${labels[action]}しますか？`)) return
  try {
    await api.post(`/admin/posts/${postId}/${action}`)
    await fetchPosts()
  } catch (e) {
    alert(e.response?.data?.error || `${labels[action]}に失敗しました`)
  }
}

function canApprove(post) { return ['draft', 'rejected'].includes(post.status) }
function canPublish(post) { return ['draft', 'approved'].includes(post.status) }
function canReject(post) { return ['draft', 'approved'].includes(post.status) }

const statusMap = {
  pending: { label: '処理待ち', class: 'bg-yellow-100 text-yellow-700' },
  processing: { label: '処理中', class: 'bg-blue-100 text-blue-700' },
  draft: { label: '下書き', class: 'bg-gray-100 text-gray-700' },
  approved: { label: '承認済み', class: 'bg-green-100 text-green-700' },
  published: { label: '公開済み', class: 'bg-emerald-100 text-emerald-700' },
  rejected: { label: '差し戻し', class: 'bg-red-100 text-red-700' },
  failed: { label: '失敗', class: 'bg-red-100 text-red-700' },
}

function statusLabel(s) { return statusMap[s]?.label || s }
function statusClass(s) { return statusMap[s]?.class || '' }
function formatDate(iso) { return new Date(iso).toLocaleDateString('ja-JP', { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' }) }
</script>
