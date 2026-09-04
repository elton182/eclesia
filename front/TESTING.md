# Guia de Teste do Pacote Antes de Publicar

Este guia mostra como testar o pacote `innov-front` em outros projetos antes de publicar no npm.

## Opções de Teste

### 1. Instalar Diretamente do Git (Recomendado)

Esta é a forma mais próxima de como funcionará após publicar no npm.

#### GitHub/GitLab/Bitbucket

```bash
# HTTPS
npm install git+https://gitlab.com/innovareti/innov-front.git

# SSH
npm install git+ssh://git@gitlab.com:innovareti/innov-front.git

# Branch específica
npm install git+https://gitlab.com/innovareti/innov-front.git#develop

# Tag específica
npm install git+https://gitlab.com/innovareti/innov-front.git#v1.0.0

# Commit específico
npm install git+https://gitlab.com/innovareti/innov-front.git#abc123def
```

#### No package.json

```json
{
  "dependencies": {
    "innov-front": "git+https://gitlab.com/innovareti/innov-front.git"
  }
}
```

### 2. Usar npm link (Desenvolvimento Local)

Ideal para desenvolvimento ativo, onde mudanças no pacote são refletidas imediatamente.

#### Passo 1: Criar o link no pacote

```bash
cd /home/elton/projetos/innov-front/front-base
npm link
```

#### Passo 2: Usar o link no projeto de teste

```bash
cd /caminho/para/seu-projeto-teste
npm link innov-front
```

#### Desfazer o link

```bash
# No projeto de teste
npm unlink innov-front

# No pacote
npm unlink
```

**Vantagens:**
- Mudanças no pacote são refletidas imediatamente
- Não precisa reinstalar após cada mudança
- Ideal para desenvolvimento ativo

**Desvantagens:**
- Pode causar problemas com dependências duplicadas
- Requer cuidado com peer dependencies

### 3. Instalar de Caminho Local

Instala o pacote diretamente de um caminho no sistema de arquivos.

#### Caminho Relativo

```bash
# No projeto de teste, no package.json:
{
  "dependencies": {
    "innov-front": "file:../innov-front/front-base"
  }
}

npm install
```

#### Caminho Absoluto

```bash
# No projeto de teste, no package.json:
{
  "dependencies": {
    "innov-front": "file:/home/elton/projetos/innov-front/front-base"
  }
}

npm install
```

**Vantagens:**
- Simples e direto
- Funciona como uma dependência normal

**Desvantagens:**
- Precisa reinstalar após mudanças significativas
- Caminhos absolutos não funcionam bem em equipes

### 4. Usar yarn (Alternativa)

Se você usa yarn, pode usar as mesmas opções:

```bash
# Do Git
yarn add git+https://gitlab.com/innovareti/innov-front.git

# Do caminho local
yarn add file:../innov-front/front-base

# Link
yarn link
```

## Exemplo de Uso Após Instalação

Independente do método escolhido, o uso é o mesmo:

```javascript
// main.js ou main.ts
import { createApp } from 'vue'
import { createPinia } from 'pinia'
import InnovFront from 'innov-front'
import 'innov-front/styles'

const app = createApp(App)
app.use(createPinia())
app.use(InnovFront)

app.mount('#app')
```

```vue
<!-- Componente.vue -->
<script setup>
import { InnovPanel, InnovRow, InnovCol, DataTable } from 'innov-front'
import { useToastStore } from 'innov-front'
</script>

<template>
  <InnovPanel>
    <InnovRow>
      <InnovCol :cols="12">
        <DataTable :data="tableData" />
      </InnovCol>
    </InnovRow>
  </InnovPanel>
</template>
```

## Configuração do Vite no Projeto de Teste

Se o projeto de teste usa Vite, você pode precisar configurar o alias:

```javascript
// vite.config.js
import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
import path from 'path'

export default defineConfig({
  plugins: [vue()],
  resolve: {
    alias: {
      '@': path.resolve(__dirname, './src'),
    },
  },
  optimizeDeps: {
    include: ['innov-front'],
  },
})
```

## Configuração do Tailwind CSS

Se o projeto de teste usa Tailwind CSS, adicione o pacote ao content:

```javascript
// tailwind.config.js
module.exports = {
  content: [
    './index.html',
    './src/**/*.{vue,js,ts,jsx,tsx}',
    './node_modules/innov-front/**/*.{vue,js,ts,jsx,tsx}',
  ],
  // ... resto da configuração
}
```

## Troubleshooting

### Erro: "Cannot find module 'innov-front'"

- Verifique se o pacote foi instalado: `npm list innov-front`
- Verifique se o caminho está correto (se usando file:)
- Tente remover `node_modules` e reinstalar: `rm -rf node_modules && npm install`

### Erro: "Peer dependencies not met"

Certifique-se de instalar as peer dependencies:

```bash
npm install vue@^3.5.13 vue-router@^4.5.0 pinia@^3.0.1 axios@^1.8.4
```

### Estilos não estão sendo aplicados

Certifique-se de importar os estilos:

```javascript
import 'innov-front/styles'
```

### Componentes não aparecem

- Verifique se o plugin foi registrado: `app.use(InnovFront)`
- Ou importe os componentes individualmente
- Verifique o console do navegador para erros

## Recomendações

1. **Para desenvolvimento ativo**: Use `npm link`
2. **Para testar versão específica**: Use instalação do Git com branch/tag
3. **Para simular produção**: Use instalação do Git da branch main/master
4. **Para testes rápidos**: Use caminho local com `file:`

## Próximo Passo

Após testar e validar que tudo funciona corretamente, você pode publicar no npm:

```bash
npm publish
```
