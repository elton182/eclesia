<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useAuthAdminStore } from '@/stores/authAdmin'
import { useTenantStore } from '@/stores/tenant'
import { useIgrejaStore } from '@/stores/igreja'
import { useBrandingStore } from '@/stores/branding'
import { userHasPermission, canSeeEccCasaisNav, canSeeEccEventosNav, canSeeCalendarioNav } from '@/utils/userRoles'
import api from '@/services/api'
import PwaInstallNavButton from '@/components/base/PwaInstallNavButton.vue'

const router = useRouter()
const authTenant = useAuthStore()
const authAdmin = useAuthAdminStore()
const tenantStore = useTenantStore()
const igrejaStore = useIgrejaStore()
const branding = useBrandingStore()

const eccCounts = ref({ equipes: null, casais: null })
const sitePublished = ref(null)
const userMenuOpen = ref(false)

const displayName = computed(
  () => authTenant.user?.name || authAdmin.user?.name || 'bem-vindo(a)',
)

const firstName = computed(() => {
  const n = displayName.value
  if (!n || n === 'bem-vindo(a)') return 'olá'
  return n.split(/\s+/)[0]
})

const initials = computed(() => {
  const n = displayName.value
  if (!n || n === 'bem-vindo(a)') return 'U'
  const parts = n.trim().split(/\s+/)
  if (parts.length === 1) return parts[0].slice(0, 2).toUpperCase()
  return (parts[0][0] + parts[parts.length - 1][0]).toUpperCase()
})

const orgName = computed(() => tenantStore.name || 'Sua organização')
const orgSlug = computed(() => tenantStore.slug || '')

const brandLogo = computed(() => branding.logoUrl || null)
const brandTitle = computed(() => (branding.logoUrl ? orgName.value : 'Eclésia'))

const roleLabel = computed(() => {
  const roles = authTenant.user?.roles
  if (!Array.isArray(roles) || !roles.length) return 'Acesso'
  const name = roles[0]?.name || roles[0]
  const map = {
    'admin-igreja': 'Administração',
    'gestor-site': 'Gestor do site',
    'cadastros-equipes': 'Cadastros',
    'cadastros-casais': 'Cadastros',
    'cadastros-usuarios': 'Cadastros',
    'cadastros-eventos': 'Cadastros',
    'lider-equipe': 'Liderança',
  }
  return map[name] || String(name)
})

const greeting = computed(() => {
  const h = new Date().getHours()
  if (h < 12) return 'Bom dia'
  if (h < 18) return 'Boa tarde'
  return 'Boa noite'
})

const isPlatformAdmin = computed(
  () => authAdmin.isAuthenticated && !authTenant.isAuthenticated,
)

const can = (permission) =>
  userHasPermission(authTenant.user, permission, {
    isSuperAdmin: isPlatformAdmin.value,
  })

const comunidadeLabel = computed(() => {
  if (igrejaStore.current?.nome) return igrejaStore.current.nome
  const n = igrejaStore.igrejas?.length || 0
  if (n > 1) return `todas as comunidades`
  if (n === 1) return igrejaStore.igrejas[0].nome
  return 'comunidades'
})

const moduleAccessCount = computed(() => {
  let n = 0
  if (canSeeEccCasaisNav(authTenant.user, { isSuperAdmin: isPlatformAdmin.value })) n++
  if (can('telas.site') || isPlatformAdmin.value) n++
  if (showCalendario.value) n++
  if (showEventos.value) n++
  return n
})

const eccMeta = computed(() => {
  const e = eccCounts.value.equipes
  const c = eccCounts.value.casais
  if (e == null && c == null) return 'Equipes e casais do movimento'
  const parts = []
  if (e != null) parts.push(`${e} equipes`)
  if (c != null) parts.push(`${c} casais`)
  return parts.join(' · ')
})

const siteMeta = computed(() => {
  if (!orgSlug.value) return 'Página pública, comunicados e horários.'
  if (sitePublished.value === true) return `/site/${orgSlug.value}`
  if (sitePublished.value === false) return 'rascunho'
  return `/site/${orgSlug.value}`
})

