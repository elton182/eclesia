# Innov Front

Um projeto base front-end moderno desenvolvido com Vue 3, Tailwind CSS e Vite, projetado para servir como ponto de partida para sistemas administrativos e dashboards.

## 📦 Uso como Pacote NPM

Este projeto também pode ser usado como uma biblioteca npm compartilhada entre várias aplicações.

### Instalação

```bash
npm install innov-front
```

### Uso Básico

#### 1. Importar e Registrar o Plugin

```javascript
import { createApp } from 'vue'
import { createPinia } from 'pinia'
import InnovFront from 'innov-front'
import 'innov-front/styles' // Importar estilos

const app = createApp(App)
app.use(createPinia())
app.use(InnovFront) // Registrar todos os componentes globalmente

app.mount('#app')
```

#### 2. Importar Componentes Individualmente

```vue
<script setup>
import { InnovPanel, InnovRow, InnovCol, DataTable } from 'innov-front'
import { useToastStore, useAuthStore } from 'innov-front'
import { innovToast } from 'innov-front'
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

#### 3. Usar Stores

```javascript
import { useAuthStore, useToastStore } from 'innov-front'

const authStore = useAuthStore()
const toastStore = useToastStore()

// Usar o store
authStore.login(credentials)
toastStore.showToast('success', 'Sucesso', 'Operação realizada com sucesso!')
```

#### 4. Usar Plugins

```javascript
import { innovToast } from 'innov-front'

// Mostrar toast
innovToast('success', 'Título', 'Mensagem de sucesso')
innovToast('error', 'Erro', 'Algo deu errado', 3000)
```

#### 5. Usar Services

```javascript
import { api } from 'innov-front'

// Fazer requisições
const response = await api.get('/users')
```

### Componentes Disponíveis

#### Componentes Base
- `InnovCol`, `InnovRow`, `InnovPanel`, `InnovView`
- `InnovStatusBadge`, `InnovStatCard`, `InnovProgressBar`, `ProgressCircle`
- `InnovSpinner`, `InnovToast`, `InnovConfirm`
- `InnovModal`, `InnovCalendar`, `InnovCrud`
- `ColorBadge`

#### Componentes de Formulário
- `TextInput`, `TextAreaInput`, `DateInput`
- `SelectSearch`, `SelectSearchServer`, `CheckboxGroup`, `RadioGroup`
- `ToggleSwitch`, `RangeSlider`
- `RichTextEditor`, `ButtonsActions`
- `FormRow`, `FormWrapper`, `FormElements`, `InputTextGroup`

#### Componentes de Dados
- `DataTable`

#### Componentes de Layout
- `Breadcrumb`, `Footer`, `Sidebar`, `TopNavbar`

#### Layouts
- `AdminLayout`, `AuthLayout`

### Stores Disponíveis

- `useAuthStore` - Gerenciamento de autenticação
- `useAuthAdminStore` - Autenticação administrativa
- `useToastStore` - Gerenciamento de notificações toast
- `useCounterStore` - Store de exemplo

### Testar Antes de Publicar

Você pode testar o pacote em outros projetos antes de publicar no npm usando uma das opções abaixo:

#### Opção 1: Instalar do Git (Recomendado)

```bash
# No projeto que vai usar o pacote
npm install git+https://gitlab.com/innovareti/innov-front.git
# ou
npm install git+ssh://git@gitlab.com:innovareti/innov-front.git

# Para uma branch específica
npm install git+https://gitlab.com/innovareti/innov-front.git#nome-da-branch

# Para um commit específico
npm install git+https://gitlab.com/innovareti/innov-front.git#commit-hash
```

#### Opção 2: Instalar de Caminho Local (npm link)

```bash
# 1. No diretório do pacote (innov-front)
cd /caminho/para/innov-front/front-base
npm link

# 2. No projeto que vai usar o pacote
cd /caminho/para/seu-projeto
npm link innov-front
```

#### Opção 3: Instalar de Caminho Relativo/Absoluto

```bash
# No projeto que vai usar o pacote, no package.json:
{
  "dependencies": {
    "innov-front": "file:../innov-front/front-base"
    // ou caminho absoluto: "file:/home/elton/projetos/innov-front/front-base"
  }
}

