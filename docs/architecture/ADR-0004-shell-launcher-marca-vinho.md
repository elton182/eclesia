# ADR-0004 — Shell launcher + marca vinho litúrgico

**Status:** accepted  
**Data:** 2026-09-10  
**Plano:** [PLAN-002](../plans/PLAN-002-redesign-vinho-launcher-ecc-site.md)

## Contexto

O canvas de design (`prototipo/eclesias-novo-design.html`) redefine a identidade (vinho/cobre/creme + Newsreader/Instrument Sans) e a navegação: home como **launcher de módulos**, com sidebar apenas **dentro** de cada módulo. O BRIEF anterior usava marinho `#00234E` + Figtree/Fraunces e uma sidebar flat misturando plataforma e módulos.

## Decisão

1. **Marca única** no shell e no site público: bordô `#4E1220`, ações `#6B1C2B`/`#8A2436`, cobre `#C88A5E`/`#B4703F`, fundos `#F7F4EF`/`#FFFDFA`, ink `#2A1418`. Tipografia: Instrument Sans (UI), Newsreader (títulos), JetBrains Mono (slugs).
2. **Navegação em dois níveis:** `/inicio` = launcher; rotas de módulo (`/ecc/*`, `/site`) usam `ModuleLayout` com “← Todos os módulos”.
3. Launcher (1c/1d), site público (1g) e editor de site (2a) exigem fidelidade estrutural/visual ao canvas (PLAN-002).

## Consequências

- Atualizar tokens em `front/src/assets/main.css` e fontes em `front/index.html`.
- Substituir/adaptar `AdminLayout` + `Sidebar` flat; administração (igrejas/usuários) entra pelo cartão do launcher.
- BRIEF § identidade alinhado a esta ADR; tema ECC separado deixa de ser necessário nesta fase.
