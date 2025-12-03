<template>
  <div class="bg-white p-4 rounded shadow">
    <h2 class="text-lg font-semibold mb-3">All bookings (admin)</h2>
    <p v-if="error" class="text-sm text-red-600 mb-2">{{ error }}</p>
    <div v-if="bookings.length" class="space-y-2">
      <div
          v-for="b in bookings"
          :key="b.id"
          class="flex items-center justify-between border rounded px-3 py-2"
      >
        <div>
          <div class="text-sm">Booking #{{ b.id }}</div>
          <div class="text-xs text-slate-600">
            User {{ b.userId }}, Provider {{ b.providerId }}, Service {{ b.serviceId }}
          </div>
          <div class="text-xs text-slate-600">
            {{ formatSlot(b.startAt) }}
          </div>
          <div class="text-xs" :class="b.cancelled ? 'text-red-600' : 'text-emerald-600'">
            {{ b.cancelled ? 'Cancelled' : 'Active' }}
          </div>
        </div>
      </div>
    </div>
    <p v-else class="text-sm text-slate-600">No bookings to show.</p>
  </div>
</template>

<script setup lang="ts">
import { onMounted, ref } from 'vue'
import api from '../api'

interface AdminBookingItem {
  id: number
  userId: number
  providerId: number
  serviceId: number
  startAt: string
  cancelled: boolean
}

const bookings = ref<AdminBookingItem[]>([])
const error = ref('')

function formatSlot(iso: string) {
  const d = new Date(iso)
  return d.toLocaleString()
}

async function load() {
  error.value = ''
  try {
    const response = await api.get('/api/admin/bookings')
    bookings.value = response.data
  } catch (e: any) {
    error.value = e?.response?.data?.message || 'Could not load bookings'
  }
}

onMounted(load)
</script>
