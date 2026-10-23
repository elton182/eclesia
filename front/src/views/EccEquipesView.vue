<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useAuthAdminStore } from '@/stores/authAdmin'
import { useTenantStore } from '@/stores/tenant'
import api from '@/services/api'
import { innovToast } from '@/plugins/toast'
import { innovAlert, innovConfirm, innovPrompt } from '@/plugins/dialog'
import { userHasPermission } from '@/utils/userRoles'
import {
  validateEquipeUsername,
  equipesPdfFilename,
  downloadBlob,
} from '@/utils/eccEquipeAcesso'
import EccEquipeMembrosModal from '@/components/ecc/EccEquipeMembrosModal.vue'

const router = useRouter()
const authStore = useAuthStore()
const authAdmin = useAuthAdminStore()
const tenantStore = useTenantStore()

const authOpts = () => ({ isSuperAdmin: authAdmin.isAuthenticated })

const canManage = computed(() =>
  userHasPermission(authStore.user, 'ecc.equipes.manage', authOpts()),
)

const equipes = ref([])
const casais = ref([])
const loading = ref(false)
const search = ref('')
/** @type {import('vue').Ref<'list'|'form'>} */
const mode = ref('list')
const saving = ref(false)
const acessoLoadingId = ref(null)
const pdfLoading = ref(false)
const equipeForm = ref({ id: null, nome: '', cor: '#6B1C2B' })
const membrosOpen = ref(false)
const membrosEquipe = ref(null)

const filteredEquipes = computed(() => {
  const q = search.value.trim().toLocaleLowerCase('pt-BR')
  if (!q) return equipes.value
  return equipes.value.filter((eq) => {
    const nome = String(eq.nome || '').toLocaleLowerCase('pt-BR')
    const user = String(eq.acesso_username || '').toLocaleLowerCase('pt-BR')
    return nome.includes(q) || user.includes(q)
  })
})

const ensureTenant = () => {
  if (!tenantStore.slug) {
    innovToast('error', 'Organização', 'Nenhuma organização selecionada.')
    router.push('/inicio')
    return false
  }
  return true
}

const load = async () => {
  if (!ensureTenant()) return
  loading.value = true
  try {
    const [eqRes, casaisRes] = await Promise.all([
      api.get('/ecc/equipes'),
      api.get('/ecc/casais'),
    ])
    equipes.value = eqRes.data?.data || eqRes.data || []
    casais.value = casaisRes.data?.data || casaisRes.data || []
  } catch (e) {
    innovToast('error', 'Erro', e.response?.data?.message || 'Falha ao carregar equipes')
  } finally {
    loading.value = false
  }
}

const openCreate = () => {
  equipeForm.value = { id: null, nome: '', cor: '#6B1C2B' }
  mode.value = 'form'
}

const openEdit = (item) => {
  equipeForm.value = {
    id: item.id,
    nome: item.nome,
    cor: item.cor || '#6B1C2B',
  }
  mode.value = 'form'
}

const saveEquipe = async () => {
  saving.value = true
  try {
    if (equipeForm.value.id) {
      await api.put(`/ecc/equipes/${equipeForm.value.id}`, {
        nome: equipeForm.value.nome,
        cor: equipeForm.value.cor,
      })
    } else {
      await api.post('/ecc/equipes', {
        nome: equipeForm.value.nome,
        cor: equipeForm.value.cor,
      })
    }
    innovToast('success', 'OK', 'Equipe salva')
    mode.value = 'list'
    await load()
  } catch (e) {
    innovToast('error', 'Erro', e.response?.data?.message || 'Falha ao salvar equipe')
  } finally {
    saving.value = false
  }
}

const openMembros = (equipe) => {
  membrosEquipe.value = equipe
  membrosOpen.value = true
}

const showCredenciais = async (username, password, titulo) => {
  await innovAlert({
    title: titulo,
    message: `Usuário: ${username}\nSenha inicial: ${password}\n\nNo primeiro acesso o líder deverá trocar a senha.`,
    confirmText: 'Entendi',
  })
}

const handleAcesso = async (equipe) => {
  if (!canManage.value) return

  if (equipe.tem_acesso) {
    const ok = await innovConfirm({
      title: 'Resetar senha',
      message: `Resetar a senha de ${equipe.nome} para 1234? O líder deverá trocá-la no próximo login.`,
      confirmText: 'Resetar',
      cancelText: 'Cancelar',
      danger: true,
    })
    if (!ok) return

    acessoLoadingId.value = equipe.id
    try {
      const { data } = await api.post(`/ecc/equipes/${equipe.id}/acesso/reset-senha`)
      const payload = data?.data || data
      await showCredenciais(payload.username, payload.password, 'Senha resetada')
      await load()
    } catch (e) {
      innovToast('error', 'Erro', e.response?.data?.message || 'Falha ao resetar senha')
    } finally {
      acessoLoadingId.value = null
    }
    return
  }

  const ok = await innovConfirm({
    title: 'Acesso da Equipe',
    message: `Criar usuário de acesso para ${equipe.nome}? A senha inicial será 1234 (troca obrigatória no primeiro login).`,
    confirmText: 'Criar acesso',
    cancelText: 'Cancelar',
    danger: false,
  })
  if (!ok) return

  acessoLoadingId.value = equipe.id
  try {
    const { data } = await api.post(`/ecc/equipes/${equipe.id}/acesso`)
    const payload = data?.data || data
    await showCredenciais(payload.username, payload.password, 'Acesso criado')
    await load()
  } catch (e) {
    innovToast('error', 'Erro', e.response?.data?.message || 'Falha ao criar acesso')
  } finally {
    acessoLoadingId.value = null
  }
}

