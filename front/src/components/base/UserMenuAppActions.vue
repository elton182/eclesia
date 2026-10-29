<script setup>
import { ref } from 'vue'
import { innovConfirm } from '@/plugins/dialog'
import { innovToast } from '@/plugins/toast'
import { APP_VERSION } from '@/utils/appVersion'
import { hardResetAppCache } from '@/utils/pwaUpdate'

const resetting = ref(false)

async function atualizarApp() {
  if (resetting.value) return

  const ok = await innovConfirm({
    title: 'Atualizar app',
    message:
      'Isso limpa o cache local e força a versão mais recente. A página vai recarregar. Continuar?',
    confirmText: 'Atualizar',
  })
  if (!ok) return

  resetting.value = true
  try {
    await hardResetAppCache()
  } catch {
    resetting.value = false
    innovToast('error', 'Erro', 'Não foi possível atualizar. Tente fechar e abrir o app de novo.')
  }
}
</script>

<template>
  <div
    class="px-4 py-1.5 text-[11px] leading-snug"
    style="color: var(--color-muted)"
    data-testid="app-version"
  >
    Versão {{ APP_VERSION }}
  </div>
  <button
    type="button"
    class="w-full text-left px-4 py-2 text-[13px]"
    style="color: var(--color-ink)"
    :disabled="resetting"
    data-testid="app-cache-reset"
    @click="atualizarApp"
  >
    {{ resetting ? 'Atualizando…' : 'Atualizar app' }}
  </button>
</template>
