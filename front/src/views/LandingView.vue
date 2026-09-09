<script setup>
import { RouterLink } from 'vue-router'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { library } from '@fortawesome/fontawesome-svg-core'
import {
  faArrowRight,
  faBookOpen,
  faBuildingColumns,
  faCalendarDays,
  faChartLine,
  faCheck,
  faChurch,
  faClipboardList,
  faCoins,
  faGear,
  faHandHoldingHeart,
  faHeart,
  faIdCard,
  faListCheck,
  faLock,
  faMobileScreenButton,
  faPeopleGroup,
  faShieldHalved,
  faUserShield,
} from '@fortawesome/free-solid-svg-icons'
import {
  HERO,
  MARCA,
  MODULOS,
  PASSOS,
  PILARES,
  RECURSOS,
  SEGURANCA,
  classeSituacaoModulo,
  rotuloSituacaoModulo,
} from '@/utils/landing'

library.add(
  faArrowRight,
  faBookOpen,
  faBuildingColumns,
  faCalendarDays,
  faChartLine,
  faCheck,
  faChurch,
  faClipboardList,
  faCoins,
  faGear,
  faHandHoldingHeart,
  faHeart,
  faIdCard,
  faListCheck,
  faLock,
  faMobileScreenButton,
  faPeopleGroup,
  faShieldHalved,
  faUserShield,
)

const icones = {
  pessoas: faIdCard,
  agenda: faCalendarDays,
  escala: faListCheck,
  permissoes: faUserShield,
  relatorios: faChartLine,
  mobile: faMobileScreenButton,
  ecc: faHeart,
  catequese: faBookOpen,
  sacramentos: faChurch,
  dizimo: faHandHoldingHeart,
  financeiro: faCoins,
  pastorais: faPeopleGroup,
  gestao: faGear,
  banco: faBuildingColumns,
  cadeado: faLock,
  auditoria: faClipboardList,
}

const icone = (chave) => icones[chave] ?? faCheck

/** Amostra do painel do ECC exibida como prévia do produto no hero. */
const equipesPreview = [
  { nome: 'Acolhida', servos: 12, cor: '#7A2231' },
  { nome: 'Cozinha', servos: 18, cor: '#B0812F' },
  { nome: 'Liturgia', servos: 9, cor: '#00234E' },
]
</script>

