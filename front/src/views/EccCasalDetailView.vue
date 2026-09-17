<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import api from '@/services/api'
import { innovToast } from '@/plugins/toast'
import { casalEle, casalEla } from '@/utils/casalDisplay'
import { useAuthStore } from '@/stores/auth'
import { useAuthAdminStore } from '@/stores/authAdmin'
import { userHasPermission } from '@/utils/userRoles'
import PessoaFotoField from '@/components/ecc/PessoaFotoField.vue'

const route = useRoute()
const router = useRouter()
const authStore = useAuthStore()
const authAdminStore = useAuthAdminStore()
const loading = ref(true)
const swapping = ref(false)
const casal = ref(null)

const canManage = computed(() =>
  userHasPermission(authStore.user, 'ecc.casais.manage', {
    isSuperAdmin: authAdminStore.isAuthenticated,
  }) || userHasPermission(authStore.user, 'pessoas.manage', {
    isSuperAdmin: authAdminStore.isAuthenticated,
  }),
)

const headerPhoto = computed(() => casal.value?.ele?.foto_url || casal.value?.ela?.foto_url || null)

const initials = computed(() => {
  const a = (casalEle(casal.value) || '?')[0]
  const b = (casalEla(casal.value) || '')[0]
  return (a + (b || '')).toUpperCase()
})

const title = computed(() => {
  if (!casal.value) return ''
  const a = casalEle(casal.value) || '—'
  const b = casalEla(casal.value)
  return b ? `${a} & ${b}` : a
})

const ele = computed(() => casal.value?.ele || {
  nome: casal.value?.nome,
  email: casal.value?.email,
  telefone: casal.value?.telefone,
  data_nascimento: casal.value?.data_nascimento,
})

const ela = computed(() => casal.value?.ela || {
  nome: casal.value?.nome_conjuge,
  email: casal.value?.email_conjuge,
  telefone: casal.value?.telefone_conjuge,
  data_nascimento: casal.value?.data_nascimento_conjuge,
})

const historico = computed(() => {
  const items = []
  if (casal.value?.ecc_origem) {
    items.push({ ano: '—', titulo: 'Origem no ECC', detalhe: casal.value.ecc_origem })
  }
  if (casal.value?.funcao_dirigente) {
    items.push({ ano: '—', titulo: 'Função dirigente', detalhe: casal.value.funcao_dirigente })
  }
  if (casal.value?.experiencia_servico) {
    items.push({ ano: '—', titulo: 'Experiência de serviço', detalhe: casal.value.experiencia_servico })
  }
  return items
})

async function load() {
  loading.value = true
  try {
    const { data } = await api.get(`/ecc/casais/${route.params.id}`)
    casal.value = data.data || data
  } catch (e) {
    innovToast('error', 'Erro', e.response?.data?.message || 'Casal não encontrado')
    router.push('/ecc/casais')
  } finally {
    loading.value = false
  }
}

function onEleFoto(url) {
  if (!casal.value?.ele) return
  casal.value = {
    ...casal.value,
    ele: { ...casal.value.ele, foto_url: url },
    ficha_com_foto: Boolean(url || casal.value.ela?.foto_url),
  }
}

function onElaFoto(url) {
  if (!casal.value?.ela) return
  casal.value = {
    ...casal.value,
    ela: { ...casal.value.ela, foto_url: url },
    ficha_com_foto: Boolean(casal.value.ele?.foto_url || url),
  }
}

async function swapEleEla() {
  if (!casal.value || swapping.value) return
  if (!confirm('Trocar Ele e Ela neste casal?')) return
  swapping.value = true
  try {
    const { data } = await api.post(`/ecc/casais/${casal.value.id}/swap`)
    casal.value = data.data || data
    innovToast('success', 'OK', 'Ele e Ela corrigidos')
  } catch (e) {
    innovToast('error', 'Erro', e.response?.data?.message || 'Falha ao trocar')
  } finally {
    swapping.value = false
  }
}

onMounted(load)
</script>

