/**
 * Conteúdo comercial da landing page pública.
 *
 * Mantido fora do componente para ser testável sem DOM. O campo `icone` é uma
 * chave resolvida para o ícone do Font Awesome dentro da view.
 */

export const MARCA = {
  nome: 'Eclésias',
  tagline: 'Gestão inteligente para Igrejas e Paróquias',
}

export const HERO = {
  eyebrow: 'Plataforma de gestão eclesial',
  titulo: 'Toda a vida da sua paróquia organizada em um só lugar',
  descricao:
    'O Eclésias reúne pessoas, equipes, escalas, eventos e finanças da sua ' +
    'comunidade em uma plataforma web modular. Sua organização ativa apenas ' +
    'os módulos que usa — e cada igreja enxerga só o que é dela.',
  destaques: [
    'Cadastro único de pessoas e famílias, compartilhado entre pastorais e movimentos',
    'Módulos habilitáveis por igreja, sem pagar pelo que não usa',
    'Dados isolados por organização e PII criptografada, em conformidade com a LGPD',
  ],
}

/** Números/pilares exibidos na faixa de prova, logo abaixo do hero. */
export const PILARES = [
  {
    valor: '1..N',
    titulo: 'Igrejas por organização',
    descricao: 'Dioceses, congregações e grupos de paróquias em um só contrato.',
  },
  {
    valor: '7',
    titulo: 'Módulos no mapa',
    descricao: 'ECC já em uso; catequese, sacramentos, dízimo e mais no roadmap.',
  },
  {
    valor: '100%',
    titulo: 'Português brasileiro',
    descricao: 'Vocabulário e rotinas pensados para a realidade da paróquia.',
  },
  {
    valor: 'LGPD',
    titulo: 'Por padrão',
    descricao: 'Banco separado por organização e dados pessoais criptografados.',
  },
]

/** Blocos de valor da seção "A plataforma". */
export const RECURSOS = [
  {
    icone: 'pessoas',
    titulo: 'Cadastro único de pessoas',
    descricao:
      'Uma pessoa é cadastrada uma vez e serve a todos os módulos. Acabam as ' +
      'planilhas paralelas e os dados divergentes entre pastorais.',
  },
  {
    icone: 'agenda',
    titulo: 'Agenda unificada',
    descricao:
      'Encontros, reuniões e eventos alimentados pelos módulos aparecem em uma ' +
      'única agenda da igreja, sem sobreposição de compromissos.',
  },
  {
    icone: 'escala',
    titulo: 'Escalas sem quebra-cabeça',
    descricao:
      'A sugestão de escala considera preferência, histórico e disponibilidade, ' +
      'e sinaliza conflitos antes de você publicar.',
  },
  {
    icone: 'permissoes',
    titulo: 'Permissões por igreja e módulo',
    descricao:
      'Coordenadores, secretaria e líderes de equipe acessam apenas o que ' +
      'precisam, com auditoria das alterações sensíveis.',
  },
  {
    icone: 'relatorios',
    titulo: 'Relatórios consolidados',
    descricao:
      'Compare movimento, participação e caixa entre as igrejas da mesma ' +
      'organização, sem exportar planilha nenhuma.',
  },
  {
    icone: 'mobile',
    titulo: 'Funciona no celular',
    descricao:
      'Interface responsiva com navegação inferior no celular: dá para conferir ' +
      'a escala e marcar presença no corredor da igreja.',
  },
]

/**
 * Módulos da plataforma. `situacao` é 'disponivel' ou 'roadmap' — só o ECC está
 * disponível hoje; os demais não devem ser vendidos como prontos.
 */
export const MODULOS = [
  {
    chave: 'ecc',
    nome: 'ECC',
    situacao: 'disponivel',
    icone: 'ecc',
    descricao:
      'Encontro de Casais com Cristo: comunidade, equipes de serviço, escala, ' +
      'perseverança, eventos e caixa do encontro.',
  },
  {
    chave: 'catequese',
    nome: 'Catequese',
    situacao: 'roadmap',
    icone: 'catequese',
    descricao: 'Turmas, catequistas, catequizandos, presença e etapas.',
  },
  {
    chave: 'sacramentos',
    nome: 'Sacramentos',
    situacao: 'roadmap',
    icone: 'sacramentos',
    descricao: 'Assentos, livros e emissão de certidões.',
  },
  {
    chave: 'dizimo',
    nome: 'Dízimo',
    situacao: 'roadmap',
    icone: 'dizimo',
    descricao: 'Dizimistas, contribuições, carnês e recibos.',
  },
  {
    chave: 'financeiro',
    nome: 'Financeiro',
    situacao: 'roadmap',
    icone: 'financeiro',
    descricao: 'Contas, receitas e despesas, caixa geral da igreja.',
  },
  {
    chave: 'pastorais',
    nome: 'Pastorais',
    situacao: 'roadmap',
    icone: 'pastorais',
    descricao: 'Pastorais e movimentos, membros e reuniões.',
  },
  {
    chave: 'gestao',
    nome: 'Gestão',
    situacao: 'roadmap',
    icone: 'gestao',
    descricao: 'Igrejas, usuários, papéis, parâmetros e comunicação.',
  },
]

