<template>
  <div>
    <h1 class="text-xl font-bold mb-6">通知設定</h1>

    <div v-if="loading" class="text-gray-400 text-sm">読み込み中...</div>

    <form v-else @submit.prevent="handleSave" class="space-y-6 max-w-2xl">
      <div class="bg-white rounded-lg shadow p-6 space-y-4">
        <h2 class="text-sm font-medium text-gray-500">Google Chat</h2>
        <div>
          <label class="block text-sm text-gray-700 mb-1">Webhook URL</label>
          <input v-model="form.google_chat_webhook" type="url" placeholder="https://chat.googleapis.com/v1/spaces/..."
            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500" />
        </div>
        <button type="button" @click="handleTest('google_chat')" :disabled="!form.google_chat_webhook || testing"
          class="text-sm text-indigo-600 hover:text-indigo-800 disabled:text-gray-400">
          {{ testing === 'google_chat' ? 'テスト送信中...' : 'テスト送信' }}
        </button>
      </div>

      <div class="bg-white rounded-lg shadow p-6 space-y-4">
        <h2 class="text-sm font-medium text-gray-500">Microsoft Teams</h2>
        <div>
          <label class="block text-sm text-gray-700 mb-1">Webhook URL</label>
          <input v-model="form.teams_webhook" type="url" placeholder="https://outlook.office.com/webhook/..."
            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500" />
        </div>
        <button type="button" @click="handleTest('teams')" :disabled="!form.teams_webhook || testing"
          class="text-sm text-indigo-600 hover:text-indigo-800 disabled:text-gray-400">
          {{ testing === 'teams' ? 'テスト送信中...' : 'テスト送信' }}
        </button>
      </div>

      <div class="bg-white rounded-lg shadow p-6 space-y-4">
        <h2 class="text-sm font-medium text-gray-500">通知イベント</h2>
        <label class="flex items-center gap-2">
          <input v-model="form.events.on_submit" type="checkbox" class="rounded border-gray-300 text-indigo-600" />
          <span class="text-sm text-gray-700">記事が送信されたとき</span>
        </label>
        <label class="flex items-center gap-2">
          <input v-model="form.events.on_publish" type="checkbox" class="rounded border-gray-300 text-indigo-600" />
          <span class="text-sm text-gray-700">記事が公開されたとき</span>
        </label>
      </div>

      <div v-if="error" class="bg-red-50 text-red-600 text-sm p-3 rounded-lg">{{ error }}</div>
      <div v-if="success" class="bg-green-50 text-green-600 text-sm p-3 rounded-lg">{{ success }}</div>
      <div v-if="testResult" class="bg-blue-50 text-blue-700 text-sm p-3 rounded-lg">{{ testResult }}</div>

      <button type="submit" :disabled="saving"
        class="bg-indigo-600 text-white px-6 py-2 rounded-lg text-sm font-medium hover:bg-indigo-700 disabled:opacity-50">
        {{ saving ? '保存中...' : '保存' }}
      </button>
    </form>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { useApi } from '../../composables/useApi'

const api = useApi()
const loading = ref(true)
const saving = ref(false)
const testing = ref(null)
const error = ref(null)
const success = ref(null)
const testResult = ref(null)

const form = reactive({
  google_chat_webhook: '',
  teams_webhook: '',
  events: {
    on_submit: false,
    on_publish: false,
  },
})

onMounted(async () => {
  try {
    const { data } = await api.get('/admin/notifications')
    form.google_chat_webhook = data.google_chat_webhook || ''
    form.teams_webhook = data.teams_webhook || ''
    form.events.on_submit = data.events?.on_submit || false
    form.events.on_publish = data.events?.on_publish || false
  } finally {
    loading.value = false
  }
})

async function handleSave() {
  saving.value = true
  error.value = null
  success.value = null
  try {
    await api.put('/admin/notifications', form)
    success.value = '通知設定を保存しました'
  } catch (e) {
    error.value = e.response?.data?.message || '保存に失敗しました'
  } finally {
    saving.value = false
  }
}

async function handleTest(type) {
  testing.value = type
  testResult.value = null
  error.value = null
  try {
    const { data } = await api.post('/admin/notifications/test', { type })
    testResult.value = data.message
  } catch (e) {
    error.value = e.response?.data?.error || 'テスト送信に失敗しました'
  } finally {
    testing.value = null
  }
}
</script>
