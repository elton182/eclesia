#!/usr/bin/env node

import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';
import { execSync } from 'child_process';
import readline from 'readline';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

const rl = readline.createInterface({
  input: process.stdin,
  output: process.stdout
});

const question = (query) => new Promise((resolve) => rl.question(query, resolve));

async function createProject() {
  try {
    // Solicitar informações do projeto
    const projectName = await question('Nome do projeto: ');
    const projectDescription = await question('Descrição do projeto: ');
    const projectAuthor = await question('Autor do projeto: ');
    const projectVersion = await question('Versão inicial (ex: 0.1.0): ');

    // Criar diretório do projeto
    const projectDir = path.join(process.cwd(), projectName);
    if (fs.existsSync(projectDir)) {
      console.error('❌ Diretório já existe!');
      process.exit(1);
    }

    console.log('\n🚀 Criando novo projeto...\n');

    // Clonar o template
    execSync(`git clone git@gitlab.com:innovareti/innov-front.git ${projectName}`);
    process.chdir(projectDir);

    // Remover git existente
    execSync('rm -rf .git');
    execSync('git init');

    // Atualizar package.json
    const packageJson = JSON.parse(fs.readFileSync('package.json', 'utf8'));
    packageJson.name = projectName;
    packageJson.version = projectVersion;
    packageJson.description = projectDescription;
    packageJson.author = projectAuthor;
    fs.writeFileSync('package.json', JSON.stringify(packageJson, null, 2));

    // Instalar dependências
    console.log('\n📦 Instalando dependências...\n');
    execSync('npm install');

    // Criar arquivo .env
    const envContent = `
VITE_APP_NAME=${projectName}
VITE_APP_DESCRIPTION=${projectDescription}
VITE_APP_VERSION=${projectVersion}
VITE_APP_AUTHOR=${projectAuthor}
    `.trim();
    fs.writeFileSync('.env', envContent);

    // Criar arquivo .gitignore
    const gitignoreContent = `
# Logs
logs
*.log
npm-debug.log*
yarn-debug.log*
yarn-error.log*
pnpm-debug.log*
lerna-debug.log*

# Dependencies
node_modules
.DS_Store
dist
dist-ssr
coverage
*.local

# Editor directories and files
.vscode/*
!.vscode/extensions.json
.idea
*.suo
*.ntvs*
*.njsproj
*.sln
*.sw?

# Environment variables
.env
.env.local
.env.*.local
    `.trim();
    fs.writeFileSync('.gitignore', gitignoreContent);

    // Criar README.md específico do projeto
    const readmeContent = `
# ${projectName}

${projectDescription}

## 🚀 Como Iniciar

\`\`\`bash
# Instalar dependências
npm install

# Iniciar servidor de desenvolvimento
npm run dev

# Build para produção
npm run build
\`\`\`

## 📚 Documentação

Consulte a documentação dos componentes em \`/documentacao\` para ver exemplos de uso.
    `.trim();
    fs.writeFileSync('README.md', readmeContent);

    console.log('\n✅ Projeto criado com sucesso!');
    console.log('\nPróximos passos:');
    console.log(`1. cd ${projectName}`);
    console.log('2. npm run dev');
    console.log('\nBoa sorte com seu projeto! 🎉\n');

  } catch (error) {
    console.error('❌ Erro ao criar projeto:', error);
    process.exit(1);
  } finally {
    rl.close();
  }
}

createProject(); 