# Design Guide — Eclesia

**Status:** active  
**Data:** 2026-09-19  
**Fonte de verdade visual:** tokens em [`front/src/assets/main.css`](../../front/src/assets/main.css)  
**Decisão de marca:** [ADR-0004](../architecture/ADR-0004-shell-launcher-marca-vinho.md)  
**White-label por tenant:** [SPEC-011](../specs/SPEC-011-branding-tenant.md)  
**Protótipo de referência:** `prototipo/`

Agentes e humanos devem seguir este guia ao criar ou alterar UI no `front/`. Em conflito com código legado de demo Innov, **preferir este guia** e migrar a tela tocada.

---

## 1. Princípios

1. **Uma composição por viewport** — a primeira tela de uma rota deve ler como uma página, não como dashboard genérico (exceto quando a rota *é* um dashboard).
2. **Marca primeiro** — no shell e no site público, a identidade vinho (ou cores do tenant) é o sinal dominante; não inventar paletas paralelas.
3. **Tokens, não hex soltos** — usar `var(--color-*)` / classes utilitárias do design system. Hex hardcoded só em protótipos descartáveis.
4. **Classes de botão completas** — sempre `btn btn-primary` (ou `btn btn-ghost` / `btn btn-accent`). Só `btn-primary` **não** aplica padding.
5. **Uma função por seção** — um título, um subtítulo curto, um bloco de ação.
6. **Menos cartões** — `.card` para agrupamento de interação/formulário; não empilhar cards decorativos no hero/launcher.

---

## 2. Tokens (fallback global)

Definidos em `:root` (`main.css`). Branding do tenant pode sobrescrever `--color-primary`, `--color-accent` / `--color-secondary` e correlatos via `applyBrandCores` ([`front/src/utils/branding.js`](../../front/src/utils/branding.js)).

| Token | Valor padrão | Uso |
|-------|--------------|-----|
| `--color-primary` | `#4E1220` | Sidebar de módulo, ênfase de marca |
| `--color-primary-soft` | `#6B1C2B` | Botão primário, avatar |
| `--color-primary-hover` | `#8A2436` | Hover primário |
| `--color-accent` | `#C88A5E` | Cobre / destaques |
| `--color-accent-dark` | `#B4703F` | Eyebrow, badges |
| `--color-accent-soft` | `#F6EDE4` | Fundo de chip |
| `--color-bg` | `#F7F4EF` | Fundo da app |
| `--color-surface` | `#FFFDFA` | Cards, header |
| `--color-ink` | `#2A1418` | Texto principal |
| `--color-muted` | `rgba(42,20,24,0.62)` | Texto secundário |
| `--color-line` | `rgba(42,20,24,0.12)` | Bordas |
| `--radius` | `12px` | Cards |
| `--font-sans` | Instrument Sans | UI, corpo |
| `--font-serif` | Newsreader | Títulos de página / módulo |
| `--font-mono` | JetBrains Mono | Slugs, URLs |

Tipografia global: `h1–h6` já usam serif no `main.css`. Em títulos de página no conteúdo, preferir `font-serif text-[28px] font-normal` + ink (padrão Calendário / Eventos).

---

## 3. Navegação e layouts

| Superfície | Layout | Notas |
|------------|--------|-------|
| Login / auth | `AuthLayout` | Pórtico; fidelidade ao PLAN-002 |
| Home autenticada | `LauncherLayout` + `WelcomeView` | Cartões de módulo; sem sidebar global |
| Módulo (ECC, Calendário, Site, Eventos) | `ModuleLayout` | Sidebar vinho (`--color-primary`) + “← Todos os módulos” |
| Admin plataforma | `AdminLayout` | Super-admin (tenants) |
| Site público | `PublicSiteView` | Aplica branding do tenant |

**Header do módulo:** organização / igreja. Se nome da org ≡ nome da igreja, mostrar **só o chip da igreja** (evitar `slug / slug`).

**Sidebar:** item ativo = fundo `#FFFDFA` + texto `var(--color-primary)`; altura mínima ~40px; gap entre itens `gap-1`.

---

## 4. Anatomia de uma página de módulo

Espelho de Calendário / Eventos:

```html
<div class="px-4 md:px-6 lg:px-8 xl:px-10 py-6 w-full">
  <header class="mb-8">
    <p class="page-eyebrow">Núcleo</p>  <!-- ou nome do módulo -->
    <h1 class="font-serif text-[28px] font-normal" style="color: var(--color-ink)">Título</h1>
    <p class="text-[14px] mt-1" style="color: var(--color-muted)">Uma frase de apoio.</p>
  </header>

  <!-- formulário / ação principal em .card -->
  <!-- lista ou empty state -->
</div>
```

Regras:

- **Padding de página** no view, não deixar o `main` do `ModuleLayout` sem respiro.
- **Formulário de criação:** campos em grid; botão primário em linha própria (`flex justify-end`), nunca espremido na mesma fileira de um `flex-1` sem gap claro.
- **Empty state:** bloco com borda tracejada (`border-dashed` + `--color-line`), título serif + texto muted — não só uma linha cinza solta.
- **Listas:** preferir `ul`/`li` + botão `.card` full-width; status em chip cobre (`accent-soft` / `accent-dark`).

