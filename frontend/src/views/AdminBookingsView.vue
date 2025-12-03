<template>
  <div class="bg-white p-4 rounded shadow">
    <h2 class="text-lg font-semibold mb-3">All bookings (admin)</h2>
    <p v-if="error" class="text-sm text-red-600 mb-2">{{ error }}</p>
    <div v-if="bookings.length" class="space-y-2">
      <div v-if="groupedBookings.length">
        <div
            v-for="group in groupedBookings"
            :key="group.email"
            class="mb-6 border rounded-lg bg-white/80 backdrop-blur px-3 py-2"
        >
          <div class="border-b pb-2 mb-2">
            <h3 class="text-sm font-semibold text-slate-900">
              {{ group.email }}
            </h3>
            <p class="text-xs text-slate-500">
              {{ group.items.length }} booking{{ group.items.length === 1 ? '' : 's' }}
            </p>
          </div>

          <div class="space-y-2">
            <div
                v-for="b in group.items"
                :key="b.id"
                class="flex items-center justify-between border rounded px-3 py-2"
            >
              <div>
                <div class="text-sm font-medium">
                  Booking #{{ b.id }}
                </div>
                <div class="text-xs text-slate-600">
                  Provider {{ b.providerId }}, Service {{ b.serviceId }}
                </div>
                <div class="text-xs text-slate-600">
                  {{ formatSlot(b.startAt) }}
                </div>
                <div class="mt-1 flex flex-wrap gap-2">
            <span
                class="inline-flex items-center rounded-full px-2 py-0.5 text-[11px] font-medium"
                :class="b.cancelled ? 'bg-orange-100 text-orange-700' : 'bg-emerald-100 text-emerald-700'"
            >
              {{ b.cancelled ? 'Cancelled' : 'Active' }}
            </span>
                  <span
                      v-if="b.deleted"
                      class="inline-flex items-center rounded-full px-2 py-0.5 text-[11px] font-medium bg-red-100 text-red-700"
                  >
              Deleted
            </span>
                </div>
              </div>

              <div class="flex flex-col gap-2">
                <button
                    v-if="!b.deleted && !b.cancelled"
                    class="text-xs px-3 py-1 border rounded hover:bg-slate-50"
                    @click="onCancel(b.id)"
                >
                  Cancel
                </button>
                <button
                    v-if="!b.deleted"
                    class="text-xs px-3 py-1 border rounded text-red-700 hover:bg-red-50 border-red-200"
                    @click="onDelete(b.id)"
                >
                  Delete
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>
      <p v-else class="text-sm text-slate-500">
        No bookings to show.
      </p>

    </div>
    <p v-else class="text-sm text-slate-600">No bookings to show.</p>
  </div>
</template>

<script setup lang="ts">
import { onMounted, ref, computed } from 'vue'
import api from '../api'

interface AdminBookingItem {
  id: number
  userId: number | null
  userEmail: string | null
  providerId: number | null
  serviceId: number | null
  startAt: string
  cancelled: boolean
  deleted: boolean
}

interface AdminBookingGroup {
  email: string
  items: AdminBookingItem[]
}

const bookings = ref<AdminBookingItem[]>([])

const groupedBookings = computed<AdminBookingGroup[]>(() => {
  const groups: Record<string, AdminBookingItem[]> = {}

  for (const b of bookings.value) {
    const key = b.userEmail || `User #${b.userId ?? 0}`
    if (!groups[key]) {
      groups[key] = []
    }
    groups[key].push(b)
  }

  return Object.entries(groups).map(([email, items]) => ({
    email,
    items,
  }))
})

const error = ref('')

function formatSlot(iso: string) {
  const d = new Date(iso)
  return d.toLocaleString()
}

async function onDelete(id: number) {
  try {
    await api.post(`/api/admin/bookings/${id}/delete`)
    await load()
  } catch (e: any) {
    error.value = e?.response?.data?.message || 'Could not delete booking'
  }
}

async function onCancel(id: number) {
  try {
    await api.post(`/api/bookings/${id}/cancel`)
    await load()
  } catch (e: any) {
    error.value = e?.response?.data?.message || 'Could not cancel booking'
  }
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