/** Passos da seção "Como funciona". */
export const PASSOS = [
  {
    numero: '01',
    titulo: 'Sua organização é criada',
    descricao:
      'A organização assinante ganha um banco de dados próprio e cadastra as ' +
      'igrejas que vai administrar.',
  },
  {
    numero: '02',
    titulo: 'Você ativa os módulos',
    descricao:
      'Cada igreja habilita só os módulos que usa. O menu e o painel mostram ' +
      'apenas o que está ativo.',
  },
  {
    numero: '03',
    titulo: 'As equipes entram em campo',
    descricao:
      'Coordenadores e líderes recebem acesso conforme o papel e passam a ' +
      'trabalhar sobre o mesmo cadastro de pessoas.',
  },
]

/** Itens da seção de segurança e conformidade. */
export const SEGURANCA = [
  {
    icone: 'banco',
    titulo: 'Banco de dados por organização',
    descricao:
      'Isolamento real: os dados da sua organização não dividem tabela com os ' +
      'de ninguém.',
  },
  {
    icone: 'cadeado',
    titulo: 'Dados pessoais criptografados',
    descricao:
      'Informações pessoais são gravadas criptografadas em repouso, como manda ' +
      'a LGPD.',
  },
  {
    icone: 'auditoria',
    titulo: 'Trilha de auditoria',
    descricao:
      'Alterações sensíveis ficam registradas, com autor e data, para prestação ' +
      'de contas.',
  },
]

/**
 * Conteúdo estático da prévia do portal no hero (launcher 1c/1d).
 * Espelha a home autenticada sem depender de sessão ou API.
 */
export const PORTAL_PREVIEW = {
  orgNome: 'Paróquia São José',
  comunidade: 'Comunidade Matriz',
  usuario: 'Maria Silva',
  papel: 'Administração',
  iniciais: 'MS',
  saudacao: 'Bom dia',
  tituloDesktop: 'Onde você quer trabalhar hoje, Maria?',
  tituloMobile: 'Olá, Maria',
  urlBar: 'app.eclesias.com.br/inicio',
  modulos: [
    {
      chave: 'ecc',
      letra: 'C',
      nome: 'ECC',
      descricao: 'Equipes, casais e encontros do movimento.',
      meta: '7 equipes · 124 casais',
      badge: 'ativo',
      cor: '#6B1C2B',
    },
    {
      chave: 'escalas',
      letra: 'E',
      nome: 'Escalas',
      descricao: 'Liturgia, equipes de apoio e agenda da igreja.',
      meta: '4 tipos',
      badge: 'ativo',
      cor: '#8A2436',
    },
    {
      chave: 'site',
      letra: 'S',
      nome: 'Site',
      descricao: 'Página pública, comunicados e horários.',
      meta: '/site/sao-jose',
      badge: 'publicado',
      cor: '#2A1418',
    },
  ],
}

/** Âncoras do menu do cabeçalho público. */
export const NAV_PUBLICA = [
  { href: '#plataforma', label: 'A plataforma' },
  { href: '#modulos', label: 'Módulos' },
  { href: '#como-funciona', label: 'Como funciona' },
  { href: '#seguranca', label: 'Segurança' },
]

export function modulosDisponiveis(modulos = MODULOS) {
  return modulos.filter((m) => m.situacao === 'disponivel')
}

export function modulosRoadmap(modulos = MODULOS) {
  return modulos.filter((m) => m.situacao === 'roadmap')
}

/** Rótulo do selo exibido no card do módulo. */
export function rotuloSituacaoModulo(situacao) {
  return situacao === 'disponivel' ? 'Disponível' : 'Em breve'
}

/** Classe do selo (`badge-*` do design system) conforme a situação do módulo. */
export function classeSituacaoModulo(situacao) {
  return situacao === 'disponivel' ? 'badge badge-success' : 'badge badge-warning'
}
