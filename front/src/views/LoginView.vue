<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthAdminStore } from '../stores/authAdmin'
import logoUrl from '../assets/logo.png'

const router = useRouter()
const authStore = useAuthAdminStore()
const email = ref('')
const password = ref('')
const showPassword = ref(false)
const isLoading = ref(false)
const errorMessage = ref('')

const handleLogin = async () => {
  if (!email.value || !password.value) {
    errorMessage.value = 'Preencha e-mail e senha.'
    return
  }

  isLoading.value = true
  errorMessage.value = ''

  const result = await authStore.login(email.value, password.value)

  if (result.success) {
    router.push('/tenants')
  } else {
    errorMessage.value = result.error
  }

  isLoading.value = false
}
</script>

<template>
  <div class="card p-8 space-y-6" data-testid="login-card">
    <div class="text-center space-y-3">
      <img :src="logoUrl" alt="Eclésia" class="mx-auto h-28 w-auto object-contain" />
      <p class="text-sm" style="color: var(--color-muted)">
        Acesso administrativo da plataforma
      </p>
    </div>

    <form class="space-y-4" @submit.prevent="handleLogin">
      <div
        v-if="errorMessage"
        class="p-3 text-sm rounded-xl bg-red-50 text-red-800"
        role="alert"
      >
        {{ errorMessage }}
      </div>

      <div>
        <label for="email" class="fld">E-mail</label>
        <input
          id="email"
          v-model="email"
          type="email"
          class="input"
          placeholder="admin@eclesia.local"
          required
          data-testid="login-email"
        />
      </div>

      <div>
        <label for="password" class="fld">Senha</label>
        <div class="relative">
          <input
            id="password"
            v-model="password"
            :type="showPassword ? 'text' : 'password'"
            class="input pr-12"
            placeholder="••••••••"
            required
            data-testid="login-password"
          />
          <button
            type="button"
            class="absolute inset-y-0 right-0 px-3 text-sm"
            style="color: var(--color-muted)"
            @click="showPassword = !showPassword"
          >
            {{ showPassword ? 'Ocultar' : 'Ver' }}
          </button>
        </div>
      </div>

      <button
        type="submit"
        class="btn btn-primary w-full justify-center"
        :disabled="isLoading"
        data-testid="login-submit"
      >
        {{ isLoading ? 'Entrando…' : 'Entrar' }}
      </button>
    </form>
  </div>
</template>
