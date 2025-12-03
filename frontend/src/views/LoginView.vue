<template>
  <div class="min-h-[calc(100vh-96px)] flex items-center justify-center">
    <div class="w-full max-w-md">
      <div class="bg-white/80 backdrop-blur border border-slate-200 shadow-lg rounded-2xl p-8">
        <div class="mb-6 text-center">
          <h2 class="text-2xl font-semibold text-slate-900">Welcome back</h2>
          <p class="text-sm text-slate-500 mt-1">
            Log ind for at booke og administrere dine aftaler.
          </p>
        </div>

        <form class="space-y-5" @submit.prevent="onSubmit">
          <div class="space-y-1.5">
            <label class="block text-sm font-medium text-slate-700">Email</label>
            <input
                v-model="email"
                type="email"
                class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm outline-none focus:border-slate-900 focus:ring-2 focus:ring-slate-900/10"
                placeholder="you@example.com"
                required
            />
          </div>

          <div class="space-y-1.5">
            <label class="block text-sm font-medium text-slate-700">Password</label>
            <input
                v-model="password"
                type="password"
                class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm outline-none focus:border-slate-900 focus:ring-2 focus:ring-slate-900/10"
                placeholder="••••••••"
                required
            />
          </div>

          <p v-if="error" class="text-sm text-red-600 bg-red-50 border border-red-100 rounded-lg px-3 py-2">
            {{ error }}
          </p>

          <button
              type="submit"
              class="w-full inline-flex items-center justify-center rounded-lg bg-slate-900 px-4 py-2.5 text-sm font-medium text-white shadow-sm hover:bg-slate-800 focus:outline-none focus:ring-2 focus:ring-slate-900 focus:ring-offset-2 disabled:opacity-60 disabled:cursor-not-allowed"
              :disabled="loading"
          >
            <span v-if="!loading">Login</span>
            <span v-else>Logger ind…</span>
          </button>
        </form>

        <div class="mt-6 text-center text-sm text-slate-500">
          <span>Har du ikke en konto?</span>
          <RouterLink
              to="/register"
              class="ml-1 font-medium text-slate-900 hover:underline"
          >
            Opret konto
          </RouterLink>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import { useRouter, RouterLink } from 'vue-router'
import { useAuth } from '../auth'

const email = ref('')
const password = ref('')
const error = ref('')
const loading = ref(false)
const router = useRouter()
const { login } = useAuth()

async function onSubmit() {
  error.value = ''
  loading.value = true
  try {
    await login(email.value, password.value)
    router.push({ name: 'dashboard' })
  } catch {
    error.value = 'Invalid credentials'
  } finally {
    loading.value = false
  }
}
</script>