# Depois execute
npm install
```

### Publicar no NPM

O pacote publica o código-fonte (`src`) diretamente, permitindo que cada projeto faça seu próprio build e otimização:

1. Atualize a versão no `package.json`
2. Publique diretamente: `npm publish`

**Nota:** Não é necessário fazer build antes de publicar. Cada projeto que usar o pacote fará seu próprio build através do Vite/Webpack, permitindo melhor tree-shaking e otimizações específicas.

### Dependências Peer

Este pacote requer as seguintes dependências como peer dependencies (devem ser instaladas na aplicação que usa o pacote):

- `vue` ^3.5.13
- `vue-router` ^4.5.0
- `pinia` ^3.0.1
- `axios` ^1.8.4

Certifique-se de que essas dependências estão instaladas no seu projeto.

---

## 🚀 Como Iniciar um Novo Projeto

## 🚀 Como Iniciar um Novo Projeto

### 1. Clonar o Template Base

```bash
# Clone o repositório base
git clone https://github.com/seu-usuario/innov-front.git meu-novo-projeto

# Entre no diretório
cd meu-novo-projeto

# Remova o git existente
rm -rf .git

# Inicialize um novo repositório
git init
```

### 2. Configurar o Projeto

```bash
# Instale as dependências
npm install

# Inicie o servidor de desenvolvimento
npm run dev
```

### 3. Personalizar o Projeto

#### 3.1 Configurações Básicas

1. Atualize o arquivo `package.json`:
   - Nome do projeto
   - Versão
   - Descrição
   - Autor

2. Atualize o arquivo `vite.config.js`:
   - Alias do projeto
   - Configurações específicas do seu projeto

#### 3.2 Personalização Visual

1. Logo e Identidade Visual:
   - Substitua o arquivo `src/assets/logo.svg`
   - Atualize as cores no arquivo `tailwind.config.js`

2. Tema:
   - Ajuste as cores primárias e secundárias
   - Personalize os estilos globais em `src/assets/main.css`

#### 3.3 Estrutura do Projeto

O template segue uma estrutura organizada:

```
src/
├── assets/           # Recursos estáticos
├── components/       # Componentes reutilizáveis
│   ├── base/        # Componentes base (InnovView, InnovPanel, etc.)
│   ├── data/        # Componentes para exibição de dados
│   ├── form/        # Componentes de formulário
│   └── layout/      # Componentes de layout
├── layouts/         # Layouts principais
├── router/          # Configuração de rotas
├── stores/          # Gerenciamento de estado
└── views/           # Páginas da aplicação
```

### 4. Componentes Disponíveis

#### 4.1 Componentes Base

- `InnovView`: Container base para views
- `InnovPanel`: Painel com estilo padrão
- `InnovRow` e `InnovCol`: Sistema de grid
- `InnovStatusBadge`: Badge para status
- `InnovStatCard`: Card para estatísticas
- `InnovProgressBar`: Barra de progresso
- `ProgressCircle`: Indicador de progresso circular
- `InnovSpinner`: Spinner de carregamento
- `InnovToast`: Sistema de notificações toast
- `InnovConfirm`: Diálogo de confirmação
- `InnovModal`: Modal reutilizável
- `InnovCalendar`: Calendário completo
- `InnovCrud`: Componente CRUD completo
- `ColorBadge`: Badge com cor personalizada

#### 4.2 Componentes de Formulário

- `TextInput`: Campo de texto
- `TextAreaInput`: Área de texto
- `DateInput`: Campo de data
- `SelectSearch`: Select com pesquisa local
- `SelectSearchServer`: Select com pesquisa no servidor
- `CheckboxGroup`: Grupo de checkboxes
- `RadioGroup`: Grupo de radio buttons
- `ToggleSwitch`: Interruptor
- `RangeSlider`: Controle deslizante
- `RichTextEditor`: Editor de texto rico
- `ButtonsActions`: Botões de ação para formulários
- `FormWrapper`, `FormRow`, `FormElements`: Estrutura de formulários
- `InputTextGroup`: Grupo de inputs de texto

#### 4.3 Componentes de Dados

- `DataTable`: Tabela de dados com paginação e filtros

#### 4.4 Componentes de Layout

- `Breadcrumb`: Navegação breadcrumb
- `Footer`: Rodapé
- `Sidebar`: Barra lateral
- `TopNavbar`: Barra de navegação superior

### 5. Boas Práticas

1. **Organização de Código**:
   - Mantenha os componentes pequenos e reutilizáveis
   - Use composables para lógica reutilizável
   - Siga o padrão de nomenclatura dos componentes

2. **Estilização**:
   - Use as classes do Tailwind CSS
   - Mantenha consistência com o design system
   - Aproveite os temas claro/escuro

3. **Performance**:
   - Use lazy loading para rotas
   - Implemente paginação em listas grandes
   - Otimize imagens e assets

### 6. Exemplos de Uso

Consulte a página de documentação em `/documentacao` para ver exemplos detalhados de uso dos componentes.

### 7. Deploy

```bash
# Build para produção
npm run build