<template>
  <div data-testid="ecc-casal-detail">
    <div v-if="loading" class="p-8" style="color: rgba(42,20,24,0.5)">Carregando…</div>
    <template v-else-if="casal">
      <div class="px-6 md:px-[30px] py-6 md:py-7" style="background: #4E1220">
        <button
          type="button"
          class="bg-transparent border-0 p-0 text-[12.5px] cursor-pointer mb-4"
          style="color: rgba(255, 253, 250, 0.65)"
          @click="router.push(casal.equipe_id ? `/ecc/casais?equipe=${casal.equipe_id}` : '/ecc/casais')"
        >
          ← {{ casal.equipe_nome ? `Equipe ${casal.equipe_nome}` : 'Casais' }}
        </button>
        <div class="flex flex-wrap items-center gap-4">
          <div
            class="w-[62px] h-[62px] rounded-full overflow-hidden flex items-center justify-center font-serif text-[21px] font-medium shrink-0"
            style="background: #C88A5E; color: #4E1220"
          >
            <img
              v-if="headerPhoto"
              :src="headerPhoto"
              alt=""
              class="w-full h-full object-cover"
            >
            <template v-else>{{ initials }}</template>
          </div>
          <div class="flex-1 min-w-0">
            <h1 class="font-serif text-[27px] leading-tight" style="color: #FFFDFA">{{ title }}</h1>
            <div class="flex flex-wrap gap-2 mt-2">
              <span
                v-if="casal.ficha_com_foto"
                class="text-[11.5px] font-medium px-2.5 py-1 rounded-full"
                style="color: #4E1220; background: #C88A5E"
                data-testid="casal-ficha-com-foto"
              >Ficha com foto</span>
              <span
                v-if="casal.funcao_dirigente"
                class="text-[11.5px] font-medium px-2.5 py-1 rounded-full"
                style="color: #4E1220; background: #C88A5E"
              >{{ casal.funcao_dirigente }}</span>
              <span
                v-if="casal.ecc_origem"
                class="text-[11.5px] px-2.5 py-1 rounded-full"
                style="color: rgba(255,253,250,0.8); border: 1px solid rgba(255,253,250,0.28)"
              >{{ casal.ecc_origem }}</span>
              <span
                v-if="casal.equipe_nome"
                class="text-[11.5px] px-2.5 py-1 rounded-full"
                style="color: rgba(255,253,250,0.8); border: 1px solid rgba(255,253,250,0.28)"
              >{{ casal.equipe_nome }}</span>
            </div>
          </div>
          <div class="flex flex-wrap gap-2">
            <button
              v-if="canManage"
              type="button"
              class="rounded-lg px-3.5 py-2.5 text-[12.5px] font-medium"
              style="border: 1px solid rgba(255,253,250,0.3); background: transparent; color: #FFFDFA"
              :disabled="swapping"
              data-testid="casal-swap-ele-ela"
              @click="swapEleEla"
            >
              Trocar Ele/Ela
            </button>
            <button
              type="button"
              class="rounded-lg px-3.5 py-2.5 text-[12.5px] font-medium"
              style="border: 1px solid rgba(255,253,250,0.3); background: transparent; color: #FFFDFA"
              @click="router.push(`/ecc/casais?edit=${casal.id}`)"
            >
              Editar
            </button>
          </div>
        </div>
      </div>

      <div class="p-6 md:p-[30px] grid md:grid-cols-2 gap-[18px]" style="background: #F7F4EF">
        <div
          class="rounded-[11px] p-5"
          style="background: #FFFDFA; border: 1px solid rgba(42, 20, 24, 0.1)"
        >
          <div class="text-[11px] font-medium tracking-wider uppercase mb-3.5" style="color: #B4703F">Ele</div>
          <PessoaFotoField
            class="mb-4"
            :pessoa-id="ele.id"
            :foto-url="ele.foto_url"
            label="Foto"
            :editable="canManage && !!ele.id"
            @update:foto-url="onEleFoto"
          />
          <div class="text-[15px] font-medium" style="color: #2A1418">{{ ele.nome || '—' }}</div>
          <div class="flex flex-col gap-2.5 mt-3.5 text-[13px]" style="color: rgba(42, 20, 24, 0.7)">
            <div class="flex justify-between gap-3">
              <span style="color: rgba(42, 20, 24, 0.62)">Nascimento</span>
              <span>{{ ele.data_nascimento || '—' }}</span>
            </div>
            <div class="flex justify-between gap-3">
              <span style="color: rgba(42, 20, 24, 0.62)">Telefone</span>
              <span>{{ ele.telefone || '—' }}</span>
            </div>
            <div class="flex justify-between gap-3">
              <span style="color: rgba(42, 20, 24, 0.62)">E-mail</span>
              <span class="truncate">{{ ele.email || '—' }}</span>
            </div>
          </div>
        </div>

        <div
          class="rounded-[11px] p-5"
          style="background: #FFFDFA; border: 1px solid rgba(42, 20, 24, 0.1)"
        >
          <div class="text-[11px] font-medium tracking-wider uppercase mb-3.5" style="color: #B4703F">Ela</div>
          <PessoaFotoField
            class="mb-4"
            :pessoa-id="ela.id"
            :foto-url="ela.foto_url"
            label="Foto"
            :editable="canManage && !!ela.id"
            @update:foto-url="onElaFoto"
          />
          <div class="text-[15px] font-medium" style="color: #2A1418">{{ ela.nome || '—' }}</div>
          <div class="flex flex-col gap-2.5 mt-3.5 text-[13px]" style="color: rgba(42, 20, 24, 0.7)">
            <div class="flex justify-between gap-3">
              <span style="color: rgba(42, 20, 24, 0.62)">Nascimento</span>
              <span>{{ ela.data_nascimento || '—' }}</span>
            </div>
            <div class="flex justify-between gap-3">
              <span style="color: rgba(42, 20, 24, 0.62)">Telefone</span>
              <span>{{ ela.telefone || '—' }}</span>
            </div>
            <div class="flex justify-between gap-3">
              <span style="color: rgba(42, 20, 24, 0.62)">E-mail</span>
              <span class="truncate">{{ ela.email || '—' }}</span>
            </div>
          </div>
        </div>

        <div
          class="md:col-span-2 rounded-[11px] p-5"
          style="background: #FFFDFA; border: 1px solid rgba(42, 20, 24, 0.1)"
        >
          <div class="font-serif text-[15px] font-medium mb-3.5" style="color: #2A1418">
            Casamento e família
          </div>
          <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-[13px]" style="color: #2A1418">
            <div>
              <div class="text-[12px] mb-1" style="color: rgba(42, 20, 24, 0.62)">Casamento</div>
              {{ casal.data_casamento || '—' }}
            </div>
            <div>
              <div class="text-[12px] mb-1" style="color: rgba(42, 20, 24, 0.62)">Anos</div>
              {{ casal.anos_casados ?? '—' }}
            </div>
            <div>
              <div class="text-[12px] mb-1" style="color: rgba(42, 20, 24, 0.62)">Filhos</div>
              {{ casal.filhos || '—' }}
            </div>
            <div>
              <div class="text-[12px] mb-1" style="color: rgba(42, 20, 24, 0.62)">Endereço</div>
              {{ casal.endereco || '—' }}
            </div>
          </div>
        </div>

        <div
          class="md:col-span-2 rounded-[11px] p-5"
          style="background: #FFFDFA; border: 1px solid rgba(42, 20, 24, 0.1)"
        >
          <div class="font-serif text-[15px] font-medium mb-3.5" style="color: #2A1418">
            Histórico no movimento
          </div>
          <div v-if="!historico.length" class="text-[13px]" style="color: rgba(42, 20, 24, 0.62)">
            Sem histórico registrado ainda.
          </div>
          <div
            v-for="(h, i) in historico"
            :key="i"
            class="grid gap-4 py-2.5"
            style="grid-template-columns: 96px 1fr; border-top: 1px solid rgba(42, 20, 24, 0.07)"
          >
            <div class="font-mono text-[12.5px] font-medium" style="color: #B4703F">{{ h.ano }}</div>
            <div>
              <div class="text-[13.5px] font-medium" style="color: #2A1418">{{ h.titulo }}</div>
              <div class="text-[12.5px] mt-0.5" style="color: rgba(42, 20, 24, 0.62)">{{ h.detalhe }}</div>
            </div>
          </div>
        </div>
      </div>
    </template>
  </div>
</template>
