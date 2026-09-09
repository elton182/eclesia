import { createRouter, createWebHistory } from 'vue-router'
import { useAuthAdminStore } from '../stores/authAdmin'
import { useAuthStore } from '../stores/auth'
import { useTenantStore } from '../stores/tenant'
import LandingView from '../views/LandingView.vue'
import TenantLoginView from '../views/TenantLoginView.vue'
import LoginView from '../views/LoginView.vue'
import WelcomeView from '../views/WelcomeView.vue'
import TenantsView from '../views/TenantsView.vue'
import UsersView from '../views/UsersView.vue'
import EccEquipesView from '../views/EccEquipesView.vue'
import EccCasaisView from '../views/EccCasaisView.vue'
import AdminLayout from '../layouts/AdminLayout.vue'
import AuthLayout from '../layouts/AuthLayout.vue'
import PublicLayout from '../layouts/PublicLayout.vue'

export const LOGIN_TENANT_PATH = '/entrar'
export const HOME_PATH = '/inicio'

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes: [
    {
      path: '/',
      component: PublicLayout,
      children: [
        {
          path: '',
          name: 'landing',
          component: LandingView,
        },
      ],
    },
    {
      path: '/',
      component: AuthLayout,
      children: [
        {
          path: 'entrar',
          name: 'tenant-login',
          component: TenantLoginView,
        },
        {
          path: 'admin/login',
          name: 'admin-login',
          component: LoginView,
        },
      ],
    },
    {
      path: '/admin',
      component: AdminLayout,
      meta: { requiresAuthAdmin: true },
      children: [
        {
          path: 'tenants',
          name: 'tenants',
          component: TenantsView,
        },
        {
          path: '',
          redirect: '/admin/tenants',
        },
      ],
    },
    {
      path: '/',
      component: AdminLayout,
      meta: { requiresAuthTenantOrAdmin: true },
      children: [
        {
          path: 'inicio',
          name: 'inicio',
          component: WelcomeView,
        },
        {
          path: 'usuarios',
          name: 'usuarios',
          component: UsersView,
        },
      ],
    },
    {
      path: '/',
      component: AdminLayout,
      meta: { requiresAuthTenantOrAdmin: true },
      children: [
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
      ],
    },
  ],
})

router.beforeEach(async (to, from, next) => {
  const authAdmin = useAuthAdminStore()
  const authTenant = useAuthStore()
  const tenantStore = useTenantStore()

  if (to.name === 'tenant-login') {
    const ok = await authTenant.checkAuth()
    next(ok ? HOME_PATH : undefined)
    return
  }

  if (to.name === 'admin-login') {
    const ok = await authAdmin.checkAuth()
    next(ok ? '/admin/tenants' : undefined)
    return
  }

  if (to.matched.some((r) => r.meta.requiresAuthTenantOrAdmin)) {
    // Preferir sessão de usuário do tenant quando houver token
    const hasTenantSession =
      !!localStorage.getItem('tenant_token') && !!tenantStore.slug

    if (hasTenantSession) {
      if (authTenant.isAuthenticated && authTenant.user) {
        next()
        return
      }
      const tenantOk = await authTenant.checkAuth()
      next(tenantOk ? undefined : LOGIN_TENANT_PATH)
      return
    }

    const adminOk = await authAdmin.checkAuth()
    if (adminOk) {
      if (!tenantStore.slug) {
        next('/admin/tenants')
        return
      }
      next()
      return
    }

    next(LOGIN_TENANT_PATH)
    return
  }

  if (to.matched.some((r) => r.meta.requiresAuthAdmin)) {
    const ok = await authAdmin.checkAuth()
    next(ok ? undefined : '/admin/login')
    return
  }

  if (to.matched.some((r) => r.meta.requiresAuthTenant)) {
    const ok = await authTenant.checkAuth()
    next(ok ? undefined : LOGIN_TENANT_PATH)
    return
  }

  next()
})

export default router
