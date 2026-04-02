<template>
  <div>
    <h1 class="text-xl font-bold mb-6">記事を送信</h1>

    <form @submit.prevent="handleSubmit" class="bg-white rounded-lg shadow p-6 space-y-5 max-w-2xl">
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Google Docs URL</label>
        <input v-model="form.google_doc_url" type="url" required
          placeholder="https://docs.google.com/document/d/..."
          class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" />
        <p v-if="docId" class="text-xs text-green-600 mt-1">Doc ID: {{ docId }}</p>
      </div>

      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">カテゴリ</label>
        <select v-model="form.category_id"
          class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500">
          <option :value="null">選択なし</option>
          <option v-for="cat in categories" :key="cat.id" :value="cat.id">{{ cat.name }}</option>
        </select>
      </div>

      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">管理者へのコメント（任意）</label>
        <textarea v-model="form.poster_comment" rows="3" maxlength="2000"
          class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500"
          placeholder="公開タイミングの希望など"></textarea>
      </div>

      <div class="bg-blue-50 p-3 rounded-lg text-xs text-blue-700">
        <p class="font-medium mb-1">画像ルール</p>
        <ul class="list-disc list-inside space-y-0.5">
          <li>JPEG / PNG / GIF / WebP のみ対応</li>
          <li>1枚あたり最大20MB</li>
          <li>幅1600pxを超える場合は自動リサイズ</li>
        </ul>
      </div>

      <div v-if="error" class="bg-red-50 text-red-600 text-sm p-3 rounded">{{ error }}</div>

      <button type="submit" :disabled="submitting || !docId"
        class="bg-indigo-600 text-white px-6 py-2 rounded-lg text-sm font-medium hover:bg-indigo-700 disabled:opacity-50 disabled:cursor-not-allowed">
        {{ submitting ? '送信中...' : '送信' }}
      </button>
    </form>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { usePostsStore } from '../../stores/posts'
import { useCategoriesStore } from '../../stores/categories'

const router = useRouter()
const postsStore = usePostsStore()
const categoriesStore = useCategoriesStore()

const categories = computed(() => categoriesStore.categories)
const submitting = ref(false)
const error = ref(null)

const form = reactive({
  google_doc_url: '',
  category_id: null,
  poster_comment: '',
})

const docId = computed(() => {
  const match = form.google_doc_url.match(/\/document\/d\/([a-zA-Z0-9_-]+)/)
  return match?.[1] || null
})

onMounted(() => {
  categoriesStore.fetchCategories()
})

async function handleSubmit() {
  submitting.value = true
  error.value = null
  try {
    await postsStore.submitPost(form)
    router.push('/posts')
  } catch (e) {
    error.value = e.response?.data?.message || e.response?.data?.error || '送信に失敗しました'
  } finally {
    submitting.value = false
  }
}
</script>
