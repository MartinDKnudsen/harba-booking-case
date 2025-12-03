<template>
  <div class="space-y-6">
    <section class="bg-white p-4 rounded shadow space-y-4">
      <h2 class="text-lg font-semibold">Book appointment</h2>

      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
          <h3 class="font-medium mb-2">Services</h3>
          <ul class="space-y-2">
            <li
                v-for="s in services"
                :key="s.id"
                class="flex items-center justify-between"
            >
              <button
                  class="px-3 py-1 rounded border w-full text-left"
                  :class="s.id === selectedServiceId ? 'bg-slate-900 text-white' : ''"
                  @click="selectedServiceId = s.id"
              >
                {{ s.name }} ({{ s.durationMinutes }} min)
              </button>
            </li>
          </ul>
        </div>

        <div>
          <h3 class="font-medium mb-2">Providers</h3>
          <ul class="space-y-2">
            <li
                v-for="p in providers"
                :key="p.id"
            >
              <button
                  class="px-3 py-1 rounded border w-full text-left"
                  :class="p.id === selectedProviderId ? 'bg-slate-900 text-white' : ''"
                  @click="onSelectProvider(p.id)"
              >
                {{ p.name }}
              </button>
            </li>
          </ul>
        </div>
      </div>

      <div class="mt-4 space-y-3">
        <div class="flex flex-wrap items-end gap-3">
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Fra dato</label>
            <input
                v-model="fromDate"
                type="date"
                class="border border-slate-300 rounded px-2 py-1 text-sm outline-none focus:border-slate-900 focus:ring-1 focus:ring-slate-900/20"
            />
          </div>
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Til dato</label>
            <input
                v-model="toDate"
                type="date"
                class="border border-slate-300 rounded px-2 py-1 text-sm outline-none focus:border-slate-900 focus:ring-1 focus:ring-slate-900/20"
            />
          </div>
          <button
              class="px-3 py-1.5 rounded bg-slate-900 text-white text-sm hover:bg-slate-800 disabled:opacity-50"
              @click="onApplyDateFilter"
              :disabled="loadingSlots"
          >
            Opdater tider
          </button>
        </div>

        <div v-if="slots.length">
          <h3 class="font-medium mb-2">Ledige tider</h3>
          <div class="flex flex-wrap gap-2 max-h-64 overflow-auto border rounded p-2">
            <button
                v-for="slot in slots"
                :key="slot"
                class="px-3 py-1 rounded border text-sm"
                :class="slot === selectedSlot ? 'bg-slate-900 text-white' : ''"
                @click="selectedSlot = slot"
            >
              {{ formatSlot(slot) }}
            </button>
          </div>
        </div>
        <p v-else class="text-sm text-slate-600">Ingen ledige tider i valgt interval.</p>
      </div>


      <div class="mt-4 flex items-center gap-3">
        <button
            class="px-4 py-2 bg-emerald-600 text-white rounded disabled:opacity-50"
            :disabled="!selectedProviderId || !selectedServiceId || !selectedSlot || loadingBook"
            @click="onBook"
        >
          Book selected slot
        </button>
        <span v-if="bookMessage" class="text-sm" :class="bookError ? 'text-red-600' : 'text-emerald-700'">
          {{ bookMessage }}
        </span>
      </div>
    </section>

    <section class="bg-white p-4 rounded shadow">
      <h2 class="text-lg font-semibold mb-3">My bookings</h2>
      <div v-if="myBookings.length" class="space-y-2">
        <div
            v-for="b in myBookings"
            :key="b.id"
            class="flex items-center justify-between border rounded px-3 py-2"
        >
          <div>
            <div class="text-sm">Booking #{{ b.id }}</div>
            <div class="text-xs text-slate-600">
              Provider {{ b.providerId }}, Service {{ b.serviceId }}, {{ formatSlot(b.startAt) }}
            </div>
            <div class="text-xs" :class="b.cancelled ? 'text-red-600' : 'text-emerald-600'">
              {{ b.cancelled ? 'Cancelled' : 'Active' }}
            </div>
          </div>
          <button
              v-if="!b.cancelled"
              class="text-sm px-3 py-1 border rounded"
              @click="onCancel(b.id)"
          >
            Cancel
          </button>
        </div>
      </div>
      <p v-else class="text-sm text-slate-600">No bookings yet.</p>
    </section>
  </div>
</template>

<script setup lang="ts">
import { onMounted, ref } from 'vue'
import api from '../api'

interface ServiceItem {
  id: number
  name: string
  durationMinutes: number
}

interface ProviderItem {
  id: number
  name: string
  workingHours: Record<string, unknown>
}

interface BookingItem {
  id: number
  providerId: number
  serviceId: number
  startAt: string
  cancelled: boolean
}

const fromDate = ref<string | null>(null)
const toDate = ref<string | null>(null)
const loadingSlots = ref(false)

const services = ref<ServiceItem[]>([])
const providers = ref<ProviderItem[]>([])
const slots = ref<string[]>([])
const myBookings = ref<BookingItem[]>([])

const selectedServiceId = ref<number | null>(null)
const selectedProviderId = ref<number | null>(null)
const selectedSlot = ref<string | null>(null)

const loadingBook = ref(false)
const bookMessage = ref('')
const bookError = ref(false)

function formatSlot(iso: string) {
  const d = new Date(iso)
  return d.toLocaleString()
}

async function loadServices() {
  const response = await api.get('/api/services')
  services.value = response.data
  if (services.value.length && !selectedServiceId.value) {
    selectedServiceId.value = services.value[0].id
  }
}

async function loadProviders() {
  const response = await api.get('/api/providers')
  providers.value = response.data
  if (providers.value.length && !selectedProviderId.value) {
    selectedProviderId.value = providers.value[0].id
    await loadSlots()
  }
}

async function loadSlots() {
  if (!selectedProviderId.value) {
    slots.value = []
    return
  }

  loadingSlots.value = true
  try {
    const params: Record<string, string> = {}
    if (fromDate.value) params.from = fromDate.value
    if (toDate.value) params.to = toDate.value

    const response = await api.get(`/api/providers/${selectedProviderId.value}/slots`, { params })
    slots.value = response.data.slots
    selectedSlot.value = null
  } finally {
    loadingSlots.value = false
  }
}

async function onApplyDateFilter() {
  await loadSlots()
}
async function loadMyBookings() {
  const response = await api.get('/api/my/bookings')
  myBookings.value = response.data
}

async function onSelectProvider(id: number) {
  selectedProviderId.value = id
  await loadSlots()
}

async function onBook() {
  if (!selectedProviderId.value || !selectedServiceId.value || !selectedSlot.value) {
    return
  }

  loadingBook.value = true
  bookMessage.value = ''
  bookError.value = false

  try {
    await api.post('/api/bookings', {
      provider_id: selectedProviderId.value,
      service_id: selectedServiceId.value,
      start_at: selectedSlot.value
    })
    bookMessage.value = 'Booking created'
    await loadMyBookings()
    await loadSlots()
  } catch (e: any) {
    bookError.value = true
    bookMessage.value = e?.response?.data?.message || 'Booking failed'
  } finally {
    loadingBook.value = false
  }
}

async function onCancel(id: number) {
  await api.post(`/api/bookings/${id}/cancel`)
  await loadMyBookings()
  await loadSlots()
}

onMounted(async () => {
  const today = new Date()
  fromDate.value = today.toISOString().slice(0, 10)
  await loadServices()
  await loadProviders()
  await loadMyBookings()
})
</script>
