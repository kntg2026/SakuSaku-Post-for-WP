<template>
  <div>
    <h1 class="text-xl font-bold mb-6">ダッシュボード</h1>

    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-8">
      <div v-for="stat in stats" :key="stat.label" class="bg-white rounded-lg shadow p-5">
        <p class="text-sm text-gray-500">{{ stat.label }}</p>
        <p class="text-2xl font-bold mt-1">{{ stat.value }}</p>
      </div>
    </div>

    <div class="bg-white rounded-lg shadow p-6">
      <h2 class="text-sm font-medium text-gray-500 mb-4">最近の投稿</h2>
      <div v-if="loading" class="text-gray-400 text-sm">読み込み中...</div>
      <table v-else class="w-full text-sm">
        <thead class="text-gray-500 border-b">
          <tr>
            <th class="text-left py-2">タイトル</th>
            <th class="text-left py-2">投稿者</th>
            <th class="text-left py-2">ステータス</th>
            <th class="text-left py-2">日時</th>
          </tr>
        </thead>
        <tbody class="divide-y">
          <tr v-for="post in recentPosts" :key="post.id" class="hover:bg-gray-50 cursor-pointer"
            @click="$router.push(`/admin/posts/${post.id}`)">
            <td class="py-2">{{ post.title || '(無題)' }}</td>
            <td class="py-2 text-gray-500">{{ post.user?.name }}</td>
            <td class="py-2">
              <span :class="statusClass(post.status)" class="px-2 py-0.5 rounded-full text-xs font-medium">{{ statusLabel(post.status) }}</span>
            </td>
            <td class="py-2 text-gray-500">{{ formatDate(post.created_at) }}</td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useApi } from '../../composables/useApi'

const api = useApi()
const loading = ref(true)
const recentPosts = ref([])
const stats = ref([
  { label: '今月の投稿', value: '—' },
  { label: 'レビュー待ち', value: '—' },
  { label: '今月公開', value: '—' },
  { label: '投稿者数', value: '—' },
])

onMounted(async () => {
  try {
    const [dashRes, postsRes] = await Promise.all([
      api.get('/admin/dashboard'),
      api.get('/admin/posts', { params: { per_page: 10 } }),
    ])

    const d = dashRes.data
    stats.value[0].value = d.total_posts_this_month
    stats.value[1].value = d.pending_count
    stats.value[2].value = d.published_count
    stats.value[3].value = d.active_users_count

    recentPosts.value = postsRes.data.data
  } finally {
    loading.value = false
  }
})

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
