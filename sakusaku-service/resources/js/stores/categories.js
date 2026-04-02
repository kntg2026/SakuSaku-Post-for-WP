import { defineStore } from 'pinia'
import { ref } from 'vue'
import { useApi } from '../composables/useApi'

export const useCategoriesStore = defineStore('categories', () => {
  const api = useApi()
  const categories = ref([])

  async function fetchCategories() {
    const { data } = await api.get('/categories')
    categories.value = data.data
  }

  return { categories, fetchCategories }
})
