{{-- Modelo do PDF de exportação (não é tela: renderizado pelo dompdf). Todo conteúdo é escapado. --}}
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>Comunicações processuais</title>
    <style>
        @page { margin: 28px 32px 40px; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #1f2937; }
        h1 { font-size: 16px; margin: 0 0 2px; }
        .sub { color: #6b7280; margin-bottom: 14px; }
        .item { border: 1px solid #e5e7eb; border-radius: 6px; padding: 10px 12px; margin-bottom: 10px; page-break-inside: avoid; }
        .topo { margin-bottom: 4px; }
        .tag { display: inline-block; background: #111827; color: #fff; border-radius: 9px; padding: 1px 7px; font-size: 9px; }
        .tag.claro { background: #f3f4f6; color: #374151; }
        .data { float: right; color: #6b7280; }
        .processo { font-family: DejaVu Sans Mono, monospace; font-size: 12px; font-weight: bold; margin: 4px 0; }
        table.meta { width: 100%; border-collapse: collapse; margin-bottom: 6px; }
        table.meta td { padding: 1px 0; vertical-align: top; }
        table.meta td.r { width: 95px; color: #6b7280; }
        .texto { white-space: pre-wrap; line-height: 1.45; }
        .rodape { position: fixed; bottom: -24px; left: 0; right: 0; text-align: center; color: #9ca3af; font-size: 8px; }
        .link { color: #2563eb; word-break: break-all; }
    </style>
</head>
<body>
    <div class="rodape">
        {{ config('app.name') }} · Dados públicos do DJEN (CNJ) · gerado em {{ $geradaEm->format('d/m/Y H:i') }}
    </div>

    <h1>Comunicações processuais</h1>
    <div class="sub">
        {{ count($linhas) }} {{ count($linhas) === 1 ? 'comunicação' : 'comunicações' }} · origem: {{ $origem }}
    </div>

    @forelse ($linhas as $linha)
        @php($c = $linha->comunicacao)
        <div class="item">
            <div class="topo">
                <span class="tag">{{ $c->tribunal }}</span>
                @if ($c->tipoComunicacao) <span class="tag claro">{{ $c->tipoComunicacao }}</span> @endif
                @if ($c->tipoDocumento) <span class="tag claro">{{ $c->tipoDocumento }}</span> @endif
                <span class="data">{{ $c->dataDisponibilizacao->format('d/m/Y') }}</span>
            </div>
            <div class="processo">{{ $c->numeroProcessoFormatado() }}</div>
            <table class="meta">
                @if ($c->orgaoJulgador)
                    <tr><td class="r">Órgão julgador</td><td>{{ $c->orgaoJulgador }}</td></tr>
                @endif
                @if ($c->destinatarios)
                    <tr><td class="r">Destinatários</td><td>{{ collect($c->destinatarios)->pluck('nome')->join(' · ') }}</td></tr>
                @endif
                @if ($c->advogados)
                    <tr><td class="r">Advogados</td><td>{{ collect($c->advogados)->map(fn ($a) => $a->numeroOab ? "{$a->nome} (OAB {$a->numeroOab}/{$a->ufOab})" : $a->nome)->join(' · ') }}</td></tr>
                @endif
                @if ($comAlvo && $linha->alvo)
                    <tr><td class="r">Alvo</td><td>{{ $linha->alvo }}</td></tr>
                @endif
                @if ($c->linkDocumento)
                    <tr><td class="r">Documento</td><td class="link">{{ $c->linkDocumento }}</td></tr>
                @endif
            </table>
            <div class="texto">{{ $c->textoPuro() }}</div>
        </div>
    @empty
        <p>Nenhuma comunicação encontrada.</p>
    @endforelse
</body>
</html>
