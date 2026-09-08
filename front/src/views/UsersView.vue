<script setup>
import { ref, onMounted } from 'vue'
import api from '@/services/api'
import { innovToast } from '@/plugins/toast'

const users = ref([])
const roles = ref([])
const igrejas = ref([])
const loading = ref(false)
const mode = ref('list')
const saving = ref(false)
const form = ref({
  id: null,
  name: '',
  email: '',
  password: '',
  is_active: true,
})
const roleForm = ref({
  userId: null,
  userName: '',
  role: 'secretaria',
  igreja_id: '',
})

const load = async () => {
  loading.value = true
  try {
    const [usersRes, rolesRes, igrejasRes] = await Promise.all([
      api.get('/users'),
      api.get('/roles'),
      api.get('/igrejas'),
    ])
    users.value = usersRes.data.data || usersRes.data || []
    roles.value = rolesRes.data.data || rolesRes.data || []
    igrejas.value = igrejasRes.data.data || igrejasRes.data || []
  } catch (e) {
    innovToast('error', 'Erro', e.response?.data?.message || 'Falha ao listar usuários')
  } finally {
    loading.value = false
  }
}

const openCreate = () => {
  form.value = { id: null, name: '', email: '', password: '', is_active: true }
  mode.value = 'form'
}

const openEdit = (user) => {
  form.value = {
    id: user.id,
    name: user.name,
    email: user.email,
    password: '',
    is_active: user.is_active !== false,
  }
  mode.value = 'form'
}

const openRoles = (user) => {
  roleForm.value = {
    userId: user.id,
    userName: user.name,
    role: 'secretaria',
    igreja_id: igrejas.value[0]?.id || '',
  }
  mode.value = 'roles'
}

const save = async () => {
  saving.value = true
  try {
    if (form.value.id) {
      const payload = {
        name: form.value.name,
        email: form.value.email,
        is_active: form.value.is_active,
      }
      if (form.value.password) payload.password = form.value.password
      await api.patch(`/users/${form.value.id}`, payload)
      innovToast('success', 'OK', 'Usuário atualizado')
    } else {
      await api.post('/users', {
        name: form.value.name,
        email: form.value.email,
        password: form.value.password,
        is_active: form.value.is_active,
      })
      innovToast('success', 'OK', 'Usuário criado')
    }
    mode.value = 'list'
    await load()
  } catch (e) {
    const msg =
      e.response?.data?.message ||
      Object.values(e.response?.data?.errors || {}).flat().join(' ') ||
      'Falha ao salvar'
    innovToast('error', 'Erro', msg)
  } finally {
    saving.value = false
  }
}

const remove = async (user) => {
  if (!confirm(`Remover o usuário "${user.name}"?`)) return
  try {
    await api.delete(`/users/${user.id}`)
    innovToast('success', 'OK', 'Usuário removido')
    await load()
  } catch (e) {
    innovToast('error', 'Erro', e.response?.data?.message || 'Falha ao remover')
  }
}

const assignRole = async () => {
  saving.value = true
  try {
    const payload = { role: roleForm.value.role }
    if (roleForm.value.role !== 'admin-tenant') {
      payload.igreja_id = roleForm.value.igreja_id
    }
    await api.post(`/users/${roleForm.value.userId}/roles`, payload)
    innovToast('success', 'OK', 'Papel atribuído')
    mode.value = 'list'
    await load()
  } catch (e) {
    const msg =
      e.response?.data?.message ||
      Object.values(e.response?.data?.errors || {}).flat().join(' ') ||
      'Falha ao atribuir papel'
    innovToast('error', 'Erro', msg)
  } finally {
    saving.value = false
  }
}

const removeRole = async (user, role) => {
  try {
    await api.delete(`/users/${user.id}/roles`, {
      data: { role: role.name, igreja_id: role.igreja_id },
    })
    innovToast('success', 'OK', 'Papel removido')
    await load()
  } catch (e) {
    innovToast('error', 'Erro', e.response?.data?.message || 'Falha ao remover papel')
  }
}

onMounted(load)
</script>

