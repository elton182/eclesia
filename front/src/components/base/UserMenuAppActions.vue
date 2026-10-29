<script setup>
import { computed, ref } from 'vue'
import { innovConfirm, innovPrompt } from '@/plugins/dialog'
import { innovToast } from '@/plugins/toast'
import api from '@/services/api'
import { APP_VERSION } from '@/utils/appVersion'
import {
  buildDiagnosticPayload,
  clearDiagnosticEvents,
  diagnosticEventCount,
} from '@/utils/clientDiagnostics'
import { hardResetAppCache } from '@/utils/pwaUpdate'

const resetting = ref(false)
const sendingDiag = ref(false)

const eventCount = computed(() => diagnosticEventCount())

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

async function enviarDiagnostico() {
  if (sendingDiag.value) return

  const nota = await innovPrompt({
    title: 'Enviar diagnóstico',
    message:
      `Vamos enviar ${eventCount.value} evento(s) desta sessão (versão, permissões e cliques na foto) para a secretaria ajudar no suporte. Opcional: descreva o que aconteceu.`,
    label: 'Descrição (opcional)',
    placeholder: 'Ex.: toquei em Galeria e não abriu nada',
    confirmText: 'Enviar',
    cancelText: 'Cancelar',
  })
  if (nota === null) return

  sendingDiag.value = true
  try {
    const payload = await buildDiagnosticPayload({
      motivo: 'foto',
      nota: String(nota || '').trim() || null,
    })
    await api.post('/client-diagnostics', payload)
    clearDiagnosticEvents()
    innovToast('success', 'Enviado', 'Diagnóstico enviado. Obrigado!')
  } catch (e) {
    innovToast('error', 'Erro', e.response?.data?.message || 'Falha ao enviar diagnóstico')
  } finally {
    sendingDiag.value = false
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
  <button
    type="button"
    class="w-full text-left px-4 py-2 text-[13px]"
    style="color: var(--color-ink)"
    :disabled="sendingDiag"
    data-testid="app-send-diagnostic"
    @click="enviarDiagnostico"
  >
    {{ sendingDiag ? 'Enviando…' : 'Enviar diagnóstico' }}
  </button>
</template>
