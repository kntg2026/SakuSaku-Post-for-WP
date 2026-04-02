<template>
  <div>
    <h1 class="text-xl font-bold mb-6">テナント設定</h1>

    <div v-if="loading" class="text-gray-400 text-sm">読み込み中...</div>

    <div v-else class="space-y-6 max-w-2xl">
      <div class="bg-white rounded-lg shadow p-6 space-y-4">
        <h2 class="text-sm font-medium text-gray-500">WordPress接続</h2>
        <div>
          <label class="block text-sm text-gray-700 mb-1">サイトURL</label>
          <input :value="settings.wp_site_url" type="text" readonly
            class="w-full border border-gray-200 bg-gray-50 rounded-lg px-3 py-2 text-sm text-gray-500" />
        </div>
        <div>
          <label class="block text-sm text-gray-700 mb-1">APIキー</label>
          <div class="flex gap-2">
            <input :value="showApiKey ? settings.wp_api_key : '••••••••••••'" type="text" readonly
              class="flex-1 border border-gray-200 bg-gray-50 rounded-lg px-3 py-2 text-sm text-gray-500 font-mono" />
            <button @click="showApiKey = !showApiKey" type="button"
              class="text-sm text-indigo-600 hover:text-indigo-800 px-2">
              {{ showApiKey ? '隠す' : '表示' }}
            </button>
          </div>
        </div>
        <button @click="handleTestWp" :disabled="testingWp" type="button"
          class="bg-indigo-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-indigo-700 disabled:opacity-50">
          {{ testingWp ? 'テスト中...' : '接続テスト' }}
        </button>
        <div v-if="wpTestResult"
          :class="wpTestResult.success ? 'bg-green-50 text-green-600' : 'bg-red-50 text-red-600'"
          class="text-sm p-3 rounded-lg">
          {{ wpTestResult.message }}
        </div>
      </div>

      <form @submit.prevent="handleSave" class="space-y-6">
        <div class="bg-white rounded-lg shadow p-6 space-y-4">
          <h2 class="text-sm font-medium text-gray-500">ドキュメント取得方法</h2>
          <select v-model="form.docs_retrieval_method"
            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500">
            <option value="url_direct">URL直接取得</option>
            <option value="oauth">OAuth</option>
            <option value="service_account">サービスアカウント</option>
          </select>
          <p class="text-xs text-gray-400">
            Google Docsからのコンテンツ取得方法を選択してください。
          </p>
        </div>

        <div v-if="error" class="bg-red-50 text-red-600 text-sm p-3 rounded-lg">{{ error }}</div>
        <div v-if="success" class="bg-green-50 text-green-600 text-sm p-3 rounded-lg">{{ success }}</div>

        <button type="submit" :disabled="saving"
          class="bg-indigo-600 text-white px-6 py-2 rounded-lg text-sm font-medium hover:bg-indigo-700 disabled:opacity-50">
          {{ saving ? '保存中...' : '保存' }}
        </button>
      </form>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { useApi } from '../../composables/useApi'

const api = useApi()
const loading = ref(true)
const saving = ref(false)
const testingWp = ref(false)
const showApiKey = ref(false)
const error = ref(null)
const success = ref(null)
const wpTestResult = ref(null)

const settings = reactive({
  wp_site_url: '',
  wp_api_key: '',
})

const form = reactive({
  docs_retrieval_method: 'url_direct',
})

onMounted(async () => {
  try {
    const { data } = await api.get('/admin/settings')
    settings.wp_site_url = data.wp_site_url || ''
    settings.wp_api_key = data.wp_api_key || ''
    form.docs_retrieval_method = data.docs_retrieval_method || 'url_direct'
  } finally {
    loading.value = false
  }
})

async function handleSave() {
  saving.value = true
  error.value = null
  success.value = null
  try {
    await api.put('/admin/settings', {
      docs_retrieval_method: form.docs_retrieval_method,
    })
    success.value = '設定を保存しました'
  } catch (e) {
    error.value = e.response?.data?.message || '保存に失敗しました'
  } finally {
    saving.value = false
  }
}

async function handleTestWp() {
  testingWp.value = true
  wpTestResult.value = null
  try {
    const { data } = await api.post('/admin/settings/test-wp')
    wpTestResult.value = data
  } catch (e) {
    wpTestResult.value = {
      success: false,
      message: e.response?.data?.message || '接続テストに失敗しました',
    }
  } finally {
    testingWp.value = false
  }
}
</script>
