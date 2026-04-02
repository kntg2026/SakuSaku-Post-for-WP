<template>
  <div>
    <h1 class="text-xl font-bold mb-6">プラン・請求</h1>

    <div v-if="loading" class="text-gray-400 text-sm">読み込み中...</div>

    <div v-else class="space-y-6 max-w-2xl">
      <div class="bg-white rounded-lg shadow p-6">
        <h2 class="text-sm font-medium text-gray-500 mb-4">現在のプラン</h2>
        <div class="flex items-center justify-between">
          <div>
            <p class="text-lg font-bold">{{ planLabel }}</p>
            <p class="text-sm text-gray-500 mt-1">{{ statusDescription }}</p>
          </div>
          <span :class="statusBadgeClass" class="px-3 py-1 rounded-full text-sm font-medium">
            {{ statusLabel }}
          </span>
        </div>
      </div>

      <div v-if="trialDaysRemaining !== null" class="bg-white rounded-lg shadow p-6">
        <h2 class="text-sm font-medium text-gray-500 mb-3">トライアル</h2>
        <div class="flex items-center gap-4">
          <div class="flex-1">
            <div class="bg-gray-200 rounded-full h-2">
              <div class="bg-indigo-600 rounded-full h-2" :style="{ width: trialProgress + '%' }"></div>
            </div>
          </div>
          <span class="text-sm font-medium" :class="trialDaysRemaining <= 3 ? 'text-red-600' : 'text-gray-700'">
            残り{{ trialDaysRemaining }}日
          </span>
        </div>
      </div>

      <div class="bg-white rounded-lg shadow p-6">
        <h2 class="text-sm font-medium text-gray-500 mb-4">プランのアップグレード</h2>
        <p class="text-sm text-gray-500 mb-4">
          有料プランに切り替えると、より多くの投稿と高度な機能をご利用いただけます。
        </p>
        <button @click="handleUpgrade" disabled
          class="bg-indigo-600 text-white px-6 py-2 rounded-lg text-sm font-medium hover:bg-indigo-700 disabled:opacity-50 disabled:cursor-not-allowed">
          アップグレード（準備中）
        </button>
        <p class="text-xs text-gray-400 mt-2">Stripe決済は準備中です。</p>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useApi } from '../../composables/useApi'

const api = useApi()
const loading = ref(true)
const tenant = ref(null)

onMounted(async () => {
  try {
    const { data } = await api.get('/admin/settings')
    // Tenant info comes from /me endpoint
    const me = await api.get('/me')
    tenant.value = me.data.tenant

    // Get more details from settings
    const settingsData = await api.get('/admin/settings')
    tenant.value.settings = settingsData.data
  } finally {
    loading.value = false
  }
})

const planLabel = computed(() => {
  if (!tenant.value) return '—'
  return 'スタンダード'
})

const statusLabel = computed(() => {
  if (!tenant.value) return '—'
  // Infer from trial info available
  return 'トライアル'
})

const statusBadgeClass = computed(() => {
  return 'bg-blue-100 text-blue-700'
})

const statusDescription = computed(() => {
  return '月額1,000円 + 従量課金'
})

const trialDaysRemaining = computed(() => {
  // This would come from the tenant data; placeholder for now
  return 14
})

const trialProgress = computed(() => {
  if (trialDaysRemaining.value === null) return 0
  const totalDays = 30
  return Math.max(0, Math.min(100, ((totalDays - trialDaysRemaining.value) / totalDays) * 100))
})

function handleUpgrade() {
  // Placeholder for Stripe integration
}
</script>
