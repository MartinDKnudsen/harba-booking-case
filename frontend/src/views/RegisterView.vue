<template>
  <div class="max-w-md mx-auto bg-white p-6 rounded shadow">
    <h2 class="text-lg font-semibold mb-4">Register</h2>
    <form class="space-y-4" @submit.prevent="onSubmit">
      <div>
        <label class="block text-sm mb-1">Email</label>
        <input v-model="email" type="email" class="w-full border rounded px-3 py-2" required />
      </div>
      <div>
        <label class="block text-sm mb-1">Password</label>
        <input v-model="password" type="password" class="w-full border rounded px-3 py-2" required />
      </div>
      <p v-if="error" class="text-sm text-red-600">{{ error }}</p>
      <button type="submit" class="w-full bg-slate-900 text-white py-2 rounded">
        Register
      </button>
    </form>
  </div>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuth } from '../auth'

const email = ref('')
const password = ref('')
const error = ref('')
const router = useRouter()
const { register, login } = useAuth()

async function onSubmit() {
  error.value = ''
  try {
    await register(email.value, password.value)
    await login(email.value, password.value)
    router.push({ name: 'dashboard' })
  } catch {
    error.value = 'Registration failed'
  }
}
</script>
