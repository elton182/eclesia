<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>Equipes ECC</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #222; }
        h1 { font-size: 18px; margin: 0 0 4px; }
        .meta { margin-bottom: 16px; color: #555; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #ccc; padding: 6px 8px; text-align: left; vertical-align: top; }
        th { background: #f3f3f3; font-weight: bold; }
        .empty { color: #666; margin-top: 12px; }
        .login { font-family: DejaVu Sans Mono, monospace; font-size: 10px; }
    </style>
</head>
<body>
    <h1>Equipes ECC</h1>
    <div class="meta">
        {{ $igreja?->nome ?? 'Igreja' }}
        · Gerado em {{ $geradoEm }}
    </div>

    @if(count($linhas) === 0)
        <p class="empty">Nenhuma equipe com login de acesso.</p>
    @else
        <table>
            <thead>
                <tr>
                    <th style="width: 28%">Equipe</th>
                    <th style="width: 44%">Piloto</th>
                    <th style="width: 28%">Login</th>
                </tr>
            </thead>
            <tbody>
                @foreach($linhas as $linha)
                    <tr>
                        <td>{{ $linha['equipe'] }}</td>
                        <td>{{ $linha['piloto'] }}</td>
                        <td class="login">{{ $linha['login'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</body>
</html>