---

## 5. Componentes CSS (obrigatórios)

| Classe | Função |
|--------|--------|
| `.card` | Superfície com borda, radius 12px, sombra suave |
| `.btn` | Base (padding, radius 8px, tipografia) — **sempre combinar** |
| `.btn-primary` | Ação principal (fundo primary-soft) |
| `.btn-ghost` | Secundária / outline |
| `.btn-accent` | Destaque cobre |
| `.btn-danger` | Destrutivo |
| `.input` | Campo de formulário |
| `.fld` | Label uppercase pequena acima do input |
| `.page-eyebrow` | Label de seção acima do H1 |

Não criar `.btn-secondary` paralelo — usar `.btn.btn-ghost`.

### Dialogs (obrigatório)

**Não usar** `alert()`, `confirm()` nem `prompt()` nativos do browser.

API Promise global (host `InnovDialog` em `App.vue`):

```js
import { innovConfirm, innovPrompt, innovAlert } from '@/plugins/dialog'

const ok = await innovConfirm({
  title: 'Remover',
  message: 'Tem certeza?',
  confirmText: 'Remover',
  danger: true,
})
if (!ok) return

const nome = await innovPrompt({
  title: 'Primeiro local',
  message: 'Cadastre um local de celebração.',
  label: 'Nome do local',
  placeholder: 'MATRIZ',
})
if (!nome) return

await innovAlert({ title: 'Pronto', message: 'Salvo com sucesso.' })
```

Componentes Vue: preferir `Innov*` existentes (`InnovModal`, `InnovPanel`, `InnovDialog`, …) antes de inventar novos.

---

## 6. White-label (tenant)

Uma marca por organização (`app_settings`, SPEC-014); o site público **espelha** a mesma marca (SPEC-011).

| Superfície | Fonte | Cores | Onde aplica |
|------------|--------|-------|-------------|
| **App (SPA)** | `app_settings` | `primary`, `secondary`, `text`, `text_muted`, `on_primary` | Login/`/web/me.branding`, shell, navbar, `/configuracoes/marca` |
| **Site público** | espelho: `tenants.name` + `app_settings` | mesmas 5 cores | Site público + Identidade (somente leitura) no Site Admin |

- `applyBrandCores` a partir de `/web/me.branding`, `GET /app/branding` ou `settings.cores` do site.
- Chrome/blocos do site usam `var(--color-*)` (não hex fixo de marca).
- Fallback: tokens da seção 2 (vinho).
- Logo: `app_settings.logo_path` → navbar e site (`logo_url` assinada).
- **Sem** override por Igreja nesta fase.

---

## 7. Acessibilidade e qualidade

- Todo controle interativo: `label` associado ou `aria-label`.
- `data-testid` em ações e regiões principais (padrão do projeto).
- Contraste: texto muted só para secundário; títulos em `--color-ink`.
- Foco visível nos inputs (já no `.input:focus`); não remover outline de botões sem substituto.
- Respeitar `prefers-reduced-motion` (já no `html`).

---

## 8. Anti-padrões (não fazer)

- Hex de marca soltos (`#4E1220`, `#6B1C2B`) em views novas — usar CSS variables.
- `class="btn-primary"` sem `btn`.
- `alert` / `confirm` / `prompt` nativos — usar `innovAlert` / `innovConfirm` / `innovPrompt`.
- Página de módulo sem padding horizontal (`px-4 md:px-6 …`).
- Breadcrumb repetindo o mesmo rótulo duas vezes.
- Cards aninhados sem necessidade; pills/stats decorativos no hero do launcher.
- Temas purple-on-white, cream+terracotta genérico de IA, ou dark mode como padrão (dark é opcional / legado).
- Tipografia default do sistema (Inter/Roboto/Arial) no lugar de Instrument Sans / Newsreader.

---

## 9. Checklist rápido (PR / agent)

- [ ] Tokens via `var(--color-*)` ou classes do `main.css`
- [ ] Botões = `btn` + variante
- [ ] Página de módulo com padding + eyebrow + H1 serif + subtítulo
- [ ] Formulário: grid + CTA alinhado (não colado ao input)
- [ ] Empty state deliberado
- [ ] Header sem rótulo duplicado org/igreja
- [ ] `data-testid` nas ações
- [ ] Teste Vitest se houver lógica nova de UI/util

---

## 10. Onde olhar no código

| O quê | Onde |
|-------|------|
| Tokens e classes | `front/src/assets/main.css` |
| Branding runtime | `front/src/utils/branding.js` |
| Dialogs | `front/src/components/base/InnovDialog.vue`, `front/src/plugins/dialog.js` |
| Launcher | `front/src/views/WelcomeView.vue` |
| Shell de módulo | `front/src/layouts/ModuleLayout.vue` |
| Referência de página | `front/src/views/CalendarioMensaisView.vue`, `EccEventosView.vue` |
| Canvas / HTML de design | `prototipo/` |
