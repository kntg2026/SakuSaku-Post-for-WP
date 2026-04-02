import { defineStore } from 'pinia'
import { ref } from 'vue'
import { useApi } from '../composables/useApi'

export const usePostsStore = defineStore('posts', () => {
  const api = useApi()
  const posts = ref([])
  const currentPost = ref(null)
  const pagination = ref({})
  const loading = ref(false)

  async function fetchPosts(params = {}) {
    loading.value = true
    try {
      const { data } = await api.get('/posts', { params })
      posts.value = data.data
      pagination.value = data.meta
    } finally {
      loading.value = false
    }
  }

  async function fetchPost(id) {
    loading.value = true
    try {
      const { data } = await api.get(`/posts/${id}`)
      currentPost.value = data.data
      return data.data
    } finally {
      loading.value = false
    }
  }

  async function submitPost(payload) {
    const { data } = await api.post('/posts', payload)
    return data.data
  }

  async function deletePost(id) {
    await api.delete(`/posts/${id}`)
    posts.value = posts.value.filter(p => p.id !== id)
  }

  async function publishPost(id) {
    const { data } = await api.post(`/posts/${id}/publish`)
    return data.data
  }

  return { posts, currentPost, pagination, loading, fetchPosts, fetchPost, submitPost, deletePost, publishPost }
})