const handleAlterarLogin = async (equipe) => {
  if (!canManage.value || !equipe.tem_acesso) return

  const digitado = await innovPrompt({
    title: 'Alterar login',
    message: `Login atual de ${equipe.nome}: ${equipe.acesso_username}. A senha permanece a mesma; sessões ativas serão encerradas.`,
    label: 'Novo login',
    placeholder: 'ex.: equipe-cana',
    defaultValue: equipe.acesso_username || '',
    confirmText: 'Salvar',
    cancelText: 'Cancelar',
  })
  if (digitado === null) return

  const valid = validateEquipeUsername(digitado)
  if (!valid.ok) {
    innovToast('error', 'Login inválido', valid.message)
    return
  }

  if (valid.username === String(equipe.acesso_username || '')) {
    innovToast('success', 'Sem alteração', 'O login informado é o mesmo do atual.')
    return
  }

  acessoLoadingId.value = equipe.id
  try {
    const { data } = await api.put(`/ecc/equipes/${equipe.id}/acesso`, {
      username: valid.username,
    })
    const payload = data?.data || data
    await innovAlert({
      title: 'Login alterado',
      message: `Novo usuário: ${payload.username || valid.username}\n\nInforme o novo login ao líder. A senha não foi alterada.`,
      confirmText: 'Entendi',
    })
    await load()
  } catch (e) {
    const msg =
      e.response?.data?.errors?.username?.[0] ||
      e.response?.data?.message ||
      'Falha ao alterar login'
    innovToast('error', 'Erro', msg)
  } finally {
    acessoLoadingId.value = null
  }
}

const verCasais = (equipe) => {
  router.push({ name: 'ecc-casais', query: { equipe: String(equipe.id) } })
}

const baixarPdf = async () => {
  if (!canManage.value || !ensureTenant()) return
  pdfLoading.value = true
  try {
    const res = await api.get('/ecc/equipes/pdf', { responseType: 'blob' })
    downloadBlob(res.data, equipesPdfFilename(tenantStore.slug || 'igreja'))
  } catch (e) {
    innovToast('error', 'PDF', e.response?.data?.message || 'Falha ao gerar PDF')
  } finally {
    pdfLoading.value = false
  }
}

onMounted(load)
</script>

