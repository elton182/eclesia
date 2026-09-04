#!/usr/bin/env bash
# Sobe API (Laravel) + Front (Vite) para validação local do monorepo Eclesia.
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
API_DIR="$ROOT/api"
FRONT_DIR="$ROOT/front"
API_PID=""
FRONT_PID=""

cleanup() {
  echo ""
  echo "==> Encerrando processos..."
  if [[ -n "${FRONT_PID}" ]] && kill -0 "$FRONT_PID" 2>/dev/null; then
    kill "$FRONT_PID" 2>/dev/null || true
    wait "$FRONT_PID" 2>/dev/null || true
  fi
  if [[ -n "${API_PID}" ]] && kill -0 "$API_PID" 2>/dev/null; then
    kill "$API_PID" 2>/dev/null || true
    wait "$API_PID" 2>/dev/null || true
  fi
  echo "==> Parado."
}

trap cleanup EXIT INT TERM

if [[ ! -f "$API_DIR/.env" ]]; then
  echo "ERRO: $API_DIR/.env não encontrado. Copie de .env.example e configure DB_*."
  exit 1
fi

if [[ ! -f "$FRONT_DIR/.env" ]]; then
  echo "AVISO: $FRONT_DIR/.env ausente — criando com VITE_API_URL=http://localhost:8000/"
  echo 'VITE_API_URL=http://localhost:8000/' > "$FRONT_DIR/.env"
fi

if [[ ! -d "$API_DIR/vendor" ]]; then
  echo "ERRO: Dependências PHP ausentes. Rode: (cd api && composer install)"
  exit 1
fi

if [[ ! -d "$FRONT_DIR/node_modules" ]]; then
  echo "ERRO: Dependências npm ausentes. Rode: (cd front && npm install)"
  exit 1
fi

cat <<'EOF'
╔══════════════════════════════════════════════════════════╗
║                   Eclesia — ambiente local               ║
╠══════════════════════════════════════════════════════════╣
║  API:   http://localhost:8000                            ║
║  Front: http://localhost:5173                            ║
║  Health: http://localhost:8000/api/v1/health             ║
║                                                          ║
║  Super-admin (seed):                                     ║
║    e-mail: admin@eclesia.local                           ║
║    senha:  password                                      ║
║                                                          ║
║  Ctrl+C encerra API e front.                             ║
╚══════════════════════════════════════════════════════════╝
EOF

echo "==> Iniciando API (php artisan serve :8000)..."
(
  cd "$API_DIR"
  # libera porta se sobrou processo órfão de sessão anterior
  if command -v fuser >/dev/null 2>&1; then
    fuser -k 8000/tcp >/dev/null 2>&1 || true
  fi
  php artisan serve --host=127.0.0.1 --port=8000
) &
API_PID=$!

echo "==> Iniciando Front (npm run dev)..."
(
  cd "$FRONT_DIR"
  if command -v fuser >/dev/null 2>&1; then
    fuser -k 5173/tcp >/dev/null 2>&1 || true
  fi
  npm run dev -- --host 127.0.0.1 --port 5173 --strictPort
) &
FRONT_PID=$!

# Aguarda qualquer um sair; trap faz cleanup
wait -n "$API_PID" "$FRONT_PID" 2>/dev/null || wait
