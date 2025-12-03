import { createRouter, createWebHistory } from 'vue-router'
import LoginView from './views/LoginView.vue'
import RegisterView from './views/RegisterView.vue'
import DashboardView from './views/DashboardView.vue'
import AdminBookingsView from './views/AdminBookingsView.vue'
import { useAuth } from './auth'

const routes = [
    { path: '/', redirect: '/dashboard' },
    { path: '/login', name: 'login', component: LoginView },
    { path: '/register', name: 'register', component: RegisterView },
    {
        path: '/dashboard',
        name: 'dashboard',
        component: DashboardView,
        meta: { requiresAuth: true }
    },
    {
        path: '/admin/bookings',
        name: 'admin-bookings',
        component: AdminBookingsView,
        meta: { requiresAuth: true }
    }
]

const router = createRouter({
    history: createWebHistory(),
    routes
})

router.beforeEach(async (to, from, next) => {
    const { isAuthenticated, fetchMe } = useAuth()
    if (!isAuthenticated.value && localStorage.getItem('token')) {
        await fetchMe()
    }

    if (to.meta.requiresAuth && !isAuthenticated.value) {
        next({ name: 'login' })
        return
    }

    next()
})

export default router
