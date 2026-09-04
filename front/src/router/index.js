import { createRouter, createWebHistory } from 'vue-router'
import { useAuthAdminStore } from '../stores/authAdmin'
import LoginView from '../views/LoginView.vue'
import TenantsView from '../views/TenantsView.vue'
import EccEquipesView from '../views/EccEquipesView.vue'
import EccCasaisView from '../views/EccCasaisView.vue'
import AdminLayout from '../layouts/AdminLayout.vue'
import AuthLayout from '../layouts/AuthLayout.vue'

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes: [
    {
      path: '/',
      component: AuthLayout,
      children: [
        {
          path: '',
          name: 'login',
          component: LoginView,
        },
      ],
    },
    {
      path: '/',
      component: AdminLayout,
      meta: { requiresAuth: true },
      children: [
        {
          path: 'tenants',
          name: 'tenants',
          component: TenantsView,
        },
        {
          path: 'ecc/equipes',
          name: 'ecc-equipes',
          component: EccEquipesView,
        },
        {
          path: 'ecc/casais',
          name: 'ecc-casais',
          component: EccCasaisView,
        },
        {
          path: 'dashboard',
          redirect: '/tenants',
        },
      ],
    },
  ],
})

router.beforeEach(async (to, from, next) => {
  const authStore = useAuthAdminStore()
  const isAuthRoute = to.path === '/'

  if (isAuthRoute) {
    const ok = await authStore.checkAuth()
    next(ok ? '/tenants' : undefined)
    return
  }

  if (to.matched.some((r) => r.meta.requiresAuth)) {
    const ok = await authStore.checkAuth()
    next(ok ? undefined : '/')
    return
  }

  next()
})

export default router