# Preview do build
npm run preview
```

## 📚 Recursos Incluídos

- [x] Vue 3 com Composition API
- [x] Tailwind CSS para estilização
- [x] Sistema de temas claro/escuro
- [x] Componentes base reutilizáveis
- [x] Sistema de rotas
- [x] Gerenciamento de estado com Pinia
- [x] Layout responsivo
- [x] Sistema de autenticação básico
- [x] Documentação de componentes

## 🔧 Tecnologias

- Vue 3
- Vue Router 4
- Pinia 3
- Tailwind CSS 4
- Vite 6
- Font Awesome 6

## 📝 Licença

Este template está sob a licença MIT. Veja o arquivo `LICENSE` para mais detalhes.

## Tecnologias Principais

- **Vue.js 3**: Framework JavaScript para construção de interfaces
- **Vue Router 4**: Para gerenciamento de rotas
- **Pinia 3**: Para gerenciamento de estado
- **Tailwind CSS 4**: Para estilização
- **Vite 6**: Como bundler e servidor de desenvolvimento
- **Font Awesome 6**: Para ícones

## Estrutura do Projeto

```
src/
├── assets/           # Recursos estáticos
├── components/       # Componentes reutilizáveis
│   ├── data/         # Componentes para exibição de dados (DataTable)
│   ├── form/         # Componentes de formulário
│   └── layout/       # Componentes de layout (Sidebar, TopNavbar, etc.)
├── layouts/          # Layouts principais da aplicação
│   ├── AdminLayout   # Layout para área administrativa
│   └── AuthLayout    # Layout para páginas de autenticação
├── router/           # Configuração de rotas
├── stores/           # Gerenciamento de estado com Pinia
├── views/            # Páginas da aplicação
│   ├── Dashboard
│   ├── Login
│   └── Páginas de gestão (Clientes, Produtos, Vendas, etc.)
└── App.vue           # Componente raiz
```

## Características

- **Interface com Tema Claro/Escuro**: Suporte a alternância entre temas
- **Layout Responsivo**: Com sidebar retrátil e adaptação para dispositivos móveis
- **Sistema de Navegação**: Inclui breadcrumbs para melhor experiência de usuário
- **Componentes Reutilizáveis**: DataTable, elementos de formulário e mais
- **Transições entre Páginas**: Animações suaves para melhorar a experiência do usuário

## Páginas Incluídas

- **Login**: Autenticação de usuários
- **Dashboard**: Visão geral com gráficos e indicadores
- **Detalhe de Projeto**: Visualização detalhada de projetos com progresso, equipe e tarefas
- **Kanban**: Quadro Kanban para gerenciamento visual de tarefas
- **Chat**: Interface de mensagens para comunicação entre usuários
- **Calendário**: Visualização e gerenciamento de eventos e compromissos
- **Clientes**: Gerenciamento de clientes
- **Produtos**: Gerenciamento de produtos
- **Vendas**: Registro e acompanhamento de vendas
- **Pedidos**: Gerenciamento de pedidos
- **Histórico**: Histórico de vendas
- **Relatórios**: Geração de relatórios
- **Configurações**: Configurações do sistema
- **Exemplos**: Demonstrações de DataTable e Formulários

## Configuração Recomendada de IDE

[VSCode](https://code.visualstudio.com/) + [Volar](https://marketplace.visualstudio.com/items?itemName=Vue.volar) (e desabilitar Vetur).

## Personalização da Configuração

Veja [Referência de Configuração do Vite](https://vite.dev/config/).

## Configuração do Projeto

```sh
npm install
```

### Compilação e Hot-Reload para Desenvolvimento

```sh
npm run dev
```

### Compilação e Minificação para Produção

```sh
npm run build
```
