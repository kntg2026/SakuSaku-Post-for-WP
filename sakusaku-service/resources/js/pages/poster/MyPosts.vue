<template>
  <div>
    <h1 class="text-xl font-bold mb-6">投稿一覧</h1>

    <div v-if="postsStore.loading" class="text-gray-500 text-sm">読み込み中...</div>

    <div v-else-if="postsStore.posts.length === 0" class="bg-white rounded-lg shadow p-8 text-center text-gray-500">
      まだ投稿がありません。<router-link to="/submit" class="text-indigo-600 hover:underline">記事を送信</router-link>してみましょう。
    </div>

    <div v-else class="bg-white rounded-lg shadow overflow-hidden">
      <table class="w-full text-sm">
        <thead class="bg-gray-50 text-gray-600">
          <tr>
            <th class="text-left px-4 py-3">タイトル</th>
            <th class="text-left px-4 py-3">カテゴリ</th>
            <th class="text-left px-4 py-3">ステータス</th>
            <th class="text-left px-4 py-3">送信日</th>
            <th class="px-4 py-3"></th>
          </tr>
        </thead>
        <tbody class="divide-y">
          <tr v-for="post in postsStore.posts" :key="post.id" class="hover:bg-gray-50 cursor-pointer"
            @click="router.push(`/posts/${post.id}`)">
            <td class="px-4 py-3 font-medium">{{ post.title || '(無題)' }}</td>
            <td class="px-4 py-3 text-gray-500">{{ post.category?.name || '—' }}</td>
            <td class="px-4 py-3">
              <span :class="statusClass(post.status)" class="px-2 py-0.5 rounded-full text-xs font-medium">
                {{ statusLabel(post.status) }}
              </span>
            </td>
            <td class="px-4 py-3 text-gray-500">{{ formatDate(post.created_at) }}</td>
            <td class="px-4 py-3 text-right">
              <button v-if="canDelete(post)" @click.stop="handleDelete(post)"
                class="text-red-500 hover:text-red-700 text-xs">削除</button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>

<script setup>
import { onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { usePostsStore } from '../../stores/posts'

const router = useRouter()
const postsStore = usePostsStore()

onMounted(() => postsStore.fetchPosts())

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
function canDelete(post) { return ['pending', 'draft', 'rejected', 'failed'].includes(post.status) }

function formatDate(iso) {
  return new Date(iso).toLocaleDateString('ja-JP', { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' })
}

async function handleDelete(post) {
  if (!confirm(`「${post.title || '(無題)'}」を削除しますか？`)) return
  await postsStore.deletePost(post.id)
}
</script>
