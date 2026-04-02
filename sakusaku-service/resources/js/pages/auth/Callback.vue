<template>
  <div class="min-h-screen flex items-center justify-center">
    <p class="text-gray-500">ログイン処理中...</p>
  </div>
</template>

<script setup>
import { onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '../../stores/auth'

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()

onMounted(() => {
  const token = route.query.token
  const userJson = route.query.user

  if (token && userJson) {
    try {
      const user = JSON.parse(userJson)
      auth.setAuth(token, user)
      router.replace(user.role === 'admin' ? '/admin' : '/submit')
    } catch {
      router.replace('/login?error=auth_failed')
    }
  } else {
    router.replace('/login?error=no_token')
  }
})
</script>
