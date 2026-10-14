<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useToastStore } from '@/stores/toast'

const router = useRouter()
const auth = useAuthStore()
const toast = useToastStore()
const exiting = ref(false)

async function sair() {
  if (exiting.value) return
  exiting.value = true
  try {
    await auth.clearViewAs()
    await router.push('/inicio')
  } catch (e) {
    toast.showToast(
      'error',
      'Simulação',
      e?.response?.data?.message || 'Não foi possível sair da simulação.',
    )
  } finally {
    exiting.value = false
  }
}
</script>

<template>
  <div
    class="px-4 py-2.5 flex flex-wrap items-center justify-between gap-2 text-[13px]"
    style="background: var(--color-primary); color: var(--color-on-primary)"
    data-testid="view-as-lider-banner"
    role="status"
  >
    <span>
      Simulando visão de líder de equipe
      <span
        v-if="auth.user?.view_as?.equipes?.length"
        class="opacity-80"
      >
        · {{ auth.user.view_as.equipes.map((e) => e.nome).join(', ') }}
      </span>
    </span>
    <button
      type="button"
      class="btn btn-ghost text-[12.5px] px-3 py-1 min-h-0"
      style="color: var(--color-on-primary); border-color: color-mix(in srgb, var(--color-on-primary) 35%, transparent)"
      data-testid="view-as-lider-sair"
      :disabled="exiting"
      @click="sair"
    >
      {{ exiting ? 'Saindo…' : 'Sair da simulação' }}
    </button>
  </div>
</template>