const showEcc = computed(() =>
  canSeeEccCasaisNav(authTenant.user, { isSuperAdmin: isPlatformAdmin.value }),
)
const showCalendario = computed(() =>
  canSeeCalendarioNav(authTenant.user, { isSuperAdmin: isPlatformAdmin.value }),
)
const showEventos = computed(() =>
  canSeeEccEventosNav(authTenant.user, { isSuperAdmin: isPlatformAdmin.value }),
)
const showSite = computed(() => can('telas.site') || isPlatformAdmin.value)
const showIgrejas = computed(() => can('telas.igrejas') || isPlatformAdmin.value)
const showUsuarios = computed(() => can('telas.usuarios') || isPlatformAdmin.value)
const showAuditoria = computed(() => can('telas.auditoria') || isPlatformAdmin.value)
const showMarca = computed(() => {
  if (isPlatformAdmin.value) return true
  const roles = (authTenant.user?.roles || []).map((r) => r.name)
  return roles.includes('admin-tenant')
})

async function loadCounts() {
  if (!tenantStore.slug) return
  try {
    if (!igrejaStore.igrejas.length) await igrejaStore.load()
  } catch {
    /* ignore */
  }
  if (showEcc.value) {
    try {
      const [eq, ca] = await Promise.all([
        api.get('/ecc/equipes'),
        api.get('/ecc/casais'),
      ])
      const eqList = eq.data?.data || eq.data || []
      const caList = ca.data?.data || ca.data || []
      eccCounts.value = {
        equipes: Array.isArray(eqList) ? eqList.length : null,
        casais: Array.isArray(caList) ? caList.length : null,
      }
    } catch {
      /* ignore */
    }
  }
  if (showSite.value) {
    try {
      const { data } = await api.get('/site/settings')
      sitePublished.value = !!(data?.publicado ?? data?.data?.publicado)
    } catch {
      /* ignore */
    }
  }
}

onMounted(async () => {
  if (tenantStore.slug) {
    await branding.ensureLoaded()
  }
  await loadCounts()
})

async function logout() {
  if (isPlatformAdmin.value) {
    tenantStore.clear()
    igrejaStore.reset()
    branding.reset()
    await authAdmin.logout()
    router.push('/admin/login')
    return
  }
  await authTenant.logout()
  router.push('/entrar')
}

function go(path) {
  router.push(path)
}
</script>

