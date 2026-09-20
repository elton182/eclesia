<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { useBrandingStore } from '@/stores/branding'
import { useAuthStore } from '@/stores/auth'
import { useAuthAdminStore } from '@/stores/authAdmin'
import { DEFAULT_BRAND_COLORS, normalizeBrandCores, applyBrandCores } from '@/utils/branding'
import { innovToast } from '@/plugins/toast'
import LogoCropModal from '@/components/branding/LogoCropModal.vue'

const branding = useBrandingStore()
const authTenant = useAuthStore()
const authAdmin = useAuthAdminStore()

const saving = ref(false)
const uploading = ref(false)

/** @type {import('vue').Ref<'org'|'diocese'|null>} */
const cropTarget = ref(null)
const cropFile = ref(null)
const cropOpen = ref(false)

const form = reactive({
  primary: DEFAULT_BRAND_COLORS.primary,
  secondary: DEFAULT_BRAND_COLORS.secondary,
  text: DEFAULT_BRAND_COLORS.text,
  text_muted: DEFAULT_BRAND_COLORS.text_muted,
  on_primary: DEFAULT_BRAND_COLORS.on_primary,
})

const canEdit = computed(() => {
  if (authAdmin.isAuthenticated && !authTenant.isAuthenticated) return true
  const roles = (authTenant.user?.roles || []).map((r) => r.name)
  return roles.includes('admin-tenant')
})

const previewLogo = computed(() => branding.logoUrl)
const previewLogoDiocese = computed(() => branding.logoDioceseUrl)

const cropTitle = computed(() =>
  cropTarget.value === 'diocese' ? 'Ajustar logo da diocese' : 'Ajustar logo da organização',
)

function syncFormFromStore() {
  const n = normalizeBrandCores(branding.cores)
  form.primary = n.primary
  form.secondary = n.secondary
  form.text = n.text
  form.text_muted = n.text_muted
  form.on_primary = n.on_primary
}

onMounted(async () => {
  try {
    if (!branding.loaded) {
      await branding.fetch()
    }
  } catch {
    // usa fallback
  }
  syncFormFromStore()
})

watch(
  form,
  (next) => {
    applyBrandCores(next)
  },
  { deep: true },
)

async function saveCores() {
  if (!canEdit.value) return
  saving.value = true
  try {
    await branding.updateCores({ ...form })
    innovToast('success', 'Marca', 'Cores salvas')
  } catch (e) {
    innovToast('error', 'Marca', e?.response?.data?.message || 'Não foi possível salvar')
  } finally {
    saving.value = false
  }
}

function openCrop(target, file) {
  cropTarget.value = target
  cropFile.value = file
  cropOpen.value = true
}

function onLogoSelected(event) {
  const file = event.target.files?.[0]
  event.target.value = ''
  if (!file || !canEdit.value) return
  openCrop('org', file)
}

function onLogoDioceseSelected(event) {
  const file = event.target.files?.[0]
  event.target.value = ''
  if (!file || !canEdit.value) return
  openCrop('diocese', file)
}

function onCropCancel() {
  cropFile.value = null
  cropTarget.value = null
}

async function onCropConfirm(file) {
  if (!canEdit.value || !cropTarget.value) return
  const target = cropTarget.value
  cropFile.value = null
  cropTarget.value = null
  uploading.value = true
  try {
    if (target === 'diocese') {
      await branding.uploadLogoDiocese(file)
      innovToast('success', 'Marca', 'Logo da diocese atualizada')
    } else {
      await branding.uploadLogo(file)
      innovToast('success', 'Marca', 'Logo da organização atualizada')
    }
  } catch (e) {
    innovToast('error', 'Marca', e?.response?.data?.message || 'Upload falhou')
  } finally {
    uploading.value = false
  }
}

async function removeLogo() {
  if (!canEdit.value) return
  uploading.value = true
  try {
    await branding.removeLogo()
    innovToast('success', 'Marca', 'Logo da organização removida')
  } catch (e) {
    innovToast('error', 'Marca', e?.response?.data?.message || 'Não foi possível remover')
  } finally {
    uploading.value = false
  }
}

async function removeLogoDiocese() {
  if (!canEdit.value) return
  uploading.value = true
  try {
    await branding.removeLogoDiocese()
    innovToast('success', 'Marca', 'Logo da diocese removida')
  } catch (e) {
    innovToast('error', 'Marca', e?.response?.data?.message || 'Não foi possível remover')
  } finally {
    uploading.value = false
  }
}

const colorFields = [
  { key: 'primary', label: 'Primária', hint: 'Sidebar, botões principais' },
  { key: 'secondary', label: 'Secundária', hint: 'Acentos e CTAs' },
  { key: 'text', label: 'Texto', hint: 'Texto principal em fundos claros' },
  { key: 'text_muted', label: 'Texto suave', hint: 'Labels e texto secundário' },
  { key: 'on_primary', label: 'Sobre a primária', hint: 'Texto/ícones no fundo primário' },
]
</script>