<template>
  <div class="p-6 md:p-[30px] w-full space-y-5" data-testid="ecc-equipes-view">
    <div v-if="mode === 'list'" class="space-y-5 w-full">
      <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
        <div>
          <p class="text-[12px] uppercase tracking-wide mb-1" style="color: rgba(42, 20, 24, 0.5)">
            ECC
          </p>
          <h1 class="font-serif text-2xl" style="color: #2A1418">Equipes</h1>
          <p class="text-[13px] mt-1" style="color: rgba(42, 20, 24, 0.62)">
            Grupos de perseverança, membros (casais) e acesso compartilhado do líder.
          </p>
        </div>
        <div class="flex flex-col gap-2 w-full sm:w-auto sm:flex-row">
          <button
            v-if="canManage"
            type="button"
            class="btn btn-ghost w-full sm:w-auto"
            data-testid="equipes-pdf"
            :disabled="pdfLoading"
            @click="baixarPdf"
          >
            {{ pdfLoading ? 'Gerando PDF…' : 'Gerar PDF' }}
          </button>
          <button
            v-if="canManage"
            type="button"
            class="btn btn-primary w-full sm:w-auto"
            data-testid="equipes-nova"
            @click="openCreate"
          >
            Nova equipe
          </button>
        </div>
      </div>

      <div class="flex flex-col gap-3 w-full md:flex-row md:flex-wrap md:items-center">
        <input
          v-model="search"
          type="search"
          class="input w-full md:flex-1 md:min-w-[220px]"
          placeholder="Pesquisar equipe ou usuário de acesso…"
          data-testid="equipes-search"
          aria-label="Pesquisar equipes"
        >
      </div>

      <div
        v-if="loading"
        class="p-10 text-center text-[13px]"
        style="color: rgba(42, 20, 24, 0.5)"
      >
        Carregando…
      </div>

      <div
        v-else
        class="w-full overflow-hidden rounded-[11px]"
        style="background: #FFFDFA; border: 1px solid rgba(42, 20, 24, 0.1)"
        data-testid="equipes-lista"
      >
        <div
          v-if="!filteredEquipes.length"
          class="p-10 text-center"
          style="color: rgba(42, 20, 24, 0.5)"
        >
          {{ search.trim() ? 'Nenhuma equipe encontrada.' : 'Nenhuma equipe cadastrada.' }}
        </div>
        <div
          v-for="(equipe, index) in filteredEquipes"
          :key="equipe.id"
          class="flex flex-col gap-3 px-5 md:px-6 py-5 sm:flex-row sm:items-center sm:justify-between"
          :style="index > 0 ? 'border-top: 1px solid rgba(42, 20, 24, 0.07)' : undefined"
          data-testid="equipe-row"
        >
          <div class="flex items-center gap-2.5 min-w-0">
            <div
              class="w-[30px] h-[30px] rounded-md flex items-center justify-center font-serif text-[13px] font-medium shrink-0"
              :style="{ background: '#F3EDE6', color: equipe.cor || '#6B1C2B' }"
            >
              {{ (equipe.nome || '?')[0] }}
            </div>
            <div class="min-w-0">
              <div class="text-[13.5px] font-medium truncate flex items-center gap-2" style="color: #2A1418">
                <span>{{ equipe.nome }}</span>
                <span
                  v-if="equipe.tem_acesso"
                  class="text-[11px] font-normal px-1.5 py-0.5 rounded"
                  style="background: rgba(107, 28, 43, 0.08); color: #6B1C2B"
                  data-testid="equipe-badge-acesso"
                >
                  Acesso: {{ equipe.acesso_username }}
                </span>
              </div>
              <div class="text-[12.5px]" style="color: rgba(42, 20, 24, 0.62)">
                {{ equipe.casais_count ?? 0 }} casais
              </div>
            </div>
          </div>
          <div class="flex flex-wrap gap-2">
            <button
              type="button"
              class="btn btn-ghost"
              data-testid="equipe-ver-casais"
              @click="verCasais(equipe)"
            >
              Ver casais
            </button>
            <button
              v-if="canManage"
              type="button"
              class="btn btn-ghost"
              data-testid="equipe-membros"
              @click="openMembros(equipe)"
            >
              Membros
            </button>
            <button
              v-if="canManage"
              type="button"
              class="btn btn-ghost"
              data-testid="equipe-acesso"
              :disabled="acessoLoadingId === equipe.id"
              @click="handleAcesso(equipe)"
            >
              {{
                acessoLoadingId === equipe.id
                  ? 'Aguarde…'
                  : equipe.tem_acesso
                    ? 'Resetar senha'
                    : 'Acesso da Equipe'
              }}
            </button>
            <button
              v-if="canManage && equipe.tem_acesso"
              type="button"
              class="btn btn-ghost"
              data-testid="equipe-alterar-login"
              :disabled="acessoLoadingId === equipe.id"
              @click="handleAlterarLogin(equipe)"
            >
              Alterar login
            </button>
            <button
              v-if="canManage"
              type="button"
              class="btn btn-ghost"
              data-testid="equipe-editar"
              @click="openEdit(equipe)"
            >
              Editar
            </button>
          </div>
        </div>
      </div>
    </div>

    <div
      v-else-if="mode === 'form'"
      class="rounded-[11px] p-6 w-full max-w-md"
      style="background: #FFFDFA; border: 1px solid rgba(42, 20, 24, 0.1)"
      data-testid="equipe-form"
    >
      <h2 class="font-serif text-xl mb-4" style="color: #2A1418">
        {{ equipeForm.id ? 'Editar equipe' : 'Nova equipe' }}
      </h2>
      <form class="space-y-4" @submit.prevent="saveEquipe">
        <div class="fld">
          <label class="lbl" for="equipe-nome">Nome</label>
          <input
            id="equipe-nome"
            v-model="equipeForm.nome"
            type="text"
            class="input w-full"
            required
            data-testid="equipe-nome"
          >
        </div>
        <div class="fld">
          <label class="lbl" for="equipe-cor">Cor</label>
          <input
            id="equipe-cor"
            v-model="equipeForm.cor"
            type="color"
            class="input w-full h-10"
            data-testid="equipe-cor"
          >
        </div>
        <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
          <button
            type="button"
            class="btn btn-ghost w-full sm:w-auto"
            data-testid="equipe-form-cancelar"
            @click="mode = 'list'"
          >
            Cancelar
          </button>
          <button
            type="submit"
            class="btn btn-primary w-full sm:w-auto"
            :disabled="saving"
            data-testid="equipe-form-salvar"
          >
            {{ saving ? 'Salvando…' : 'Salvar' }}
          </button>
        </div>
      </form>
    </div>

    <EccEquipeMembrosModal
      v-model="membrosOpen"
      :equipe="membrosEquipe"
      :casais="casais"
      @saved="load"
    />
  </div>
</template>