<template>
  <div data-testid="landing-page">
    <!-- Hero -->
    <section
      class="relative overflow-hidden"
      style="background: linear-gradient(145deg, #F4F6F9 0%, #E8EEF6 45%, #F7F0E4 100%)"
    >
      <div class="mx-auto max-w-6xl px-4 md:px-8 py-16 md:py-24 grid gap-12 lg:grid-cols-2 lg:items-center">
        <div>
          <p class="page-eyebrow">{{ HERO.eyebrow }}</p>
          <h1
            class="text-[34px] leading-[1.12] md:text-[46px]"
            style="color: var(--color-primary)"
          >
            {{ HERO.titulo }}
          </h1>
          <p class="mt-5 text-[16px] md:text-[17px] leading-relaxed max-w-xl" style="color: var(--color-muted)">
            {{ HERO.descricao }}
          </p>

          <ul class="mt-7 space-y-3">
            <li
              v-for="destaque in HERO.destaques"
              :key="destaque"
              class="flex gap-3 text-[15px] leading-snug"
              style="color: var(--color-ink)"
            >
              <span
                class="mt-0.5 shrink-0 h-5 w-5 rounded-full inline-flex items-center justify-center text-[10px]"
                style="background: var(--color-accent-soft); color: var(--color-accent-dark)"
                aria-hidden="true"
              >
                <FontAwesomeIcon :icon="faCheck" />
              </span>
              {{ destaque }}
            </li>
          </ul>

          <div class="mt-9 flex flex-wrap gap-3">
            <RouterLink to="/entrar" class="btn btn-primary" data-testid="hero-login">
              Entrar na plataforma
              <FontAwesomeIcon :icon="faArrowRight" class="text-xs" />
            </RouterLink>
            <a href="#modulos" class="btn btn-ghost" data-testid="hero-modulos">
              Conhecer os módulos
            </a>
          </div>

          <p class="mt-4 text-[13px]" style="color: var(--color-muted)">
            Já tem acesso? Use o nome da sua organização e seu e-mail para entrar.
          </p>
        </div>

        <!-- Prévia do produto -->
        <div class="relative lg:pl-6" aria-hidden="true">
          <div
            class="absolute -top-8 -right-6 h-40 w-40 rounded-full blur-3xl opacity-40 hidden lg:block"
            style="background: var(--color-accent-soft)"
          ></div>

          <div class="card relative p-5 md:p-6">
            <div class="flex items-center justify-between">
              <div>
                <p class="page-eyebrow" style="margin-bottom: 2px">ECC · Painel</p>
                <p class="text-[15px] font-semibold" style="color: var(--color-primary)">
                  Paróquia São José
                </p>
              </div>
              <span class="badge badge-info">1ª etapa</span>
            </div>

            <div class="mt-5 grid grid-cols-3 gap-3">
              <div
                v-for="stat in [
                  { valor: '124', label: 'Casais' },
                  { valor: '39', label: 'Servos' },
                  { valor: '7', label: 'Equipes' },
                ]"
                :key="stat.label"
                class="rounded-xl p-3 text-center"
                style="background: var(--color-surface-2)"
              >
                <p
                  class="text-[26px] leading-none font-semibold"
                  style="font-family: Fraunces, Georgia, serif; color: var(--color-primary)"
                >
                  {{ stat.valor }}
                </p>
                <p class="mt-1 text-[11.5px]" style="color: var(--color-muted)">{{ stat.label }}</p>
              </div>
            </div>

            <p class="mt-6 text-[12.5px] font-semibold" style="color: var(--color-muted)">
              Equipes de serviço
            </p>
            <div class="mt-2 space-y-2">
              <div
                v-for="equipe in equipesPreview"
                :key="equipe.nome"
                class="flex items-center gap-3 rounded-xl border p-3"
                style="border-color: var(--color-line)"
              >
                <span class="h-8 w-1.5 rounded-full shrink-0" :style="{ background: equipe.cor }"></span>
                <span class="text-[14px] font-medium flex-1" style="color: var(--color-ink)">
                  {{ equipe.nome }}
                </span>
                <span class="flex -space-x-2">
                  <span
                    class="h-6 w-6 rounded-full border-2 border-white"
                    style="background: var(--color-primary)"
                  ></span>
                  <span
                    class="h-6 w-6 rounded-full border-2 border-white"
                    style="background: var(--color-accent)"
                  ></span>
                </span>
                <span class="text-[12.5px] tabular-nums" style="color: var(--color-muted)">
                  {{ equipe.servos }}
                </span>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- Pilares -->
    <section class="border-y" style="border-color: var(--color-line); background: var(--color-surface)">
      <div class="mx-auto max-w-6xl px-4 md:px-8 py-10 grid gap-8 sm:grid-cols-2 lg:grid-cols-4">
        <div v-for="pilar in PILARES" :key="pilar.titulo">
          <p
            class="text-[30px] leading-none"
            style="font-family: Fraunces, Georgia, serif; color: var(--color-accent-dark)"
          >
            {{ pilar.valor }}
          </p>
          <p class="mt-2 text-[15px] font-semibold" style="color: var(--color-primary)">
            {{ pilar.titulo }}
          </p>
          <p class="mt-1 text-[13.5px] leading-relaxed" style="color: var(--color-muted)">
            {{ pilar.descricao }}
          </p>
        </div>
      </div>
    </section>

    <!-- A plataforma -->
    <section id="plataforma" class="scroll-anchor">
      <div class="mx-auto max-w-6xl px-4 md:px-8 py-16 md:py-20">
        <div class="max-w-2xl">
          <p class="page-eyebrow">A plataforma</p>
          <h2 class="text-[28px] md:text-[34px]" style="color: var(--color-primary)">
            Um núcleo comum para toda a comunidade
          </h2>
          <p class="mt-3 text-[15.5px] leading-relaxed" style="color: var(--color-muted)">
            Cada módulo resolve uma rotina específica, mas todos bebem da mesma fonte: as
            pessoas, as famílias e a agenda da sua igreja. É isso que elimina o retrabalho
            entre pastorais e movimentos.
          </p>
        </div>

        <div class="mt-10 grid gap-5 md:grid-cols-2 lg:grid-cols-3">
          <article
            v-for="recurso in RECURSOS"
            :key="recurso.titulo"
            class="card p-6"
            data-testid="landing-recurso"
          >
            <span
              class="h-11 w-11 rounded-xl inline-flex items-center justify-center"
              style="background: rgba(0, 35, 78, 0.06); color: var(--color-primary)"
              aria-hidden="true"
            >
              <FontAwesomeIcon :icon="icone(recurso.icone)" />
            </span>
            <h3 class="mt-4 text-[18px]" style="color: var(--color-primary)">{{ recurso.titulo }}</h3>
            <p class="mt-2 text-[14.5px] leading-relaxed" style="color: var(--color-muted)">
              {{ recurso.descricao }}
            </p>
          </article>
        </div>
      </div>
    </section>

    <!-- Módulos -->
    <section
      id="modulos"
      class="scroll-anchor border-y"
      style="border-color: var(--color-line); background: var(--color-surface)"
    >
      <div class="mx-auto max-w-6xl px-4 md:px-8 py-16 md:py-20">
        <div class="max-w-2xl">
          <p class="page-eyebrow">Módulos</p>
          <h2 class="text-[28px] md:text-[34px]" style="color: var(--color-primary)">
            Ative só o que a sua igreja usa
          </h2>
          <p class="mt-3 text-[15.5px] leading-relaxed" style="color: var(--color-muted)">
            O ECC já está no ar. Os demais módulos entram no ritmo do roadmap e podem ser
            habilitados igreja a igreja, sem migração de dados.
          </p>
        </div>

        <div class="mt-10 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
          <article
            v-for="modulo in MODULOS"
            :key="modulo.chave"
            class="card p-6 flex flex-col"
            :data-testid="`landing-modulo-${modulo.chave}`"
          >
            <div class="flex items-start justify-between gap-3">
              <span
                class="h-11 w-11 rounded-xl inline-flex items-center justify-center"
                :style="
                  modulo.situacao === 'disponivel'
                    ? 'background: var(--color-primary); color: var(--color-accent)'
                    : 'background: var(--color-surface-2); color: var(--color-muted)'
                "
                aria-hidden="true"
              >
                <FontAwesomeIcon :icon="icone(modulo.icone)" />
              </span>
              <span :class="classeSituacaoModulo(modulo.situacao)">
                {{ rotuloSituacaoModulo(modulo.situacao) }}
              </span>
            </div>
            <h3 class="mt-4 text-[18px]" style="color: var(--color-primary)">{{ modulo.nome }}</h3>
            <p class="mt-2 text-[14.5px] leading-relaxed" style="color: var(--color-muted)">
              {{ modulo.descricao }}
            </p>
          </article>
        </div>
      </div>
    </section>

    <!-- Como funciona -->
    <section id="como-funciona" class="scroll-anchor">
      <div class="mx-auto max-w-6xl px-4 md:px-8 py-16 md:py-20">
        <div class="max-w-2xl">
          <p class="page-eyebrow">Como funciona</p>
          <h2 class="text-[28px] md:text-[34px]" style="color: var(--color-primary)">
            Do contrato à primeira escala
          </h2>
        </div>

        <ol class="mt-10 grid gap-5 md:grid-cols-3">
          <li v-for="passo in PASSOS" :key="passo.numero" class="card p-6">
            <p
              class="text-[15px] font-bold tracking-[0.14em]"
              style="color: var(--color-accent); font-family: Fraunces, Georgia, serif"
            >
              {{ passo.numero }}
            </p>
            <h3 class="mt-2 text-[18px]" style="color: var(--color-primary)">{{ passo.titulo }}</h3>
            <p class="mt-2 text-[14.5px] leading-relaxed" style="color: var(--color-muted)">
              {{ passo.descricao }}
            </p>
          </li>
        </ol>
      </div>
    </section>

    <!-- Segurança -->
    <section id="seguranca" class="scroll-anchor">
      <div class="mx-auto max-w-6xl px-4 md:px-8 pb-16 md:pb-20">
        <div
          class="rounded-[var(--radius)] px-6 md:px-10 py-10 md:py-12"
          style="background: var(--color-primary); color: #F7F4EE"
        >
          <div class="max-w-2xl">
            <p class="page-eyebrow">Segurança e LGPD</p>
            <h2 class="text-[28px] md:text-[34px]" style="color: #F7F4EE">
              Dado de fiel não é dado qualquer
            </h2>
            <p class="mt-3 text-[15.5px] leading-relaxed text-white/70">
              A plataforma nasceu com isolamento por organização e criptografia de dados
              pessoais em repouso — não é um recurso opcional que se contrata à parte.
            </p>
          </div>

          <div class="mt-10 grid gap-6 md:grid-cols-3">
            <div v-for="item in SEGURANCA" :key="item.titulo" data-testid="landing-seguranca">
              <span
                class="h-11 w-11 rounded-xl inline-flex items-center justify-center"
                style="background: rgba(197, 160, 89, 0.18); color: var(--color-accent)"
                aria-hidden="true"
              >
                <FontAwesomeIcon :icon="icone(item.icone)" />
              </span>
              <h3 class="mt-4 text-[17px]" style="color: #F7F4EE">{{ item.titulo }}</h3>
              <p class="mt-2 text-[14.5px] leading-relaxed text-white/70">{{ item.descricao }}</p>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- Chamada final -->
    <section class="pb-20">
      <div class="mx-auto max-w-6xl px-4 md:px-8">
        <div
          class="card p-8 md:p-12 text-center flex flex-col items-center"
          style="background: linear-gradient(145deg, #FFFFFF 0%, #F7F0E4 100%)"
        >
          <p class="page-eyebrow">{{ MARCA.nome }}</p>
          <h2 class="text-[26px] md:text-[32px] max-w-xl" style="color: var(--color-primary)">
            Pronto para tirar a gestão da sua paróquia das planilhas?
          </h2>
          <p class="mt-3 max-w-lg text-[15.5px] leading-relaxed" style="color: var(--color-muted)">
            Entre com o acesso da sua organização e comece pelo módulo ECC.
          </p>
          <div class="mt-7 flex flex-wrap justify-center gap-3">
            <RouterLink to="/entrar" class="btn btn-primary" data-testid="cta-login">
              Entrar na plataforma
              <FontAwesomeIcon :icon="faArrowRight" class="text-xs" />
            </RouterLink>
          </div>
          <p class="mt-4 text-[13px]" style="color: var(--color-muted)">
            Ainda não tem acesso? Procure o responsável pela sua organização.
          </p>
        </div>
      </div>
    </section>
  </div>
</template>

<style scoped>
.scroll-anchor {
  scroll-margin-top: 5rem;
}
</style>
