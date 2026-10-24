<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>Planejamento {{ $anual->ano }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #222; }
        h1 { font-size: 18px; margin: 0 0 4px; }
        h2 { font-size: 15px; margin: 0 0 8px; color: #4E1220; }
        .meta { margin-bottom: 12px; color: #555; }
        .secao { page-break-after: always; padding-bottom: 8px; }
        .secao:last-child { page-break-after: auto; }
        .link-box { margin: 10px 0 14px; text-align: center; }
        .qr { width: 120px; height: 120px; }
        .link-url {
            font-family: DejaVu Sans Mono, monospace;
            font-size: 9px;
            word-break: break-all;
            color: #333;
            margin-top: 8px;
        }
        table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        th, td { border: 1px solid #ccc; padding: 5px 7px; text-align: left; vertical-align: top; }
        th { background: #f3f3f3; font-weight: bold; }
        .status { font-size: 10px; text-transform: uppercase; }
        .empty { color: #666; font-style: italic; }
    </style>
</head>
<body>
    <h1>Planejamento pastoral {{ $anual->ano }}</h1>
    <div class="meta">
        {{ $igreja?->nome ?? 'Igreja' }}
        · Status: {{ $anual->status }}
        @if($mesNome)
            · Mês: {{ $mesNome }}
        @endif
    </div>

    @forelse($secoes as $secao)
        <div class="secao">
            <h2>{{ $secao['pastoral']->nome }}</h2>
            <div class="link-box">
                <img class="qr" src="{{ $secao['qr_data_uri'] }}" alt="QR Code">
                <div class="link-url">{{ $secao['link'] }}</div>
            </div>

            @if($secao['eventos']->isEmpty())
                <p class="empty">Nenhum evento nesta pastoral{{ $mesNome ? ' neste mês' : '' }}.</p>
            @else
                <table>
                    <thead>
                        <tr>
                            <th>Data</th>
                            <th>Horário</th>
                            <th>Título</th>
                            <th>Local</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($secao['eventos'] as $evento)
                            @php
                                $data = $evento->data_inicio?->format('d/m/Y');
                                if ($evento->data_fim && $evento->data_fim->format('Y-m-d') !== $evento->data_inicio?->format('Y-m-d')) {
                                    $data .= ' – '.$evento->data_fim->format('d/m/Y');
                                }
                                $horaIni = $evento->hora_inicio;
                                $horaFim = $evento->hora_fim;
                                if ($horaIni instanceof \DateTimeInterface) { $horaIni = $horaIni->format('H:i'); }
                                if ($horaFim instanceof \DateTimeInterface) { $horaFim = $horaFim->format('H:i'); }
                                if (is_string($horaIni) && preg_match('/^(\d{2}:\d{2})/', $horaIni, $m)) { $horaIni = $m[1]; }
                                if (is_string($horaFim) && preg_match('/^(\d{2}:\d{2})/', $horaFim, $m)) { $horaFim = $m[1]; }
                                $horario = trim(($horaIni ?? '').($horaFim ? ' – '.$horaFim : ''));
                                $locais = $evento->locais->pluck('nome')->implode(', ');
                                if ($evento->local_texto) {
                                    $locais = $locais !== '' ? $locais.'; '.$evento->local_texto : $evento->local_texto;
                                }
                            @endphp
                            <tr>
                                <td>{{ $data }}</td>
                                <td>{{ $horario !== '' ? $horario : '—' }}</td>
                                <td>{{ $evento->titulo }}</td>
                                <td>{{ $locais !== '' ? $locais : '—' }}</td>
                                <td class="status">{{ $evento->status_solicitacao }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    @empty
        <p class="empty">Nenhuma pastoral ativa cadastrada.</p>
    @endforelse
</body>
</html>
