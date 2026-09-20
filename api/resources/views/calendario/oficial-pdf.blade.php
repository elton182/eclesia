<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <style>
    @page { margin: 12mm 10mm; }
    body {
      font-family: DejaVu Sans, sans-serif;
      font-size: 12px;
      color: {{ $corTexto }};
    }
    .header-table {
      width: 100%;
      border-collapse: collapse;
      margin-bottom: 6px;
    }
    .header-table td {
      border: none;
      vertical-align: middle;
      padding: 0;
    }
    .header-logo {
      width: 18%;
      text-align: center;
    }
    .header-logo img {
      max-height: 72px;
      max-width: 110px;
    }
    .header-center {
      width: 64%;
      text-align: center;
      padding: 0 8px;
    }
    .header-titulo {
      font-size: 18px;
      font-weight: bold;
      color: #000000;
      text-transform: uppercase;
      margin: 0 0 3px;
      letter-spacing: 0.3px;
    }
    .header-localidade {
      font-size: 13px;
      font-weight: bold;
      color: #000000;
      margin: 0 0 4px;
    }
    .header-mes {
      font-size: 16px;
      font-weight: bold;
      color: {{ $corPrimary }};
      text-transform: uppercase;
      margin: 0;
    }
    .header-rule {
      border: none;
      border-top: 1px solid #9E9E9E;
      margin: 6px 0 12px;
    }
    table.grade {
      width: 100%;
      border-collapse: collapse;
      margin-bottom: 12px;
      table-layout: fixed;
    }
    table.grade th,
    table.grade td {
      border: 1px solid #333;
      padding: 4px 5px;
      vertical-align: middle;
      text-align: center;
    }
    .titulo-bloco {
      background: {{ $corPrimary }};
      color: {{ $corOnPrimary }};
      font-weight: bold;
      font-size: 13px;
      letter-spacing: 0.5px;
      padding: 6px 4px !important;
    }
    .dia-header {
      background: {{ $corSecondary }};
      color: {{ $corTexto }};
      font-weight: bold;
      font-size: 11px;
    }
    .tempo-liturgico {
      background: #E8F5E9;
      color: #2E7D32;
      font-weight: bold;
      font-size: 10px;
    }
    .tempo-liturgico .tempo-label {
      text-align: left;
      padding-left: 6px;
      color: {{ $corTexto }};
    }
    .dia-header .dia-nome {
      text-align: left;
      padding-left: 6px;
    }
    .local-cell {
      text-align: left !important;
      font-weight: bold;
      font-size: 11px;
      width: 18%;
    }
    .hora-cell {
      width: 9%;
      font-weight: bold;
      font-size: 11px;
    }
    .cel {
      font-size: 11px;
    }
    .cancelado {
      color: #C62828;
      font-weight: bold;
    }
    .ref {
      color: #C62828;
      font-size: 9px;
    }
    .sec-title {
      font-size: 14px;
      font-weight: bold;
      color: {{ $corPrimary }};
      margin: 12px 0 4px;
    }
    .festa-titulo {
      font-size: 13px;
      font-weight: bold;
      color: {{ $corPrimary }};
      margin: 8px 0 2px;
      text-transform: uppercase;
    }
    .lista {
      margin: 0 0 8px;
      padding-left: 0;
      list-style: none;
    }
    .lista li {
      margin: 2px 0;
      padding-left: 12px;
      position: relative;
    }
    .lista-obs li {
      margin: 0 0 10px;
      padding-bottom: 6px;
    }
    .lista-obs li:last-child {
      margin-bottom: 0;
      padding-bottom: 0;
    }
    .lista li:before {
      content: "✓";
      position: absolute;
      left: 0;
      color: {{ $corPrimary }};
      font-weight: bold;
    }
    .lista strong {
      color: {{ $corTexto }};
    }
    .obs-desc {
      display: inline;
    }
    .page-break {
      page-break-before: always;
    }
  </style>
