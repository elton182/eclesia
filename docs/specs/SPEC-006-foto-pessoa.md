# SPEC-006 — Foto da Pessoa no cadastro

**Status:** approved  
**Data:** 2026-09-16  
**Decisões de produto:** confirmadas em 2026-09-16 (foto em `Pessoa`; upload + câmera; UI no form e na ficha do casal; `ficha_com_foto` derivado).

## Contexto

O núcleo compartilha `Pessoa` entre módulos. Hoje o ECC só tem o flag booleano `casais.ficha_com_foto` (planilha/formulário), sem imagem. Avatar na ficha usa iniciais. Foto facial é dado pessoal (LGPD): isolamento por tenant (storage suffix) e acesso autenticado.

## Objetivo

1. Persistir foto opcional em `Pessoa` (`foto_path` + URL derivada).
2. Upload multipart (arquivo ou captura via câmera do dispositivo — `accept="image/*"` + `capture`).
3. Expor `id` e `foto_url` em `ele`/`ela` do casal.
4. UI de foto no formulário de casais e na ficha `/ecc/casais/:id`.
5. `ficha_com_foto` passa a refletir presença de foto em pelo menos uma das pessoas do casal (não é mais editável manualmente).

## Critérios de aceite (testáveis)

- [ ] Migration tenant: `pessoas.foto_path` (string nullable).
- [ ] `POST /api/v1/pessoas/{id}/foto` (multipart `file`) → 200 com `id`, `foto_url`; valida mime jpg/jpeg/png/webp e máx. 5 MB.
- [ ] `DELETE /api/v1/pessoas/{id}/foto` → 204; remove arquivo e zera path.
- [ ] 401 sem auth; 403 sem `ecc.casais.manage` / `pessoas.manage`; 404 pessoa de outra igreja/tenant.
- [ ] Resource do casal: `ele`/`ela` incluem `id` e `foto_url`; `ficha_com_foto === true` se A ou B tem foto.
- [ ] Create/update/import de casal **não** aceitam mais `ficha_com_foto` como input efetivo (coluna sincronizada ao alterar foto).
- [ ] Ao apagar pessoa/casal, arquivo da foto é removido do disco.
- [ ] Front: componente reutilizável (galeria + “tirar foto”); no form (Ele/Ela) e na ficha; após criar casal, faz upload se houver arquivo pendente.
- [ ] Isolamento: arquivo no disk `public` com path sob storage do tenant (`suffix_storage_path`).

## Fora de escopo

- CRUD genérico de pessoas (tela `/pessoas`)
- Crop/editor avançado; reconhecimento facial
- CDN / S3 (disk `public` local por tenant nesta fase)
- Foto pública no site CMS

## Contrato de API

Ver `api/docs/specs/openapi.yaml` — paths `/pessoas/{id}/foto` e campos `ele`/`ela`/`ficha_com_foto` em `EccCasal`.

## Notas

- Permissão de escrita: `pessoas.manage` **ou** `ecc.casais.manage` (SuperAdmin / admin-tenant ok via escopo existente).
- Import planilha: coluna “Ficha Com foto” é ignorada; o flag só fica true após upload real.
- Câmera: HTML `input capture="user"` (selfie) + opção sem capture (galeria); funciona em celular com HTTPS/origem segura.
