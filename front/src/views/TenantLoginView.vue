<script setup>
import { ref, computed } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../stores/auth'
import { validateTenantLoginForm } from '../utils/tenantAuth'

const router = useRouter()
const authStore = useAuthStore()
const tenant = ref('')
const email = ref('')
const password = ref('')
const showPassword = ref(false)
const isLoading = ref(false)
const errorMessage = ref('')

const siteLink = computed(() => {
  const slug = tenant.value.trim()
  return slug ? `/site/${slug}` : '/'
})

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
    router.push('/inicio')
  } else {
    errorMessage.value = result.error
  }

  isLoading.value = false
}
</script>

<template>
  <div
    class="min-h-screen grid lg:grid-cols-2"
    data-testid="tenant-login-portico"
    style="background: #FFFDFA"
  >
    <!-- Painel vinho -->
    <aside
      class="hidden lg:flex flex-col justify-between px-10 py-11 min-h-screen"
      style="background: #4E1220"
      aria-hidden="true"
    >
      <div class="flex items-center gap-2.5">
        <div
          class="w-[30px] h-[30px] rounded-full border flex items-center justify-center font-serif text-[14px] font-medium"
          style="border-color: #C88A5E; color: #F0D8C2"
        >
          E
        </div>
        <div class="font-serif text-[17px] font-medium tracking-wide" style="color: #FFFDFA">
          Eclesias
        </div>
      </div>

      <div>
        <h1
          class="font-serif font-normal text-[34px] leading-[1.18] mb-3.5 text-pretty"
          style="color: #FFFDFA"
        >
          A gestão da sua paróquia, em um só lugar.
        </h1>
        <p class="text-[14px] leading-[1.65] max-w-[330px]" style="color: rgba(255, 253, 250, 0.72)">
          Comunidades, pastorais, ECC, finanças e o site público da paróquia.
        </p>
      </div>

      <div class="flex gap-2">
        <div class="h-[3px] w-[34px] rounded-sm" style="background: #C88A5E" />
        <div class="h-[3px] w-2.5 rounded-sm" style="background: rgba(255, 253, 250, 0.28)" />
        <div class="h-[3px] w-2.5 rounded-sm" style="background: rgba(255, 253, 250, 0.28)" />
      </div>
    </aside>

    <!-- Formulário -->
    <div class="flex flex-col justify-center px-6 py-10 sm:px-11 lg:px-11">
      <div class="w-full max-w-[380px] mx-auto" data-testid="tenant-login-card">
        <div class="lg:hidden flex flex-col items-center gap-1.5 mb-8">
          <div
            class="w-11 h-11 rounded-full flex items-center justify-center font-serif text-[22px] font-medium"
            style="background: #6B1C2B; color: #F0D8C2"
          >
            E
          </div>
          <div class="font-serif text-[19px] font-medium" style="color: #2A1418">Eclesias</div>
        </div>

        <h2 class="font-serif text-[25px] leading-tight mb-1.5" style="color: #2A1418">Entrar</h2>
        <p class="text-[13.5px] leading-relaxed mb-7" style="color: rgba(42, 20, 24, 0.62)">
          Bem-vindo de volta.
        </p>

        <form class="space-y-0" @submit.prevent="handleLogin">
          <div
            v-if="errorMessage"
            class="p-3 text-sm rounded-lg mb-4"
            style="background: #F6E9EB; color: #8A2436"
            role="alert"
            data-testid="tenant-login-error"
          >
            {{ errorMessage }}
          </div>

          <label for="tenant" class="fld">Organização</label>
          <div
            class="flex items-center overflow-hidden mb-[18px]"
            style="
              border: 1px solid rgba(42, 20, 24, 0.16);
              border-radius: 8px;
              background: #F7F4EF;
            "
          >
            <span
              class="pl-3 pr-0.5 text-[13px] font-mono shrink-0"
              style="color: rgba(42, 20, 24, 0.62)"
            >eclesias.com.br/</span>
            <input
              id="tenant"
              v-model="tenant"
              type="text"
              class="flex-1 min-w-0 border-0 outline-none bg-transparent py-3 pr-3 font-mono text-[13px] font-medium"
              style="color: #2A1418"
              placeholder="sao-jose-operario"
              required
              autocomplete="organization"
              data-testid="tenant-login-org"
            />
          </div>

          <label for="email" class="fld">E-mail</label>
          <input
            id="email"
            v-model="email"
            type="email"
            class="input mb-[18px]"
            placeholder="seu@email.com"
            required
            data-testid="tenant-login-email"
          />

          <label for="password" class="fld">Senha</label>
          <div
            class="flex items-center mb-[22px]"
            style="border: 1px solid rgba(42, 20, 24, 0.16); border-radius: 8px; background: #fff"
          >
            <input
              id="password"
              v-model="password"
              :type="showPassword ? 'text' : 'password'"
              class="flex-1 min-w-0 border-0 outline-none bg-transparent py-3 px-3.5 text-[14px]"
              style="color: #2A1418"
              placeholder="••••••••"
              required
              data-testid="tenant-login-password"
            />
            <button
              type="button"
              class="px-3.5 text-[12.5px] font-medium shrink-0"
              style="color: #8A2436"
              @click="showPassword = !showPassword"
            >
              {{ showPassword ? 'ocultar' : 'mostrar' }}
            </button>
          </div>

          <button
            type="submit"
            class="w-full border-0 rounded-lg py-3.5 text-[14.5px] font-medium cursor-pointer disabled:opacity-60"
            style="background: #6B1C2B; color: #FFFDFA"
            :disabled="isLoading"
            data-testid="tenant-login-submit"
          >
            {{ isLoading ? 'Entrando…' : 'Entrar' }}
          </button>
        </form>

        <div class="flex justify-between mt-4 text-[12.5px]">
          <a href="#" class="no-underline" style="color: #8A2436" @click.prevent>Esqueci minha senha</a>
          <router-link :to="siteLink" class="no-underline" style="color: rgba(42, 20, 24, 0.62)">
            Ver o site da paróquia
          </router-link>
        </div>

        <div
          class="mt-[30px] pt-[18px] text-[12px] leading-relaxed"
          style="border-top: 1px solid rgba(42, 20, 24, 0.1); color: rgba(42, 20, 24, 0.62)"
        >
          Primeira vez?
          <a href="#" class="no-underline" style="color: #8A2436" @click.prevent>Cadastrar minha paróquia</a>
        </div>

        <p class="text-center text-[12px] mt-6" style="color: rgba(42, 20, 24, 0.62)">
          <router-link to="/admin/login" class="no-underline" data-testid="link-admin-login" style="color: rgba(42, 20, 24, 0.62)">
            Acesso administrativo da plataforma
          </router-link>
        </p>
      </div>
    </div>
  </div>
</template>
