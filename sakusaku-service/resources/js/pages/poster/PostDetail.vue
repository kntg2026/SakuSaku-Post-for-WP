<template>
  <div v-if="post">
    <div class="flex items-center justify-between mb-6">
      <h1 class="text-xl font-bold">{{ post.title || '(無題)' }}</h1>
      <router-link to="/posts" class="text-sm text-gray-500 hover:text-gray-700">← 一覧に戻る</router-link>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
      <div class="lg:col-span-2 space-y-4">
        <div class="bg-white rounded-lg shadow p-6">
          <h2 class="text-sm font-medium text-gray-500 mb-3">記事情報</h2>
          <dl class="grid grid-cols-2 gap-3 text-sm">
            <dt class="text-gray-500">ステータス</dt>
            <dd><span :class="statusClass(post.status)" class="px-2 py-0.5 rounded-full text-xs font-medium">{{ statusLabel(post.status) }}</span></dd>
            <dt class="text-gray-500">カテゴリ</dt>
            <dd>{{ post.category?.name || '—' }}</dd>
            <dt class="text-gray-500">送信日</dt>
            <dd>{{ formatDate(post.created_at) }}</dd>
            <dt v-if="post.published_at" class="text-gray-500">公開日</dt>
            <dd v-if="post.published_at">{{ formatDate(post.published_at) }}</dd>
            <dt class="text-gray-500">Google Docs</dt>
            <dd><a :href="post.google_doc_url" target="_blank" class="text-indigo-600 hover:underline">元のドキュメント</a></dd>
          </dl>
        </div>

        <div v-if="post.poster_comment" class="bg-white rounded-lg shadow p-6">
          <h2 class="text-sm font-medium text-gray-500 mb-2">投稿者コメント</h2>
          <p class="text-sm">{{ post.poster_comment }}</p>
        </div>

        <div v-if="post.admin_comment" class="bg-white rounded-lg shadow p-6">
          <h2 class="text-sm font-medium text-gray-500 mb-2">管理者コメント</h2>
          <p class="text-sm">{{ post.admin_comment }}</p>
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
          <button v-if="canPublish" @click="handlePublish"
            class="w-full bg-green-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-green-700">
            公開する
          </button>
        </div>

        <div v-if="post.tags?.length" class="bg-white rounded-lg shadow p-6">
          <h2 class="text-sm font-medium text-gray-500 mb-2">タグ</h2>
          <div class="flex flex-wrap gap-1">
            <span v-for="tag in post.tags" :key="tag" class="bg-gray-100 text-gray-600 text-xs px-2 py-0.5 rounded">{{ tag }}</span>
          </div>
        </div>
      </div>
    </div>
  </div>
  <div v-else class="text-gray-500">読み込み中...</div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { usePostsStore } from '../../stores/posts'
import { useAuthStore } from '../../stores/auth'

const props = defineProps({ id: [String, Number] })
const postsStore = usePostsStore()
const auth = useAuthStore()
const post = ref(null)

onMounted(async () => { post.value = await postsStore.fetchPost(props.id) })

const canPublish = computed(() =>
  auth.userLevel >= 2 && post.value && ['draft', 'approved'].includes(post.value.status) && post.value.wp_post_id
)

async function handlePublish() {
  if (!confirm('この記事を公開しますか？')) return
  post.value = await postsStore.publishPost(post.value.id)
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
