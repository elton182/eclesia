<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { homePathForUser } from '@/utils/userRoles'
import { extractApiError } from '@/utils/tenantAuth'
import { appInstallRoute, shouldOfferAppInstall } from '@/utils/siteAccessInstall'

const router = useRouter()
const authStore = useAuthStore()

const currentPassword = ref('')
const password = ref('')
const passwordConfirmation = ref('')
const showPassword = ref(false)
const isLoading = ref(false)
const errorMessage = ref('')

const handleSubmit = async () => {
  errorMessage.value = ''
  if (!currentPassword.value || !password.value || !passwordConfirmation.value) {
    errorMessage.value = 'Preencha todos os campos.'
    return
  }
  if (password.value !== passwordConfirmation.value) {
    errorMessage.value = 'A confirmação não confere com a nova senha.'
    return
  }

  isLoading.value = true
  const result = await authStore.changePassword({
    current_password: currentPassword.value,
    password: password.value,
    password_confirmation: passwordConfirmation.value,
  })
  isLoading.value = false

  if (!result.success) {
    errorMessage.value = result.error || extractApiError({}, 'Não foi possível alterar a senha.')
    return
  }

  const home = homePathForUser(authStore.user)
  if (shouldOfferAppInstall({})) {
    router.replace(appInstallRoute(home))
    return
  }
  router.push(home)
}
</script>

<template>
  <div
    class="min-h-screen flex items-center justify-center px-6 py-10"
    style="background: #FFFDFA"
    data-testid="change-password-view"
  >
    <div class="w-full max-w-[400px]">
      <h1 class="font-serif text-[25px] mb-1.5" style="color: #2A1418">Trocar senha</h1>
      <p class="text-[13.5px] mb-7" style="color: rgba(42, 20, 24, 0.62)">
        Por segurança, defina uma nova senha antes de continuar.
      </p>

      <form class="space-y-0" @submit.prevent="handleSubmit">
        <div
          v-if="errorMessage"
          class="p-3 text-sm rounded-lg mb-4"
          style="background: #F6E9EB; color: #8A2436"
          role="alert"
          data-testid="change-password-error"
        >
          {{ errorMessage }}
        </div>

        <label for="current-password" class="fld">Senha atual (temporária)</label>
        <input
          id="current-password"
          v-model="currentPassword"
          type="password"
          class="input mb-[18px]"
          required
          autocomplete="current-password"
          data-testid="change-password-current"
        >

        <label for="new-password" class="fld">Nova senha</label>
        <div
          class="flex items-center mb-[18px]"
          style="border: 1px solid rgba(42, 20, 24, 0.16); border-radius: 8px; background: #fff"
        >
          <input
            id="new-password"
            v-model="password"
            :type="showPassword ? 'text' : 'password'"
            class="flex-1 min-w-0 border-0 outline-none bg-transparent py-3 px-3.5 text-[14px]"
            required
            autocomplete="new-password"
            data-testid="change-password-new"
          >
          <button
            type="button"
            class="px-3.5 text-[12.5px] font-medium shrink-0"
            style="color: #8A2436"
            @click="showPassword = !showPassword"
          >
            {{ showPassword ? 'ocultar' : 'mostrar' }}
          </button>
        </div>

        <label for="confirm-password" class="fld">Confirmar nova senha</label>
        <input
          id="confirm-password"
          v-model="passwordConfirmation"
          type="password"
          class="input mb-[22px]"
          required
          autocomplete="new-password"
          data-testid="change-password-confirm"
        >

        <button
          type="submit"
          class="w-full border-0 rounded-lg py-3.5 text-[14.5px] font-medium cursor-pointer disabled:opacity-60"
          style="background: #6B1C2B; color: #FFFDFA"
          :disabled="isLoading"
          data-testid="change-password-submit"
        >
          {{ isLoading ? 'Salvando…' : 'Salvar nova senha' }}
        </button>
      </form>
    </div>
  </div>
</template>
