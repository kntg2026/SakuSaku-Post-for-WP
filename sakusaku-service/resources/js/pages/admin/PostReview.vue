<template>
  <div v-if="post">
    <div class="flex items-center justify-between mb-6">
      <h1 class="text-xl font-bold">{{ post.title || '(無題)' }}</h1>
      <router-link to="/admin/posts" class="text-sm text-gray-500 hover:text-gray-700">← 投稿一覧に戻る</router-link>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
      <div class="lg:col-span-2 space-y-4">
        <div class="bg-white rounded-lg shadow p-6">
          <h2 class="text-sm font-medium text-gray-500 mb-3">記事情報</h2>
          <dl class="grid grid-cols-2 gap-3 text-sm">
            <dt class="text-gray-500">投稿者</dt>
            <dd>{{ post.user?.name }} ({{ post.user?.email }})</dd>
            <dt class="text-gray-500">ステータス</dt>
            <dd>
              <span :class="statusClass(post.status)" class="px-2 py-0.5 rounded-full text-xs font-medium">
                {{ statusLabel(post.status) }}
              </span>
            </dd>
            <dt class="text-gray-500">カテゴリ</dt>
            <dd>
              <select v-model="selectedCategoryId" @change="handleCategoryChange"
                class="border border-gray-300 rounded px-2 py-1 text-sm">
                <option :value="null">選択なし</option>
                <option v-for="cat in categories" :key="cat.id" :value="cat.id">{{ cat.name }}</option>
              </select>
            </dd>
            <dt class="text-gray-500">送信日</dt>
            <dd>{{ formatDate(post.created_at) }}</dd>
            <dt v-if="post.published_at" class="text-gray-500">公開日</dt>
            <dd v-if="post.published_at">{{ formatDate(post.published_at) }}</dd>
            <dt class="text-gray-500">Google Docs</dt>
            <dd>
              <a :href="post.google_doc_url" target="_blank" class="text-indigo-600 hover:underline">元のドキュメント</a>
            </dd>
            <dt class="text-gray-500">画像数</dt>
            <dd>{{ post.images_count ?? 0 }}</dd>
          </dl>
        </div>

        <div v-if="post.poster_comment" class="bg-white rounded-lg shadow p-6">
          <h2 class="text-sm font-medium text-gray-500 mb-2">投稿者コメント</h2>
          <p class="text-sm">{{ post.poster_comment }}</p>
        </div>

        <div v-if="post.tags?.length" class="bg-white rounded-lg shadow p-6">
          <h2 class="text-sm font-medium text-gray-500 mb-2">タグ</h2>
          <div class="flex flex-wrap gap-1">
            <span v-for="tag in post.tags" :key="tag" class="bg-gray-100 text-gray-600 text-xs px-2 py-0.5 rounded">
              {{ tag }}
            </span>
          </div>
        </div>

        <div class="bg-white rounded-lg shadow p-6">
          <h2 class="text-sm font-medium text-gray-500 mb-2">管理者コメント</h2>
          <textarea v-model="adminComment" rows="3" maxlength="2000"
            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500"
            placeholder="投稿者へのコメント（差し戻し理由など）"></textarea>
        </div>
      </div>

      <div class="space-y-4">
        <div class="bg-white rounded-lg shadow p-6 space-y-3">
          <h2 class="text-sm font-medium text-gray-500">アクション</h2>

          <a v-if="post.wp_preview_url" :href="post.wp_preview_url" target="_blank"
            class="block w-full text-center bg-indigo-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-indigo-700">
            プレビュー
          </a>
          <a v-if="post.wp_permalink" :href="post.wp_permalink" target="_blank"
            class="block w-full text-center bg-emerald-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-emerald-700">
            公開ページを見る
          </a>

          <button v-if="canApprove" @click="handleAction('approve')" :disabled="actionLoading"
            class="w-full bg-green-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-green-700 disabled:opacity-50">
            承認する
          </button>
          <button v-if="canPublish" @click="handleAction('publish')" :disabled="actionLoading"
            class="w-full bg-emerald-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-emerald-700 disabled:opacity-50">
            公開する
          </button>
          <button v-if="canReject" @click="handleAction('reject')" :disabled="actionLoading"
            class="w-full bg-red-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-red-700 disabled:opacity-50">
            差し戻す
          </button>
          <button v-if="canDelete" @click="handleDelete" :disabled="actionLoading"
            class="w-full bg-gray-200 text-red-600 px-4 py-2 rounded-lg text-sm hover:bg-gray-300 disabled:opacity-50">
            削除
          </button>
        </div>

        <div v-if="error" class="bg-red-50 text-red-600 text-sm p-3 rounded-lg">{{ error }}</div>
      </div>
    </div>
  </div>
  <div v-else-if="loading" class="text-gray-500">読み込み中...</div>
  <div v-else class="text-red-500">投稿が見つかりませんでした。</div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useApi } from '../../composables/useApi'

const props = defineProps({ id: [String, Number] })
const router = useRouter()
const api = useApi()

const loading = ref(true)
const actionLoading = ref(false)
const post = ref(null)
const categories = ref([])
const selectedCategoryId = ref(null)
const adminComment = ref('')
const error = ref(null)

const canApprove = computed(() => post.value && ['draft', 'rejected'].includes(post.value.status))
const canPublish = computed(() => post.value && ['draft', 'approved'].includes(post.value.status) && post.value.wp_post_id)
const canReject = computed(() => post.value && ['draft', 'approved'].includes(post.value.status))
const canDelete = computed(() => post.value && post.value.status !== 'published')

onMounted(async () => {
  await Promise.all([fetchPost(), fetchCategories()])
})

async function fetchPost() {
  loading.value = true
  try {
    const { data } = await api.get(`/admin/posts/${props.id}`)
    post.value = data.data
    selectedCategoryId.value = post.value.category?.id || null
    adminComment.value = post.value.admin_comment || ''
  } catch {
    post.value = null
  } finally {
    loading.value = false
  }
}

async function fetchCategories() {
  try {
    const { data } = await api.get('/admin/categories')
    categories.value = data.data
  } catch {
    // silent
  }
}

async function handleCategoryChange() {
  if (!selectedCategoryId.value) return
  try {
    const { data } = await api.put(`/admin/posts/${post.value.id}/category`, {
      category_id: selectedCategoryId.value,
    })
    post.value = data.data
  } catch (e) {
    error.value = e.response?.data?.error || 'カテゴリの更新に失敗しました'
  }
}

async function handleAction(action) {
  const labels = { approve: '承認', publish: '公開', reject: '差し戻し' }
  if (!confirm(`この投稿を${labels[action]}しますか？`)) return

  actionLoading.value = true
  error.value = null
  try {
    const payload = {}
    if (adminComment.value) {
      payload.admin_comment = adminComment.value
    }
    const { data } = await api.post(`/admin/posts/${post.value.id}/${action}`, payload)
    post.value = data.data
  } catch (e) {
    error.value = e.response?.data?.error || `${labels[action]}に失敗しました`
  } finally {
    actionLoading.value = false
  }
}

async function handleDelete() {
  if (!confirm('この投稿を削除しますか？この操作は取り消せません。')) return
  actionLoading.value = true
  try {
    await api.delete(`/admin/posts/${post.value.id}`)
    router.push('/admin/posts')
  } catch (e) {
    error.value = e.response?.data?.error || '削除に失敗しました'
    actionLoading.value = false
  }
}

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