<template>
  <div class="min-h-screen" style="background: var(--color-bg)" data-testid="welcome-page">
    <!-- Desktop top bar (1c) -->
    <header
      class="hidden md:flex items-center justify-between px-[26px] h-14"
      style="background: var(--color-primary); color: var(--color-on-primary)"
      data-testid="launcher-topbar"
    >
      <div class="flex items-center gap-[22px] min-w-0">
        <div class="flex items-center gap-2.5 shrink-0">
          <img
            v-if="brandLogo"
            :src="brandLogo"
            :alt="brandTitle"
            class="w-9 h-9 rounded-md object-cover bg-white/90"
            data-testid="launcher-brand-logo"
          />
          <div
            v-else
            class="w-6 h-6 rounded-full border flex items-center justify-center font-serif text-[12px] font-medium"
            style="border-color: var(--color-accent); color: var(--color-on-primary)"
          >
            E
          </div>
          <span class="font-serif text-[15px] font-medium" style="color: var(--color-on-primary)">
            {{ brandTitle }}
          </span>
        </div>
        <div class="w-px h-[22px]" style="background: color-mix(in srgb, var(--color-on-primary) 20%, transparent)" />
        <button
          type="button"
          class="flex items-center gap-2 px-2.5 py-1.5 rounded-md min-w-0"
          style="background: color-mix(in srgb, var(--color-on-primary) 9%, transparent)"
        >
          <span class="text-[13px] font-medium truncate" style="color: var(--color-on-primary)">{{ orgName }}</span>
          <span class="text-[11px] truncate hidden lg:inline" style="color: color-mix(in srgb, var(--color-on-primary) 60%, transparent)">
            {{ comunidadeLabel }}
          </span>
          <span class="text-[10px]" style="color: color-mix(in srgb, var(--color-on-primary) 60%, transparent)">▾</span>
        </button>
      </div>
      <div class="flex items-center gap-2.5 shrink-0 relative">
        <PwaInstallNavButton labeled dark />
        <span class="text-[12.5px]" style="color: color-mix(in srgb, var(--color-on-primary) 75%, transparent)">
          {{ displayName }} · {{ roleLabel }}
        </span>
        <button
          type="button"
          class="w-[30px] h-[30px] rounded-full flex items-center justify-center text-[12.5px] font-medium"
          style="background: var(--color-accent); color: var(--color-primary)"
          data-testid="launcher-avatar"
          @click="userMenuOpen = !userMenuOpen"
        >
          {{ initials }}
        </button>
        <div
          v-if="userMenuOpen"
          class="absolute right-0 top-10 rounded-lg py-2 min-w-[160px] z-20 shadow-lg"
          style="background: var(--color-surface); border: 1px solid var(--color-line)"
        >
          <button
            type="button"
            class="w-full text-left px-4 py-2 text-[13px]"
            style="color: var(--color-ink)"
            @click="logout"
          >
            Sair
          </button>
        </div>
      </div>
    </header>

    <!-- Mobile header (1d) -->
    <header
      class="md:hidden px-[18px] pt-4 pb-[18px]"
      style="background: var(--color-primary); color: var(--color-on-primary)"
    >
      <div class="flex items-center justify-between mb-4">
        <div class="flex items-center gap-2">
          <img
            v-if="brandLogo"
            :src="brandLogo"
            :alt="brandTitle"
            class="w-8 h-8 rounded-md object-cover bg-white/90"
            data-testid="launcher-brand-logo-mobile"
          />
          <div
            v-else
            class="w-[22px] h-[22px] rounded-full border flex items-center justify-center font-serif text-[11px] font-medium"
            style="border-color: var(--color-accent); color: var(--color-on-primary)"
          >
            E
          </div>
          <span class="font-serif text-[14px] font-medium" style="color: var(--color-on-primary)">
            {{ brandTitle }}
          </span>
        </div>
        <div class="flex items-center gap-2">
          <PwaInstallNavButton dark />
          <button
            type="button"
            class="w-7 h-7 rounded-full flex items-center justify-center text-[11.5px] font-medium"
            style="background: var(--color-accent); color: var(--color-primary)"
            @click="logout"
          >
            {{ initials }}
          </button>
        </div>
      </div>
      <div class="font-serif text-[22px] leading-snug mb-3.5" style="color: var(--color-on-primary)">
        Olá, {{ firstName }}
      </div>
      <div
        class="flex items-center gap-2.5 rounded-[9px] px-3 py-2.5"
        style="background: color-mix(in srgb, var(--color-on-primary) 10%, transparent)"
      >
        <div
          class="w-[30px] h-[30px] rounded-md flex items-center justify-center font-serif text-[13px] font-medium shrink-0"
          style="background: var(--color-accent); color: var(--color-primary)"
        >
          {{ (orgName || 'P')[0] }}
        </div>
        <div class="flex-1 min-w-0">
          <div class="text-[13px] font-medium truncate" style="color: var(--color-on-primary)">{{ orgName }}</div>
          <div class="text-[11px] truncate" style="color: color-mix(in srgb, var(--color-on-primary) 60%, transparent)">
            {{ comunidadeLabel }}
          </div>
        </div>
        <span class="text-[11px]" style="color: color-mix(in srgb, var(--color-on-primary) 70%, transparent)">▾</span>
      </div>
    </header>

    <!-- Desktop body (1c) -->
    <div class="hidden md:block px-10 py-[38px] pb-[42px]">
      <p class="page-eyebrow mb-2.5">{{ greeting }}</p>
      <h1 class="font-serif font-normal text-[30px] leading-tight mb-1.5" style="color: var(--color-ink)">
        Onde você quer trabalhar hoje, {{ firstName }}?
      </h1>
      <p class="text-[14px] leading-relaxed mb-[30px]" style="color: var(--color-muted)">
        Você tem acesso a {{ moduleAccessCount || 'seus' }} módulos nesta paróquia.
      </p>

      <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-[18px] w-full">
        <button
          v-if="showEcc"
          type="button"
          class="text-left rounded-xl p-[22px] flex flex-col gap-3 cursor-pointer transition-shadow"
          style="background: var(--color-surface); border: 1px solid var(--color-line)"
          data-testid="launcher-card-ecc"
          @click="go('/ecc/casais')"
          @mouseenter="($event.currentTarget.style.borderColor = 'var(--color-primary-hover)')"
          @mouseleave="($event.currentTarget.style.borderColor = 'var(--color-line)')"
        >
          <div class="flex items-start justify-between">
            <div
              class="w-[38px] h-[38px] rounded-[9px] flex items-center justify-center font-serif text-[17px] font-medium"
              style="background: var(--color-primary-soft); color: var(--color-on-primary)"
            >
              C
            </div>
            <span
              v-if="eccCounts.equipes != null"
              class="text-[11px] font-medium px-2 py-1 rounded-full"
              style="color: var(--color-accent-dark); background: var(--color-accent-soft)"
            >
              ativo
            </span>
          </div>
          <div>
            <div class="font-serif text-[17px] font-medium" style="color: var(--color-ink)">ECC</div>
            <div class="text-[12.5px] leading-relaxed mt-1" style="color: var(--color-muted)">
              Equipes, casais e encontros do movimento.
            </div>
          </div>
          <div class="mt-auto flex gap-3.5 text-[12px]" style="color: var(--color-muted)">
            <span>{{ eccMeta }}</span>
          </div>
        </button>


        <button
          v-if="showCalendario"
          type="button"
          class="text-left rounded-xl p-[22px] flex flex-col gap-3 cursor-pointer transition-shadow"
          style="background: var(--color-surface); border: 1px solid var(--color-line)"
          data-testid="launcher-card-calendario"
          @click="go('/calendario')"
          @mouseenter="($event.currentTarget.style.borderColor = 'var(--color-primary-hover)')"
          @mouseleave="($event.currentTarget.style.borderColor = 'var(--color-line)')"
        >
          <div class="flex items-start justify-between">
            <div
              class="w-[38px] h-[38px] rounded-[9px] flex items-center justify-center font-serif text-[17px] font-medium"
              style="background: var(--color-primary); color: var(--color-on-primary)"
            >
              C
            </div>
            <span
              class="text-[11px] font-medium px-2 py-1 rounded-full"
              style="color: var(--color-accent-dark); background: var(--color-accent-soft)"
            >
              ativo
            </span>
          </div>
          <div>
            <div class="font-serif text-[17px] font-medium" style="color: var(--color-ink)">Calendário</div>
            <div class="text-[12.5px] leading-relaxed mt-1" style="color: var(--color-muted)">
              Calendário oficial mensal, coleta e PDF.
            </div>
          </div>
          <div class="mt-auto flex gap-3.5 text-[12px]" style="color: var(--color-muted)">
            <span>Missas, festas e casamentos</span>
          </div>
        </button>

        <button
          v-if="showEventos"
          type="button"
          class="text-left rounded-xl p-[22px] flex flex-col gap-3 cursor-pointer transition-shadow"
          style="background: var(--color-surface); border: 1px solid var(--color-line)"
          data-testid="launcher-card-eventos"
          @click="go('/eventos')"
          @mouseenter="($event.currentTarget.style.borderColor = 'var(--color-primary-hover)')"
          @mouseleave="($event.currentTarget.style.borderColor = 'var(--color-line)')"
        >
          <div class="flex items-start justify-between">
            <div
              class="w-[38px] h-[38px] rounded-[9px] flex items-center justify-center font-serif text-[17px] font-medium"
              style="background: var(--color-accent-dark); color: var(--color-on-primary)"
            >
              A
            </div>
          </div>
          <div>
            <div class="font-serif text-[17px] font-medium" style="color: var(--color-ink)">Eventos</div>
            <div class="text-[12.5px] leading-relaxed mt-1" style="color: var(--color-muted)">
              Agenda paroquial fora do ECC — festas, encontros e celebrações.
            </div>
          </div>
          <div class="mt-auto flex gap-3.5 text-[12px]" style="color: var(--color-muted)">
            <span>Lista e calendário</span>
          </div>
        </button>

        <button
          v-if="showSite"
          type="button"
          class="text-left rounded-xl p-[22px] flex flex-col gap-3 cursor-pointer"
          style="background: var(--color-surface); border: 1px solid var(--color-line)"
          data-testid="launcher-card-site"
          @click="go('/site')"
          @mouseenter="($event.currentTarget.style.borderColor = 'var(--color-primary-hover)')"
          @mouseleave="($event.currentTarget.style.borderColor = 'var(--color-line)')"
        >
          <div class="flex items-start justify-between">
            <div
              class="w-[38px] h-[38px] rounded-[9px] flex items-center justify-center font-serif text-[17px] font-medium"
              style="background: var(--color-ink); color: var(--color-on-primary)"
            >
              S
            </div>
            <span
              v-if="sitePublished === true"
              class="text-[11px] font-medium px-2 py-1 rounded-full"
              style="color: #2A6B4A; background: #E7F1EA"
            >
              publicado
            </span>
            <span
              v-else-if="sitePublished === false"
              class="text-[11px] font-medium px-2 py-1 rounded-full"
              style="color: var(--color-accent-dark); background: var(--color-accent-soft)"
            >
              rascunho
            </span>
          </div>
          <div>
            <div class="font-serif text-[17px] font-medium" style="color: var(--color-ink)">Site</div>
            <div class="text-[12.5px] leading-relaxed mt-1" style="color: var(--color-muted)">
              Página pública, comunicados e horários.
            </div>
          </div>
          <div class="mt-auto font-mono text-[12px]" style="color: var(--color-muted)">
            {{ siteMeta }}
          </div>
        </button>

        <div
          class="rounded-xl p-[22px] flex flex-col gap-3 opacity-90"
          style="background: var(--color-surface); border: 1px solid var(--color-line)"
        >
          <div
            class="w-[38px] h-[38px] rounded-[9px] flex items-center justify-center font-serif text-[17px] font-medium"
            style="background: var(--color-surface-2); color: var(--color-primary-soft)"
          >
            P
          </div>
          <div>
            <div class="font-serif text-[17px] font-medium" style="color: var(--color-ink)">Pastorais</div>
            <div class="text-[12.5px] leading-relaxed mt-1" style="color: var(--color-muted)">
              Grupos, coordenações e membros.
            </div>
          </div>
          <div class="mt-auto text-[12px]" style="color: var(--color-muted)">em breve</div>
        </div>

        <div
          class="rounded-xl p-[22px] flex flex-col gap-3"
          style="background: var(--color-surface); border: 1px solid var(--color-line)"
        >
          <div
            class="w-[38px] h-[38px] rounded-[9px] flex items-center justify-center font-serif text-[17px] font-medium"
            style="background: var(--color-surface-2); color: var(--color-primary-soft)"
          >
            F
          </div>
          <div>
            <div class="font-serif text-[17px] font-medium" style="color: var(--color-ink)">Financeiro</div>
            <div class="text-[12.5px] leading-relaxed mt-1" style="color: var(--color-muted)">
              Dízimos, despesas e prestação de contas.
            </div>
          </div>
          <div class="mt-auto text-[12px]" style="color: var(--color-muted)">em breve</div>
        </div>

        <div
          class="rounded-xl p-[22px] flex flex-col gap-3"
          style="background: transparent; border: 1px dashed var(--color-line)"
        >
          <div
            class="w-[38px] h-[38px] rounded-[9px] border flex items-center justify-center text-[20px]"
            style="border-color: var(--color-line); color: var(--color-muted)"
          >
            +
          </div>
          <div>
            <div class="font-serif text-[15px] font-medium" style="color: var(--color-muted)">
              Outros módulos
            </div>
            <div class="text-[12.5px] leading-relaxed mt-1" style="color: var(--color-muted)">
              Catequese, secretaria, patrimônio.
            </div>
          </div>
        </div>

        <div
          v-if="showIgrejas || showUsuarios || showAuditoria || showMarca || isPlatformAdmin"
          class="rounded-xl p-[22px] flex flex-col gap-3.5"
          style="background: var(--color-primary)"
          data-testid="launcher-card-admin"
        >
          <div
            class="text-[11px] font-medium tracking-wider uppercase"
            style="color: var(--color-accent)"
          >
            Administração
          </div>
          <div class="flex flex-col gap-2.5">
            <button
              v-if="showIgrejas"
              type="button"
              class="text-left text-[13.5px] bg-transparent border-0 p-0 cursor-pointer"
              style="color: var(--color-on-primary)"
              @click="go('/igrejas')"
            >
              Comunidades e igrejas
            </button>
            <button
              v-if="showUsuarios"
              type="button"
              class="text-left text-[13.5px] bg-transparent border-0 p-0 cursor-pointer"
              style="color: color-mix(in srgb, var(--color-on-primary) 80%, transparent)"
              @click="go('/usuarios')"
            >
              Usuários e permissões
            </button>
            <button
              v-if="showMarca"
              type="button"
              class="text-left text-[13.5px] bg-transparent border-0 p-0 cursor-pointer"
              style="color: color-mix(in srgb, var(--color-on-primary) 80%, transparent)"
              data-testid="launcher-link-marca"
              @click="go('/configuracoes/marca')"
            >
              Marca do sistema
            </button>
            <button
              v-if="showAuditoria"
              type="button"
              class="text-left text-[13.5px] bg-transparent border-0 p-0 cursor-pointer"
              style="color: color-mix(in srgb, var(--color-on-primary) 80%, transparent)"
              data-testid="launcher-link-auditoria"
              @click="go('/auditoria')"
            >
              Auditoria
            </button>
            <button
              v-if="isPlatformAdmin"
              type="button"
              class="text-left text-[13.5px] bg-transparent border-0 p-0 cursor-pointer"
              style="color: color-mix(in srgb, var(--color-on-primary) 80%, transparent)"
              @click="go('/admin/tenants')"
            >
              Tenants da plataforma
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Mobile body (1d) -->
    <div class="md:hidden px-[18px] py-5 flex flex-col gap-2.5 pb-8">
      <div
        class="text-[11px] font-medium tracking-wider uppercase mb-0.5"
        style="color: var(--color-accent-dark)"
      >
        Módulos
      </div>

      <button
        v-if="showEcc"
        type="button"
        class="flex items-center gap-3.5 rounded-[11px] p-[15px] min-h-11 text-left"
        style="background: var(--color-surface); border: 1px solid var(--color-line)"
        @click="go('/ecc/casais')"
      >
        <div
          class="w-9 h-9 rounded-lg flex items-center justify-center font-serif text-[16px] font-medium shrink-0"
          style="background: var(--color-primary-soft); color: var(--color-on-primary)"
        >
          C
        </div>
        <div class="flex-1 min-w-0">
          <div class="font-serif text-[15px] font-medium" style="color: var(--color-ink)">ECC</div>
          <div class="text-[12px] mt-0.5" style="color: var(--color-muted)">{{ eccMeta }}</div>
        </div>
      </button>


      <button
        v-if="showCalendario"
        type="button"
        class="flex items-center gap-3.5 rounded-[11px] p-[15px] min-h-11 text-left"
        style="background: var(--color-surface); border: 1px solid var(--color-line)"
        data-testid="launcher-card-calendario-mobile"
        @click="go('/calendario')"
      >
        <div
          class="w-9 h-9 rounded-lg flex items-center justify-center font-serif text-[16px] font-medium shrink-0"
          style="background: var(--color-primary); color: var(--color-on-primary)"
        >
          C
        </div>
        <div class="flex-1 min-w-0">
          <div class="font-serif text-[15px] font-medium" style="color: var(--color-ink)">Calendário</div>
          <div class="text-[12px] mt-0.5" style="color: var(--color-muted)">
            Oficial mensal e PDF
          </div>
        </div>
      </button>

      <button
        v-if="showEventos"
        type="button"
        class="flex items-center gap-3.5 rounded-[11px] p-[15px] min-h-11 text-left"
        style="background: var(--color-surface); border: 1px solid var(--color-line)"
        data-testid="launcher-card-eventos-mobile"
        @click="go('/eventos')"
      >
        <div
          class="w-9 h-9 rounded-lg flex items-center justify-center font-serif text-[16px] font-medium shrink-0"
          style="background: var(--color-accent-dark); color: var(--color-on-primary)"
        >
          A
        </div>
        <div class="flex-1 min-w-0">
          <div class="font-serif text-[15px] font-medium" style="color: var(--color-ink)">Eventos</div>
          <div class="text-[12px] mt-0.5" style="color: var(--color-muted)">Agenda paroquial</div>
        </div>
      </button>

      <button
        v-if="showSite"
        type="button"
        class="flex items-center gap-3.5 rounded-[11px] p-[15px] min-h-11 text-left"
        style="background: var(--color-surface); border: 1px solid var(--color-line)"
        @click="go('/site')"
      >
        <div
          class="w-9 h-9 rounded-lg flex items-center justify-center font-serif text-[16px] font-medium shrink-0"
          style="background: var(--color-ink); color: var(--color-on-primary)"
        >
          S
        </div>
        <div class="flex-1 min-w-0">
          <div class="font-serif text-[15px] font-medium" style="color: var(--color-ink)">Site</div>
          <div class="text-[12px] mt-0.5" style="color: var(--color-muted)">
            {{ sitePublished === true ? 'Publicado' : sitePublished === false ? 'Rascunho' : 'Gerenciar site' }}
          </div>
        </div>
      </button>

      <div
        class="flex items-center gap-3.5 rounded-[11px] p-[15px] min-h-11"
        style="background: var(--color-surface); border: 1px solid var(--color-line)"
      >
        <div
          class="w-9 h-9 rounded-lg flex items-center justify-center font-serif text-[16px] font-medium shrink-0"
          style="background: var(--color-surface-2); color: var(--color-primary-soft)"
        >
          P
        </div>
        <div class="flex-1">
          <div class="font-serif text-[15px] font-medium" style="color: var(--color-ink)">Pastorais</div>
          <div class="text-[12px] mt-0.5" style="color: var(--color-muted)">em breve</div>
        </div>
      </div>

      <div
        class="flex items-center gap-3.5 rounded-[11px] p-[15px] min-h-11"
        style="border: 1px dashed var(--color-line)"
      >
        <div
          class="w-9 h-9 rounded-lg flex items-center justify-center font-serif text-[16px] font-medium shrink-0"
          style="background: var(--color-surface-2); color: var(--color-muted)"
        >
          F
        </div>
        <div class="flex-1">
          <div class="font-serif text-[15px] font-medium" style="color: var(--color-muted)">
            Financeiro
          </div>
          <div class="text-[12px] mt-0.5" style="color: var(--color-muted)">em breve</div>
        </div>
      </div>

      <div v-if="showIgrejas || showUsuarios || showAuditoria || showMarca" class="mt-3 flex flex-col gap-2">
        <button
          v-if="showIgrejas"
          type="button"
          class="text-left text-[13px] py-2"
          style="color: var(--color-primary-hover)"
          @click="go('/igrejas')"
        >
          Comunidades e igrejas →
        </button>
        <button
          v-if="showUsuarios"
          type="button"
          class="text-left text-[13px] py-2"
          style="color: var(--color-primary-hover)"
          @click="go('/usuarios')"
        >
          Usuários e permissões →
        </button>
        <button
          v-if="showMarca"
          type="button"
          class="text-left text-[13px] py-2"
          style="color: var(--color-primary-hover)"
          data-testid="launcher-link-marca-mobile"
          @click="go('/configuracoes/marca')"
        >
          Marca do sistema →
        </button>
        <button
          v-if="showAuditoria"
          type="button"
          class="text-left text-[13px] py-2"
          style="color: var(--color-primary-hover)"
          data-testid="launcher-link-auditoria-mobile"
          @click="go('/auditoria')"
        >
          Auditoria →
        </button>
      </div>
    </div>
  </div>
</template>
