import { createRouter, createWebHistory } from 'vue-router'
import { h } from 'vue'
import { useAuthAdminStore } from '../stores/authAdmin'
import { useAuthStore } from '../stores/auth'
import { useTenantStore } from '../stores/tenant'
import { useBrandingStore } from '../stores/branding'
import LandingView from '../views/LandingView.vue'
import TenantLoginView from '../views/TenantLoginView.vue'
import LoginView from '../views/LoginView.vue'
import WelcomeView from '../views/WelcomeView.vue'
import TenantsView from '../views/TenantsView.vue'
import UsersView from '../views/UsersView.vue'
import AuditoriaView from '../views/AuditoriaView.vue'
import IgrejasView from '../views/IgrejasView.vue'
import EccCasaisView from '../views/EccCasaisView.vue'
import EccCasalDetailView from '../views/EccCasalDetailView.vue'
import EccEventosView from '../views/EccEventosView.vue'
import EccEventoDetailView from '../views/EccEventoDetailView.vue'
import EventosView from '../views/EventosView.vue'
import EventoDetailView from '../views/EventoDetailView.vue'
import CalendarioMensaisView from '../views/CalendarioMensaisView.vue'
import CalendarioMensalDetailView from '../views/CalendarioMensalDetailView.vue'
import CalendarioLocaisView from '../views/CalendarioLocaisView.vue'
import CalendarioTiposView from '../views/CalendarioTiposView.vue'
import CalendarioColetaPublicView from '../views/CalendarioColetaPublicView.vue'
import SiteAdminView from '../views/SiteAdminView.vue'
import ConfiguracoesMarcaView from '../views/ConfiguracoesMarcaView.vue'
import PublicSiteView from '../views/site/PublicSiteView.vue'
import AdminLayout from '../layouts/AdminLayout.vue'
import AuthLayout from '../layouts/AuthLayout.vue'
import PublicLayout from '../layouts/PublicLayout.vue'
import LauncherLayout from '../layouts/LauncherLayout.vue'
import ModuleLayout from '../layouts/ModuleLayout.vue'

export const LOGIN_TENANT_PATH = '/entrar'
export const HOME_PATH = '/inicio'

const ModuleLayoutEccWrapper = {
  name: 'ModuleLayoutEcc',
  setup() {
    return () => h(ModuleLayout, { moduleKey: 'ecc' })
  },
}

const ModuleLayoutSiteWrapper = {
  name: 'ModuleLayoutSite',
  setup() {
    return () => h(ModuleLayout, { moduleKey: 'site' })
  },
}

const ModuleLayoutCalendarioWrapper = {
  name: 'ModuleLayoutCalendario',
  setup() {
    return () => h(ModuleLayout, { moduleKey: 'calendario' })
  },
}

const ModuleLayoutEventosWrapper = {
  name: 'ModuleLayoutEventos',
  setup() {
    return () => h(ModuleLayout, { moduleKey: 'eventos' })
  },
}

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
      path: '/site/:tenantSlug',
      name: 'public-site',
      component: PublicSiteView,
      meta: { publicSite: true },
    },
    {
      path: '/site/:tenantSlug/:pageSlug',
      name: 'public-site-page',
      component: PublicSiteView,
      meta: { publicSite: true },
    },
    {
      path: '/calendario/coleta/:token',
      name: 'calendario-coleta-publica',
      component: CalendarioColetaPublicView,
      meta: { publicSite: true },
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
      component: LauncherLayout,
      meta: { requiresAuthTenantOrAdmin: true },
      children: [
        {
          path: 'inicio',
          name: 'inicio',
          component: WelcomeView,
        },
      ],
    },
    {
      path: '/',
      component: AdminLayout,
      meta: { requiresAuthTenantOrAdmin: true },
      children: [
        {
          path: 'igrejas',
          name: 'igrejas',
          component: IgrejasView,
        },
        {
          path: 'usuarios',
          name: 'usuarios',
          component: UsersView,
        },
        {
          path: 'auditoria',
          name: 'auditoria',
          component: AuditoriaView,
        },
        {
          path: 'configuracoes/marca',
          name: 'configuracoes-marca',
          component: ConfiguracoesMarcaView,
        },
      ],
    },
    {
      path: '/',
      component: ModuleLayoutSiteWrapper,
      meta: { requiresAuthTenantOrAdmin: true },
      children: [
        {
          path: 'site',
          name: 'site-admin',
          component: SiteAdminView,
        },
      ],
    },
    {
      path: '/',
      component: ModuleLayoutEccWrapper,
      meta: { requiresAuthTenantOrAdmin: true },
      children: [
        {
          path: 'ecc/equipes',
          redirect: { name: 'ecc-casais' },
        },
        {
          path: 'ecc/casais/:id',
          name: 'ecc-casal-detail',
          component: EccCasalDetailView,
        },
        {
          path: 'ecc/casais',
          name: 'ecc-casais',
          component: EccCasaisView,
        },
        {
          path: 'ecc/eventos',
          name: 'ecc-eventos',
          component: EccEventosView,
        },
        {
          path: 'ecc/eventos/:id',
          name: 'ecc-evento-detail',
          component: EccEventoDetailView,
        },
      ],
    },
    {
      path: '/',
      component: ModuleLayoutCalendarioWrapper,
      meta: { requiresAuthTenantOrAdmin: true },
      children: [
        {
          path: 'calendario',
          name: 'calendario-mensais',
          component: CalendarioMensaisView,
        },
        {
          path: 'calendario/locais',
          name: 'calendario-locais',
          component: CalendarioLocaisView,
        },
        {
          path: 'calendario/tipos',
          name: 'calendario-tipos',
          component: CalendarioTiposView,
        },
        {
          path: 'calendario/:id',
          name: 'calendario-mensal-detail',
          component: CalendarioMensalDetailView,
        },
      ],
    },
    {
      path: '/',
      component: ModuleLayoutEventosWrapper,
      meta: { requiresAuthTenantOrAdmin: true },
      children: [
        {
          path: 'eventos',
          name: 'eventos',
          component: EventosView,
        },
        {
          path: 'eventos/:id',
          name: 'eventos-detail',
          component: EventoDetailView,
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
    const branding = useBrandingStore()
    const hasTenantSession =
      !!localStorage.getItem('tenant_token') && !!tenantStore.slug

    if (hasTenantSession) {
      if (authTenant.isAuthenticated && authTenant.user) {
        // Sessão já hidratada (ex.: pós-login SPA): ainda assim garantir cores
        if (tenantStore.slug) {
          await branding.ensureLoaded()
        }
        next()
        return
      }
      const tenantOk = await authTenant.checkAuth()
      if (tenantOk && tenantStore.slug) {
        await branding.ensureLoaded()
      }
      next(tenantOk ? undefined : LOGIN_TENANT_PATH)
      return
    }

    const adminOk = await authAdmin.checkAuth()
    if (adminOk) {
      if (!tenantStore.slug) {
        next('/admin/tenants')
        return
      }
      await branding.ensureLoaded()
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