<template>
  <div class="px-4 md:px-6 py-6 max-w-3xl" data-testid="marca-config">
    <h1 class="text-2xl font-semibold" style="color: var(--color-ink)">Marca do sistema</h1>
    <p class="mt-1 text-sm" style="color: var(--color-muted)">
      Cores e logos da organização. Ao enviar, você enquadra e redimensiona a imagem.
    </p>

    <section class="mt-8 space-y-8">
      <div>
        <h2 class="text-sm font-bold uppercase tracking-wider" style="color: var(--color-muted)">
          Logo da organização (direita no PDF)
        </h2>
        <div class="mt-3 flex items-center gap-5">
          <div
            class="h-28 w-28 rounded-2xl flex items-center justify-center overflow-hidden border shrink-0"
            style="border-color: var(--color-line); background: var(--color-surface)"
          >
            <img
              v-if="previewLogo"
              :src="previewLogo"
              alt="Logo atual"
              class="h-full w-full object-cover"
              data-testid="marca-logo-preview"
            />
            <span v-else class="text-xs px-2 text-center" style="color: var(--color-muted)">Sem logo</span>
          </div>
          <div class="flex flex-wrap gap-2">
            <label class="btn btn-primary cursor-pointer" :class="{ 'opacity-50': !canEdit || uploading }">
              {{ uploading ? 'Enviando…' : 'Enviar logo' }}
              <input
                type="file"
                accept="image/png,image/jpeg,image/webp"
                class="sr-only"
                data-testid="marca-logo-input"
                :disabled="!canEdit || uploading"
                @change="onLogoSelected"
              />
            </label>
            <button
              v-if="previewLogo"
              type="button"
              class="btn btn-ghost"
              data-testid="marca-logo-remove"
              :disabled="!canEdit || uploading"
              @click="removeLogo"
            >
              Remover
            </button>
          </div>
        </div>
      </div>

      <div>
        <h2 class="text-sm font-bold uppercase tracking-wider" style="color: var(--color-muted)">
          Logo da diocese (esquerda no PDF)
        </h2>
        <div class="mt-3 flex items-center gap-5">
          <div
            class="h-28 w-28 rounded-2xl flex items-center justify-center overflow-hidden border shrink-0"
            style="border-color: var(--color-line); background: var(--color-surface)"
          >
            <img
              v-if="previewLogoDiocese"
              :src="previewLogoDiocese"
              alt="Logo da diocese"
              class="h-full w-full object-cover"
              data-testid="marca-logo-diocese-preview"
            />
            <span v-else class="text-xs px-2 text-center" style="color: var(--color-muted)">Sem logo</span>
          </div>
          <div class="flex flex-wrap gap-2">
            <label class="btn btn-primary cursor-pointer" :class="{ 'opacity-50': !canEdit || uploading }">
              {{ uploading ? 'Enviando…' : 'Enviar logo' }}
              <input
                type="file"
                accept="image/png,image/jpeg,image/webp"
                class="sr-only"
                data-testid="marca-logo-diocese-input"
                :disabled="!canEdit || uploading"
                @change="onLogoDioceseSelected"
              />
            </label>
            <button
              v-if="previewLogoDiocese"
              type="button"
              class="btn btn-ghost"
              data-testid="marca-logo-diocese-remove"
              :disabled="!canEdit || uploading"
              @click="removeLogoDiocese"
            >
              Remover
            </button>
          </div>
        </div>
      </div>

      <div>
        <h2 class="text-sm font-bold uppercase tracking-wider" style="color: var(--color-muted)">
          Cores
        </h2>
        <div class="mt-3 grid gap-4 sm:grid-cols-2">
          <label
            v-for="field in colorFields"
            :key="field.key"
            class="flex flex-col gap-1.5"
          >
            <span class="text-sm font-medium" style="color: var(--color-ink)">{{ field.label }}</span>
            <span class="text-xs" style="color: var(--color-muted)">{{ field.hint }}</span>
            <div class="flex items-center gap-2">
              <input
                v-model="form[field.key]"
                type="color"
                class="h-10 w-14 cursor-pointer rounded border-0 bg-transparent p-0"
                :disabled="!canEdit"
                :data-testid="`marca-color-${field.key}`"
              />
              <input
                v-model="form[field.key]"
                type="text"
                class="input flex-1 font-mono text-sm"
                pattern="^#[0-9A-Fa-f]{6}$"
                maxlength="7"
                :disabled="!canEdit"
                :data-testid="`marca-hex-${field.key}`"
              />
            </div>
          </label>
        </div>

        <div
          class="mt-5 rounded-xl p-4 flex items-center gap-3"
          style="background: var(--color-primary); color: var(--color-on-primary)"
          data-testid="marca-preview-bar"
        >
          <span class="text-sm font-medium">Prévia sobre a cor primária</span>
        </div>

        <div class="mt-4">
          <button
            type="button"
            class="btn btn-primary"
            data-testid="marca-save"
            :disabled="!canEdit || saving"
            @click="saveCores"
          >
            {{ saving ? 'Salvando…' : 'Salvar cores' }}
          </button>
        </div>
      </div>
    </section>

    <LogoCropModal
      v-model="cropOpen"
      :file="cropFile"
      :title="cropTitle"
      @confirm="onCropConfirm"
      @cancel="onCropCancel"
    />
  </div>
</template>