</head>
<body>
  <table class="header-table">
    <tr>
      <td class="header-logo">
        @if(!empty($logoDioceseSrc))
          <img src="{{ $logoDioceseSrc }}" alt="Diocese">
        @endif
      </td>
      <td class="header-center">
        <div class="header-titulo">{{ $mensal->titulo ?: ($mensal->igreja->nome ?? 'Paróquia') }}</div>
        @if(!empty($localidadeLinha))
          <div class="header-localidade">{{ $localidadeLinha }}</div>
        @elseif($mensal->subtitulo)
          <div class="header-localidade">{{ $mensal->subtitulo }}</div>
        @endif
        <div class="header-mes">{{ $mesNome }} – {{ $mensal->ano }}</div>
      </td>
      <td class="header-logo">
        @if(!empty($logoParoquiaSrc))
          <img src="{{ $logoParoquiaSrc }}" alt="Paróquia">
        @endif
      </td>
    </tr>
  </table>
  <hr class="header-rule">

  {{-- 1. Celebrações na semana (sempre o mês todo: SEG–SEX) --}}
  @php
    $maxColsSemana = count($gradeSemana) > 0
      ? max(array_map(fn ($g) => count($g['datas']), $gradeSemana))
      : 5;
    $colspanSemana = 2 + $maxColsSemana;
  @endphp
  <table class="grade">
    <tr>
      <td class="titulo-bloco" colspan="{{ $colspanSemana }}">CELEBRAÇÕES NA SEMANA</td>
    </tr>
    @foreach($gradeSemana as $bloco)
      @php $nDatas = count($bloco['datas']); @endphp
      <tr class="dia-header">
        <td class="dia-nome" colspan="2">{{ $bloco['diaLabel'] }}</td>
        @foreach($bloco['datas'] as $d)
          <td>{{ $d }}</td>
        @endforeach
        @for($i = $nDatas; $i < $maxColsSemana; $i++)
          <td></td>
        @endfor
      </tr>
      @foreach($bloco['linhas'] as $linha)
        <tr>
          <td class="local-cell">{{ $linha['local'] }}</td>
          <td class="hora-cell">{{ $linha['hora'] }}</td>
          @foreach($linha['celulas'] as $cel)
            <td class="cel">
              @if($cel['cancelado'])
                <span class="cancelado">{{ $cel['texto'] }}</span>
              @elseif($cel['texto'] === '-')
                -
              @else
                <span @if($cel['cor']) style="color: {{ $cel['cor'] }}; font-weight: bold;" @endif>{{ $cel['texto'] }}</span>
                @if($cel['ref'])
                  <span class="ref">({{ $cel['ref'] }})</span>
                @endif
              @endif
            </td>
          @endforeach
          @for($i = $nDatas; $i < $maxColsSemana; $i++)
            <td></td>
          @endfor
        </tr>
      @endforeach
    @endforeach
  </table>

  {{-- 2. Celebrações nos sábados e domingos --}}
  @if(count($gradeFds) > 0)
    @php
      $maxColsFds = max(array_map(fn ($g) => count($g['datas']), $gradeFds));
      $colspanFds = 2 + $maxColsFds;
      $temposLabels = $temposLiturgicosLabels ?? [];
      $temAlgumTempo = collect($temposLabels)->contains(fn ($t) => trim((string) $t) !== '');
    @endphp
    <table class="grade">
      <tr>
        <td class="titulo-bloco" colspan="{{ $colspanFds }}">CELEBRAÇÕES NOS SÁBADOS E DOMINGOS</td>
      </tr>
      @if($temAlgumTempo)
        <tr class="tempo-liturgico">
          <td class="tempo-label" colspan="2">Tempo Litúrgico</td>
          @foreach($temposLabels as $rotuloTempo)
            <td>{{ $rotuloTempo }}</td>
          @endforeach
          @for($i = count($temposLabels); $i < $maxColsFds; $i++)
            <td></td>
          @endfor
        </tr>
      @endif
      @foreach($gradeFds as $bloco)
        @php $nDatas = count($bloco['datas']); @endphp
        <tr class="dia-header">
          <td class="dia-nome" colspan="2">{{ $bloco['diaLabel'] }}</td>
          @foreach($bloco['datas'] as $d)
            <td>{{ $d }}</td>
          @endforeach
          @for($i = $nDatas; $i < $maxColsFds; $i++)
            <td></td>
          @endfor
        </tr>
        @foreach($bloco['linhas'] as $linha)
          <tr>
            <td class="local-cell">{{ $linha['local'] }}</td>
            <td class="hora-cell">{{ $linha['hora'] }}</td>
            @foreach($linha['celulas'] as $cel)
              <td class="cel">
                @if($cel['cancelado'])
                  <span class="cancelado">{{ $cel['texto'] }}</span>
                @elseif($cel['texto'] === '-')
                  -
                @else
                  <span @if($cel['cor']) style="color: {{ $cel['cor'] }}; font-weight: bold;" @endif>{{ $cel['texto'] }}</span>
                  @if($cel['ref'])
                    <span class="ref">({{ $cel['ref'] }})</span>
                  @endif
                @endif
              </td>
            @endforeach
            @for($i = $nDatas; $i < $maxColsFds; $i++)
              <td></td>
            @endfor
          </tr>
        @endforeach
      @endforeach
    </table>
  @endif

  {{-- 3. Festas --}}
  @if($festas->isNotEmpty())
    @php
      $festasPorTitulo = $festas->groupBy(fn ($i) => $i->titulo ?: ($i->tipo?->nome ?: 'Festa'));
    @endphp
    @foreach($festasPorTitulo as $tituloFesta => $itensFesta)
      <div class="festa-titulo">{{ mb_strtoupper($tituloFesta) }}:</div>
      <ul class="lista">
        @foreach($itensFesta->sortBy(
          fn ($i) => $i->data->format('Y-m-d').'-'.str_pad(substr((string) ($i->hora ?? '99:99'), 0, 5), 5, '0', STR_PAD_LEFT)
        ) as $item)
          <li>
            <strong>
              {{ $item->data->format('d.m') }}
              @if($item->hora)
                às {{ \App\Services\CalendarioService::formatHoraPdf($item->hora) }}
              @endif
              @if($item->local)
                ({{ $item->local->nome }})
              @endif
              @if($item->notas)
                — {{ $item->notas }}
              @endif:
            </strong>
            @if($item->celebrante_nome)
              <span @if(!empty($corMap[mb_strtolower(trim($item->celebrante_nome))]))
                style="color: {{ $corMap[mb_strtolower(trim($item->celebrante_nome))] }}; font-weight: bold;"
              @endif>{{ $item->celebrante_nome }}</span>
            @endif
          </li>
        @endforeach
      </ul>
    @endforeach
  @endif

  {{-- 4. Observações móveis --}}
  @if($obsMoveis->isNotEmpty())
    <div class="sec-title">*Observações móveis:</div>
    <ul class="lista">
      @foreach($obsMoveis as $item)
        <li>
          <strong>
            {{ $item->data->format('d.m') }}
            @if($item->hora) às {{ \App\Services\CalendarioService::formatHoraPdf($item->hora) }}@endif:
          </strong>
          {{ $item->titulo ?: $item->notas }}
          @if($item->celebrante_nome)
            : <span @if(!empty($corMap[mb_strtolower(trim($item->celebrante_nome))]))
              style="color: {{ $corMap[mb_strtolower(trim($item->celebrante_nome))] }}; font-weight: bold;"
            @endif>{{ $item->celebrante_nome }}</span>
          @endif
        </li>
      @endforeach
    </ul>
  @endif

  {{-- 5. Casamentos --}}
  @if($casamentos->isNotEmpty())
    <div class="sec-title">Casamentos:</div>
    <ul class="lista">
      @foreach($casamentos as $item)
        <li>
          <strong>
            {{ $item->data->format('d.m') }}
            @if($item->hora) às {{ \App\Services\CalendarioService::formatHoraPdf($item->hora) }}@endif
            @if($item->local) ({{ $item->local->nome }})@endif:
          </strong>
          @if($item->celebrante_nome)
            <span @if(!empty($corMap[mb_strtolower(trim($item->celebrante_nome))]))
              style="color: {{ $corMap[mb_strtolower(trim($item->celebrante_nome))] }}; font-weight: bold;"
            @endif>{{ $item->celebrante_nome }}</span>
          @endif
        </li>
      @endforeach
    </ul>
  @endif

  {{-- 6. Observações fixas --}}
  @php $listaObs = $observacoes ?? collect(); @endphp
  @if($listaObs->isNotEmpty())
    <div class="sec-title">Observações fixas:</div>
    <ul class="lista lista-obs">
      @foreach($listaObs->values() as $i => $obs)
        <li>
          <strong>{{ $i + 1 }}) {{ $obs->titulo }}:</strong>
          <span class="obs-desc">{!! nl2br(e($obs->descricao)) !!}</span>
        </li>
      @endforeach
    </ul>
  @endif
</body>
</html>
