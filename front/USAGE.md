# Guia de Uso - Innov Base Front como Pacote NPM

Este guia mostra como usar o pacote `innov-front` em suas aplicações.

## Instalação

```bash
npm install innov-front
```

## Configuração Básica

### 1. Importar Estilos

Primeiro, você precisa importar os estilos CSS do pacote:

```javascript
// No seu main.js ou main.ts
import 'innov-front/styles'
```

### 2. Registrar o Plugin (Opcional)

Se você quiser registrar todos os componentes globalmente:

```javascript
import { createApp } from 'vue'
import { createPinia } from 'pinia'
import InnovFront from 'innov-front'
import 'innov-front/styles'

const app = createApp(App)
app.use(createPinia())
app.use(InnovFront) // Todos os componentes ficam disponíveis globalmente

app.mount('#app')
```

### 3. Usar Componentes Individualmente (Recomendado)

Para melhor tree-shaking e controle, importe apenas os componentes que você precisa:

```vue
<script setup>
import { InnovPanel, InnovRow, InnovCol, DataTable } from 'innov-front'
import { useToastStore } from 'innov-front'
</script>

<template>
  <InnovPanel>
    <InnovRow>
      <InnovCol :cols="12">
        <h2>Minha Página</h2>
        <DataTable :data="tableData" :columns="columns" />
      </InnovCol>
    </InnovRow>
  </InnovPanel>
</template>
```

## Exemplos de Uso

### Usando Componentes de Formulário

```vue
<script setup>
import { ref } from 'vue'
import { FormWrapper, FormRow, FormField, TextInput, SelectSearch } from 'innov-front'

const formData = ref({
  nome: '',
  email: '',
  tipo: ''
})

const tipos = [
  { value: '1', label: 'Tipo 1' },
  { value: '2', label: 'Tipo 2' }
]
</script>

<template>
  <FormWrapper>
    <FormRow>
      <FormField label="Nome">
        <TextInput v-model="formData.nome" />
      </FormField>
    </FormRow>
    <FormRow>
      <FormField label="Email">
        <TextInput v-model="formData.email" type="email" />
      </FormField>
    </FormRow>
    <FormRow>
      <FormField label="Tipo">
        <SelectSearch 
          v-model="formData.tipo" 
          :options="tipos"
        />
      </FormField>
    </FormRow>
  </FormWrapper>
</template>
```

### Usando Stores

```vue
<script setup>
import { onMounted } from 'vue'
import { useAuthStore, useToastStore } from 'innov-front'

const authStore = useAuthStore()
const toastStore = useToastStore()

onMounted(async () => {
  const isAuthenticated = await authStore.checkAuth()
  if (isAuthenticated) {
    toastStore.showToast('success', 'Bem-vindo', 'Você está autenticado!')
  }
})
</script>
```

### Usando o Plugin de Toast

```vue
<script setup>
import { innovToast } from 'innov-front'

const handleSuccess = () => {
  innovToast('success', 'Sucesso!', 'Operação realizada com sucesso')
}

const handleError = () => {
  innovToast('error', 'Erro', 'Algo deu errado', 5000)
}
</script>
```

### Usando o Service API

```javascript
import { api } from 'innov-front'

// Fazer requisições GET
const getUsers = async () => {
  try {
    const response = await api.get('/users')
    return response.data
  } catch (error) {
    console.error('Erro ao buscar usuários:', error)
  }
}

// Fazer requisições POST
const createUser = async (userData) => {
  try {
    const response = await api.post('/users', userData)
    return response.data
  } catch (error) {
    console.error('Erro ao criar usuário:', error)
  }
}
```

### Usando Layouts

```vue
<script setup>
import { AdminLayout, AuthLayout } from 'innov-front'
</script>

<template>
  <!-- Para páginas administrativas -->
  <AdminLayout>
    <router-view />
  </AdminLayout>

  <!-- Para páginas de autenticação -->
  <AuthLayout>
    <router-view />
  </AuthLayout>
</template>
```

## Configuração do Tailwind CSS

Se você já usa Tailwind CSS no seu projeto, você pode precisar configurar o `tailwind.config.js` para incluir os estilos do pacote:

```javascript
module.exports = {
  content: [
    './index.html',
    './src/**/*.{vue,js,ts,jsx,tsx}',
    './node_modules/innov-front/**/*.{vue,js,ts,jsx,tsx}'
  ],
  // ... resto da configuração
}
```

## Dependências Peer

Certifique-se de que as seguintes dependências estão instaladas no seu projeto:

```bash
npm install vue@^3.5.13 vue-router@^4.5.0 pinia@^3.0.1 axios@^1.8.4
```

## Variáveis de Ambiente

O service `api` usa a variável de ambiente `VITE_API_URL`. Configure no seu `.env`:

```env
VITE_API_URL=http://localhost:8000
```

## Build e Publicação

Para fazer o build da biblioteca:

```bash
npm run build:lib
```

Para publicar no npm:

```bash
npm publish
```

## Suporte

Para mais informações, consulte o README.md principal do projeto.
