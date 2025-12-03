import { ref, computed } from 'vue'
import api from './api'

export interface User {
    id: number
    email: string
    roles: string[]
}

const user = ref<User | null>(null)
const token = ref<string | null>(localStorage.getItem('token') || null)
const isAuthenticated = computed(() => !!token.value)

export function useAuth() {
    async function register(email: string, password: string) {
        await api.post('/api/register', { email, password })
    }

    async function login(email: string, password: string) {
        const response = await api.post('/api/login', { email, password })
        const data = response.data as { token: string; id: number; email: string; roles: string[] }
        token.value = data.token
        localStorage.setItem('token', data.token)
        user.value = { id: data.id, email: data.email, roles: data.roles }
    }

    function logout() {
        token.value = null
        user.value = null
        localStorage.removeItem('token')
    }

    async function fetchMe() {
        if (!token.value) {
            user.value = null
            return
        }
        try {
            const response = await api.get('/api/me')
            const data = response.data as User
            user.value = data
        } catch {
            logout()
        }
    }

    return {
        user,
        token,
        isAuthenticated,
        register,
        login,
        logout,
        fetchMe
    }
}
