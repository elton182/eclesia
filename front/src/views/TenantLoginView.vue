<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../stores/auth'
import { validateTenantLoginForm } from '../utils/tenantAuth'
import logoUrl from '../assets/logo.png'

const router = useRouter()
const authStore = useAuthStore()
const tenant = ref('')
const email = ref('')
const password = ref('')
const showPassword = ref(false)
const isLoading = ref(false)
const errorMessage = ref('')

const handleLogin = async () => {
  const validation = validateTenantLoginForm({
    tenant: tenant.value,
    email: email.value,
    password: password.value,
  })
  if (!validation.ok) {
    errorMessage.value = validation.error
    return
  }

  isLoading.value = true
  errorMessage.value = ''

  const result = await authStore.login(tenant.value, email.value, password.value)

  if (result.success) {
    router.push('/usuarios')
  } else {
    errorMessage.value = result.error
  }

  isLoading.value = false
}
</script>

<template>
  <div class="card p-8 space-y-6" data-testid="tenant-login-card">
    <div class="text-center space-y-3">
      <img :src="logoUrl" alt="Eclésia" class="mx-auto h-28 w-auto object-contain" />
      <p class="text-sm" style="color: var(--color-muted)">
        Acesso à sua organização
      </p>
    </div>

    <form class="space-y-4" @submit.prevent="handleLogin">
      <div
        v-if="errorMessage"
        class="p-3 text-sm rounded-xl bg-red-50 text-red-800"
        role="alert"
        data-testid="tenant-login-error"
      >
        {{ errorMessage }}
      </div>

      <div>
        <label for="tenant" class="fld">Organização</label>
        <input
          id="tenant"
          v-model="tenant"
          type="text"
          class="input"
          placeholder="slug, nome ou apelido"
          required
          autocomplete="organization"
          data-testid="tenant-login-org"
        />
      </div>

      <div>
        <label for="email" class="fld">E-mail</label>
        <input
          id="email"
          v-model="email"
          type="email"
          class="input"
          placeholder="seu@email.com"
          required
          data-testid="tenant-login-email"
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
            data-testid="tenant-login-password"
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
        data-testid="tenant-login-submit"
      >
        {{ isLoading ? 'Entrando…' : 'Entrar' }}
      </button>
    </form>

    <p class="text-center text-xs" style="color: var(--color-muted)">
      <router-link to="/admin/login" class="underline" data-testid="link-admin-login">
        Acesso administrativo da plataforma
      </router-link>
    </p>
  </div>
</template>
