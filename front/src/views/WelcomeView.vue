<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useAuthAdminStore } from '@/stores/authAdmin'
import { useTenantStore } from '@/stores/tenant'
import { useIgrejaStore } from '@/stores/igreja'
import { userHasPermission, canSeeEccCasaisNav } from '@/utils/userRoles'
import api from '@/services/api'

const router = useRouter()
const authTenant = useAuthStore()
const authAdmin = useAuthAdminStore()
const tenantStore = useTenantStore()
const igrejaStore = useIgrejaStore()

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
const showSite = computed(() => can('telas.site') || isPlatformAdmin.value)
const showIgrejas = computed(() => can('telas.igrejas') || isPlatformAdmin.value)
const showUsuarios = computed(() => can('telas.usuarios') || isPlatformAdmin.value)

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

onMounted(loadCounts)

async function logout() {
  if (isPlatformAdmin.value) {
    tenantStore.clear()
    igrejaStore.reset()
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
  <div class="min-h-screen" style="background: #F7F4EF" data-testid="welcome-page">
    <!-- Desktop top bar (1c) -->
    <header
      class="hidden md:flex items-center justify-between px-[26px] h-14"
      style="background: #4E1220"
    >
      <div class="flex items-center gap-[22px] min-w-0">
        <div class="flex items-center gap-2.5 shrink-0">
          <div
            class="w-6 h-6 rounded-full border flex items-center justify-center font-serif text-[12px] font-medium"
            style="border-color: #C88A5E; color: #F0D8C2"
          >
            E
          </div>
          <span class="font-serif text-[15px] font-medium" style="color: #FFFDFA">Eclesias</span>
        </div>
        <div class="w-px h-[22px]" style="background: rgba(255, 253, 250, 0.2)" />
        <button
          type="button"
          class="flex items-center gap-2 px-2.5 py-1.5 rounded-md min-w-0"
          style="background: rgba(255, 253, 250, 0.09)"
        >
          <span class="text-[13px] font-medium truncate" style="color: #FFFDFA">{{ orgName }}</span>
          <span class="text-[11px] truncate hidden lg:inline" style="color: rgba(255, 253, 250, 0.6)">
            {{ comunidadeLabel }}
          </span>
          <span class="text-[10px]" style="color: rgba(255, 253, 250, 0.6)">▾</span>
        </button>
      </div>
      <div class="flex items-center gap-2.5 shrink-0 relative">
        <span class="text-[12.5px]" style="color: rgba(255, 253, 250, 0.75)">
          {{ displayName }} · {{ roleLabel }}
        </span>
        <button
          type="button"
          class="w-[30px] h-[30px] rounded-full flex items-center justify-center text-[12.5px] font-medium"
          style="background: #C88A5E; color: #4E1220"
          data-testid="launcher-avatar"
          @click="userMenuOpen = !userMenuOpen"
        >
          {{ initials }}
        </button>
        <div
          v-if="userMenuOpen"
          class="absolute right-0 top-10 rounded-lg py-2 min-w-[160px] z-20 shadow-lg"
          style="background: #FFFDFA; border: 1px solid rgba(42, 20, 24, 0.1)"
        >
          <button
            type="button"
            class="w-full text-left px-4 py-2 text-[13px]"
            style="color: #2A1418"
            @click="logout"
          >
            Sair
          </button>
        </div>
      </div>
    </header>

    <!-- Mobile header (1d) -->
    <header class="md:hidden px-[18px] pt-4 pb-[18px]" style="background: #4E1220">
      <div class="flex items-center justify-between mb-4">
        <div class="flex items-center gap-2">
          <div
            class="w-[22px] h-[22px] rounded-full border flex items-center justify-center font-serif text-[11px] font-medium"
            style="border-color: #C88A5E; color: #F0D8C2"
          >
            E
          </div>
          <span class="font-serif text-[14px] font-medium" style="color: #FFFDFA">Eclesias</span>
        </div>
        <button
          type="button"
          class="w-7 h-7 rounded-full flex items-center justify-center text-[11.5px] font-medium"
          style="background: #C88A5E; color: #4E1220"
          @click="logout"
        >
          {{ initials }}
        </button>
      </div>
      <div class="font-serif text-[22px] leading-snug mb-3.5" style="color: #FFFDFA">
        Olá, {{ firstName }}
      </div>
      <div
        class="flex items-center gap-2.5 rounded-[9px] px-3 py-2.5"
        style="background: rgba(255, 253, 250, 0.1)"
      >
        <div
          class="w-[30px] h-[30px] rounded-md flex items-center justify-center font-serif text-[13px] font-medium shrink-0"
          style="background: #C88A5E; color: #4E1220"
        >
          {{ (orgName || 'P')[0] }}
        </div>
        <div class="flex-1 min-w-0">
          <div class="text-[13px] font-medium truncate" style="color: #FFFDFA">{{ orgName }}</div>
          <div class="text-[11px] truncate" style="color: rgba(255, 253, 250, 0.6)">
            {{ comunidadeLabel }}
          </div>
        </div>
        <span class="text-[11px]" style="color: rgba(255, 253, 250, 0.7)">▾</span>
      </div>
    </header>

    <!-- Desktop body (1c) -->
    <div class="hidden md:block px-10 py-[38px] pb-[42px]">
      <p class="page-eyebrow mb-2.5">{{ greeting }}</p>
      <h1 class="font-serif font-normal text-[30px] leading-tight mb-1.5" style="color: #2A1418">
        Onde você quer trabalhar hoje, {{ firstName }}?
      </h1>
      <p class="text-[14px] leading-relaxed mb-[30px]" style="color: rgba(42, 20, 24, 0.62)">
        Você tem acesso a {{ moduleAccessCount || 'seus' }} módulos nesta paróquia.
      </p>

      <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-[18px] w-full">
        <button
          v-if="showEcc"
          type="button"
          class="text-left rounded-xl p-[22px] flex flex-col gap-3 cursor-pointer transition-shadow"
          style="background: #FFFDFA; border: 1px solid rgba(42, 20, 24, 0.11)"
          data-testid="launcher-card-ecc"
          @click="go('/ecc/casais')"
          @mouseenter="($event.currentTarget.style.borderColor = '#8A2436')"
          @mouseleave="($event.currentTarget.style.borderColor = 'rgba(42, 20, 24, 0.11)')"
        >
          <div class="flex items-start justify-between">
            <div
              class="w-[38px] h-[38px] rounded-[9px] flex items-center justify-center font-serif text-[17px] font-medium"
              style="background: #6B1C2B; color: #F0D8C2"
            >
              C
            </div>
            <span
              v-if="eccCounts.equipes != null"
              class="text-[11px] font-medium px-2 py-1 rounded-full"
              style="color: #B4703F; background: #F6EDE4"
            >
              ativo
            </span>
          </div>
          <div>
            <div class="font-serif text-[17px] font-medium" style="color: #2A1418">ECC</div>
            <div class="text-[12.5px] leading-relaxed mt-1" style="color: rgba(42, 20, 24, 0.62)">
              Equipes, casais e encontros do movimento.
            </div>
          </div>
          <div class="mt-auto flex gap-3.5 text-[12px]" style="color: rgba(42, 20, 24, 0.62)">
            <span>{{ eccMeta }}</span>
          </div>
        </button>

        <button
          v-if="showSite"
          type="button"
          class="text-left rounded-xl p-[22px] flex flex-col gap-3 cursor-pointer"
          style="background: #FFFDFA; border: 1px solid rgba(42, 20, 24, 0.11)"
          data-testid="launcher-card-site"
          @click="go('/site')"
          @mouseenter="($event.currentTarget.style.borderColor = '#8A2436')"
          @mouseleave="($event.currentTarget.style.borderColor = 'rgba(42, 20, 24, 0.11)')"
        >
          <div class="flex items-start justify-between">
            <div
              class="w-[38px] h-[38px] rounded-[9px] flex items-center justify-center font-serif text-[17px] font-medium"
              style="background: #2A1418; color: #F0D8C2"
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
              style="color: #B4703F; background: #F6EDE4"
            >
              rascunho
            </span>
          </div>
          <div>
            <div class="font-serif text-[17px] font-medium" style="color: #2A1418">Site</div>
            <div class="text-[12.5px] leading-relaxed mt-1" style="color: rgba(42, 20, 24, 0.62)">
              Página pública, comunicados e horários.
            </div>
          </div>
          <div class="mt-auto font-mono text-[12px]" style="color: rgba(42, 20, 24, 0.62)">
            {{ siteMeta }}
          </div>
        </button>

        <div
          class="rounded-xl p-[22px] flex flex-col gap-3 opacity-90"
          style="background: #FFFDFA; border: 1px solid rgba(42, 20, 24, 0.11)"
        >
          <div
            class="w-[38px] h-[38px] rounded-[9px] flex items-center justify-center font-serif text-[17px] font-medium"
            style="background: #F3EDE6; color: #6B1C2B"
          >
            P
          </div>
          <div>
            <div class="font-serif text-[17px] font-medium" style="color: #2A1418">Pastorais</div>
            <div class="text-[12.5px] leading-relaxed mt-1" style="color: rgba(42, 20, 24, 0.62)">
              Grupos, coordenações e membros.
            </div>
          </div>
          <div class="mt-auto text-[12px]" style="color: rgba(42, 20, 24, 0.62)">em breve</div>
        </div>

        <div
          class="rounded-xl p-[22px] flex flex-col gap-3"
          style="background: #FFFDFA; border: 1px solid rgba(42, 20, 24, 0.11)"
        >
          <div
            class="w-[38px] h-[38px] rounded-[9px] flex items-center justify-center font-serif text-[17px] font-medium"
            style="background: #F3EDE6; color: #6B1C2B"
          >
            F
          </div>
          <div>
            <div class="font-serif text-[17px] font-medium" style="color: #2A1418">Financeiro</div>
            <div class="text-[12.5px] leading-relaxed mt-1" style="color: rgba(42, 20, 24, 0.62)">
              Dízimos, despesas e prestação de contas.
            </div>
          </div>
          <div class="mt-auto text-[12px]" style="color: rgba(42, 20, 24, 0.62)">em breve</div>
        </div>

        <div
          class="rounded-xl p-[22px] flex flex-col gap-3"
          style="background: transparent; border: 1px dashed rgba(42, 20, 24, 0.22)"
        >
          <div
            class="w-[38px] h-[38px] rounded-[9px] border flex items-center justify-center text-[20px]"
            style="border-color: rgba(42, 20, 24, 0.18); color: rgba(42, 20, 24, 0.62)"
          >
            +
          </div>
          <div>
            <div class="font-serif text-[15px] font-medium" style="color: rgba(42, 20, 24, 0.62)">
              Outros módulos
            </div>
            <div class="text-[12.5px] leading-relaxed mt-1" style="color: rgba(42, 20, 24, 0.62)">
              Catequese, secretaria, patrimônio.
            </div>
          </div>
        </div>

        <div
          v-if="showIgrejas || showUsuarios || isPlatformAdmin"
          class="rounded-xl p-[22px] flex flex-col gap-3.5"
          style="background: #4E1220"
          data-testid="launcher-card-admin"
        >
          <div
            class="text-[11px] font-medium tracking-wider uppercase"
            style="color: #C88A5E"
          >
            Administração
          </div>
          <div class="flex flex-col gap-2.5">
            <button
              v-if="showIgrejas"
              type="button"
              class="text-left text-[13.5px] bg-transparent border-0 p-0 cursor-pointer"
              style="color: #FFFDFA"
              @click="go('/igrejas')"
            >
              Comunidades e igrejas
            </button>
            <button
              v-if="showUsuarios"
              type="button"
              class="text-left text-[13.5px] bg-transparent border-0 p-0 cursor-pointer"
              style="color: rgba(255, 253, 250, 0.8)"
              @click="go('/usuarios')"
            >
              Usuários e permissões
            </button>
            <button
              v-if="isPlatformAdmin"
              type="button"
              class="text-left text-[13.5px] bg-transparent border-0 p-0 cursor-pointer"
              style="color: rgba(255, 253, 250, 0.8)"
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
        style="color: #B4703F"
      >
        Módulos
      </div>

      <button
        v-if="showEcc"
        type="button"
        class="flex items-center gap-3.5 rounded-[11px] p-[15px] min-h-11 text-left"
        style="background: #FFFDFA; border: 1px solid rgba(42, 20, 24, 0.11)"
        @click="go('/ecc/casais')"
      >
        <div
          class="w-9 h-9 rounded-lg flex items-center justify-center font-serif text-[16px] font-medium shrink-0"
          style="background: #6B1C2B; color: #F0D8C2"
        >
          C
        </div>
        <div class="flex-1 min-w-0">
          <div class="font-serif text-[15px] font-medium" style="color: #2A1418">ECC</div>
          <div class="text-[12px] mt-0.5" style="color: rgba(42, 20, 24, 0.62)">{{ eccMeta }}</div>
        </div>
      </button>

      <button
        v-if="showSite"
        type="button"
        class="flex items-center gap-3.5 rounded-[11px] p-[15px] min-h-11 text-left"
        style="background: #FFFDFA; border: 1px solid rgba(42, 20, 24, 0.11)"
        @click="go('/site')"
      >
        <div
          class="w-9 h-9 rounded-lg flex items-center justify-center font-serif text-[16px] font-medium shrink-0"
          style="background: #2A1418; color: #F0D8C2"
        >
          S
        </div>
        <div class="flex-1 min-w-0">
          <div class="font-serif text-[15px] font-medium" style="color: #2A1418">Site</div>
          <div class="text-[12px] mt-0.5" style="color: rgba(42, 20, 24, 0.62)">
            {{ sitePublished === true ? 'Publicado' : sitePublished === false ? 'Rascunho' : 'Gerenciar site' }}
          </div>
        </div>
      </button>

      <div
        class="flex items-center gap-3.5 rounded-[11px] p-[15px] min-h-11"
        style="background: #FFFDFA; border: 1px solid rgba(42, 20, 24, 0.11)"
      >
        <div
          class="w-9 h-9 rounded-lg flex items-center justify-center font-serif text-[16px] font-medium shrink-0"
          style="background: #F3EDE6; color: #6B1C2B"
        >
          P
        </div>
        <div class="flex-1">
          <div class="font-serif text-[15px] font-medium" style="color: #2A1418">Pastorais</div>
          <div class="text-[12px] mt-0.5" style="color: rgba(42, 20, 24, 0.62)">em breve</div>
        </div>
      </div>

      <div
        class="flex items-center gap-3.5 rounded-[11px] p-[15px] min-h-11"
        style="border: 1px dashed rgba(42, 20, 24, 0.2)"
      >
        <div
          class="w-9 h-9 rounded-lg flex items-center justify-center font-serif text-[16px] font-medium shrink-0"
          style="background: #F3EDE6; color: rgba(42, 20, 24, 0.62)"
        >
          F
        </div>
        <div class="flex-1">
          <div class="font-serif text-[15px] font-medium" style="color: rgba(42, 20, 24, 0.62)">
            Financeiro
          </div>
          <div class="text-[12px] mt-0.5" style="color: rgba(42, 20, 24, 0.62)">em breve</div>
        </div>
      </div>

      <div v-if="showIgrejas || showUsuarios" class="mt-3 flex flex-col gap-2">
        <button
          v-if="showIgrejas"
          type="button"
          class="text-left text-[13px] py-2"
          style="color: #8A2436"
          @click="go('/igrejas')"
        >
          Comunidades e igrejas →
        </button>
        <button
          v-if="showUsuarios"
          type="button"
          class="text-left text-[13px] py-2"
          style="color: #8A2436"
          @click="go('/usuarios')"
        >
          Usuários e permissões →
        </button>
      </div>
    </div>
  </div>
</template>
