<template>
  <div class="min-h-screen bg-slate-100 text-slate-900">
    <header class="bg-white shadow mb-6">
      <div class="max-w-4xl mx-auto px-4 py-4 flex items-center justify-between">
        <h1 class="text-xl font-semibold">Harba Booking</h1>
        <nav class="flex items-center gap-4">
          <template v-if="isAuthenticated">
            <RouterLink to="/dashboard" class="hover:underline">Dashboard</RouterLink>
            <RouterLink
                v-if="user && user.roles && user.roles.includes('ROLE_ADMIN')"
                to="/admin/bookings"
                class="hover:underline"
            >
              Admin
            </RouterLink>
            <button class="text-sm px-3 py-1 border rounded" @click="onLogout">Logout</button>
          </template>
          <template v-else>
            <RouterLink to="/login" class="hover:underline">Login</RouterLink>
            <RouterLink to="/register" class="hover:underline">Register</RouterLink>
          </template>
        </nav>
      </div>
    </header>
    <main class="max-w-4xl mx-auto px-4 pb-10">
      <RouterView />
    </main>
  </div>
</template>

<script setup lang="ts">
import { RouterLink, RouterView, useRouter } from 'vue-router'
import { onMounted } from 'vue'
import { useAuth } from './auth'

const { isAuthenticated, logout, fetchMe, user } = useAuth()
const router = useRouter()

onMounted(async () => {
  if (localStorage.getItem('token')) {
    await fetchMe()
  }
})

function onLogout() {
  logout()
  router.push({ name: 'login' })
}
</script>


<style scoped>
</style>
