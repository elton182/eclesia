# Configuração do Pacote NPM - Resumo

Este documento resume as mudanças feitas para transformar o projeto em um pacote npm compartilhável.

## Arquivos Criados/Modificados

### 1. `src/index.js` (NOVO)
- Arquivo de entrada principal do pacote
- Exporta todos os componentes, stores, plugins e services
- Inclui função `install()` para uso como plugin Vue

### 2. `vite.config.js` (MODIFICADO)
- Adicionada configuração para build de biblioteca
- Suporta dois modos: desenvolvimento (padrão) e biblioteca (`BUILD_MODE=library`)
- Configurado para gerar builds ES modules e UMD

### 3. `package.json` (MODIFICADO)
- Removido `"private": true`
- Adicionados campos: `main`, `module`, `exports`
- Configurado para apontar para `src/index.js` (código-fonte)
- Dependências movidas para `peerDependencies`
- Script `build:lib` mantido apenas para testes (não necessário para publicação)

### 4. `.npmignore` (NOVO)
- Configurado para excluir arquivos desnecessários do pacote npm
- Mantém apenas arquivos essenciais para distribuição

### 5. `README.md` (MODIFICADO)
- Adicionada seção completa sobre uso como pacote npm
- Exemplos de instalação e uso
- Documentação de componentes, stores e plugins disponíveis

### 6. `USAGE.md` (NOVO)
- Guia detalhado de uso do pacote
- Exemplos práticos de código
- Configurações necessárias

## Como Usar

### Publicar no NPM

O pacote publica o código-fonte diretamente, sem necessidade de build:

1. Atualize a versão no `package.json`
2. Faça login no npm: `npm login`
3. Publique diretamente: `npm publish`

**Importante:** Não é necessário fazer build antes de publicar. O código-fonte (`src`) será usado diretamente pelos projetos que instalarem o pacote, permitindo que cada projeto faça seu próprio build e otimização através do Vite/Webpack.

### Build da Biblioteca (Opcional - apenas para testes)

```bash
npm run build:lib
```

Isso gerará os arquivos em `dist/` para testes locais, mas não é necessário para publicação.

### Usar em Outras Aplicações

```bash
npm install innov-front
```

```javascript
import InnovFront from 'innov-front'
import 'innov-front/styles'

app.use(InnovFront)
```

## Estrutura de Exportação

O pacote exporta:

- **Componentes**: Todos os componentes Vue (base, form, data, layout)
- **Stores**: Stores Pinia (auth, toast, etc.)
- **Plugins**: Plugins utilitários (toast)
- **Services**: Serviços (api)
- **CSS**: Estilos completos do Tailwind CSS

## Próximos Passos

1. Testar localmente usando `npm link` (sem necessidade de build)
2. Configurar repositório no `package.json`
3. Publicar no npm registry: `npm publish`
4. Atualizar documentação conforme necessário

## Notas Importantes

- **Código-fonte**: O pacote publica o código-fonte (`src`) diretamente, permitindo que cada projeto faça seu próprio build e tree-shaking
- **Peer Dependencies**: As dependências Vue, Vue Router, Pinia e Axios são **peer dependencies**
- **CSS**: O CSS precisa ser importado separadamente: `import 'innov-front/styles'`
- **FontAwesome**: Deve ser configurado manualmente na aplicação que usa o pacote
- **API Service**: O service `api` usa `VITE_API_URL` como variável de ambiente
- **Vantagens**: Publicar código-fonte permite melhor tree-shaking, builds otimizados por projeto e menor tamanho final