<template>
  <div data-testid="users-page">
    <div class="mb-6">
      <p class="page-eyebrow">Organização</p>
      <h2 class="text-3xl" style="color: var(--color-primary)">Usuários</h2>
      <p class="mt-1 text-[14.5px]" style="color: var(--color-muted)">
        Cadastre usuários e atribua papéis por igreja.
      </p>
    </div>

    <div v-if="mode === 'list'" class="space-y-4">
      <button class="btn btn-primary" data-testid="user-new" @click="openCreate">
        Novo usuário
      </button>

      <div class="card overflow-hidden">
        <div v-if="loading" class="p-8 text-center" style="color: var(--color-muted)">Carregando…</div>
        <div v-else-if="!users.length" class="p-8 text-center" style="color: var(--color-muted)">
          Nenhum usuário encontrado.
        </div>
        <div v-else class="divide-y" style="border-color: var(--color-line)">
          <div
            v-for="user in users"
            :key="user.id"
            class="px-5 py-4 space-y-2"
            data-testid="user-row"
          >
            <div class="flex items-center gap-4 flex-wrap">
              <div class="flex-1 min-w-0">
                <div class="font-semibold">{{ user.name }}</div>
                <div class="text-sm" style="color: var(--color-muted)">{{ user.email }}</div>
              </div>
              <span
                class="text-xs font-semibold px-2 py-1 rounded-lg"
                :class="user.is_active ? 'bg-green-50 text-green-800' : 'bg-red-50 text-red-800'"
              >
                {{ user.is_active ? 'Ativo' : 'Inativo' }}
              </span>
              <div class="flex gap-2 flex-wrap">
                <button class="btn btn-ghost" @click="openRoles(user)">Papéis</button>
                <button class="btn btn-ghost" @click="openEdit(user)">Editar</button>
                <button class="btn btn-ghost text-red-700" @click="remove(user)">Excluir</button>
              </div>
            </div>
            <div v-if="user.roles?.length" class="flex flex-wrap gap-2">
              <button
                v-for="(role, idx) in user.roles"
                :key="`${role.name}-${role.igreja_id}-${idx}`"
                type="button"
                class="text-xs px-2 py-1 rounded-lg bg-[var(--color-bg)] border"
                style="border-color: var(--color-line)"
                :title="'Clique para remover'"
                @click="removeRole(user, role)"
              >
                {{ role.name }}
                <span v-if="role.igreja_id" style="color: var(--color-muted)"> · igreja</span>
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div v-else-if="mode === 'form'" class="card p-6 max-w-lg">
      <h3 class="text-xl mb-4">{{ form.id ? 'Editar usuário' : 'Novo usuário' }}</h3>
      <form class="space-y-4" @submit.prevent="save">
        <div>
          <label class="fld" for="user-name">Nome</label>
          <input id="user-name" v-model="form.name" class="input" required data-testid="user-name" />
        </div>
        <div>
          <label class="fld" for="user-email">E-mail</label>
          <input
            id="user-email"
            v-model="form.email"
            type="email"
            class="input"
            required
            data-testid="user-email"
          />
        </div>
        <div>
          <label class="fld" for="user-password">
            Senha {{ form.id ? '(deixe em branco para manter)' : '' }}
          </label>
          <input
            id="user-password"
            v-model="form.password"
            type="password"
            class="input"
            :required="!form.id"
            data-testid="user-password"
          />
        </div>
        <label class="flex items-center gap-2 text-sm">
          <input v-model="form.is_active" type="checkbox" />
          Ativo
        </label>
        <div class="flex gap-2">
          <button type="submit" class="btn btn-primary" :disabled="saving" data-testid="user-save">
            {{ saving ? 'Salvando…' : 'Salvar' }}
          </button>
          <button type="button" class="btn btn-ghost" @click="mode = 'list'">Cancelar</button>
        </div>
      </form>
    </div>

    <div v-else class="card p-6 max-w-lg">
      <h3 class="text-xl mb-1">Papéis</h3>
      <p class="text-sm mb-4" style="color: var(--color-muted)">{{ roleForm.userName }}</p>
      <form class="space-y-4" @submit.prevent="assignRole">
        <div>
          <label class="fld" for="role-name">Papel</label>
          <select id="role-name" v-model="roleForm.role" class="input" data-testid="role-select">
            <option v-for="role in roles" :key="role.name" :value="role.name">
              {{ role.name }}
            </option>
          </select>
        </div>
        <div v-if="roleForm.role !== 'admin-tenant'">
          <label class="fld" for="role-igreja">Igreja</label>
          <select
            id="role-igreja"
            v-model="roleForm.igreja_id"
            class="input"
            required
            data-testid="role-igreja"
          >
            <option disabled value="">Selecione</option>
            <option v-for="igreja in igrejas" :key="igreja.id" :value="igreja.id">
              {{ igreja.nome }}
            </option>
          </select>
        </div>
        <div class="flex gap-2">
          <button type="submit" class="btn btn-primary" :disabled="saving" data-testid="role-assign">
            {{ saving ? 'Salvando…' : 'Atribuir' }}
          </button>
          <button type="button" class="btn btn-ghost" @click="mode = 'list'">Cancelar</button>
        </div>
      </form>
    </div>
  </div>
</template>
