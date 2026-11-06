<?php

namespace App\Services\Exportacao;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithCustomCsvSettings;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

/** Fonte de dados para CSV e XLSX (maatwebsite/excel). */
final class PlanilhaComunicacoes implements FromArray, ShouldAutoSize, WithCustomCsvSettings, WithHeadings, WithTitle
{
    /** Limite de caracteres de uma célula no Excel é 32.767. */
    private const MAX_CELULA = 32000;

    /**
     * @param  list<LinhaExportacao>  $linhas
     */
    public function __construct(
        private readonly array $linhas,
        private readonly bool $comAlvo = false,
    ) {}

    /** @return list<string> */
    public function headings(): array
    {
        $cabecalho = [
            'Data de disponibilização', 'Tribunal', 'Órgão julgador', 'Tipo de comunicação',
            'Tipo de documento', 'Número do processo', 'Destinatários', 'Advogados', 'Texto', 'Link do documento',
        ];

        return $this->comAlvo ? [...$cabecalho, 'Alvo', 'Lida'] : $cabecalho;
    }

    /** @return list<list<string>> */
    public function array(): array
    {
        return array_map(function (LinhaExportacao $l): array {
            $c = $l->comunicacao;

            $linha = [
                $c->dataDisponibilizacao->format('d/m/Y'),
                $c->tribunal,
                $c->orgaoJulgador ?? '',
                $c->tipoComunicacao ?? '',
                $c->tipoDocumento ?? '',
                $c->numeroProcessoFormatado(),
                implode('; ', array_map(fn ($d) => $d->nome, $c->destinatarios)),
                implode('; ', array_map(fn ($a) => $a->numeroOab ? "{$a->nome} (OAB {$a->numeroOab}/{$a->ufOab})" : $a->nome, $c->advogados)),
                mb_substr($c->textoPuro(), 0, self::MAX_CELULA),
                $c->linkDocumento ?? '',
            ];

            if ($this->comAlvo) {
                $linha[] = $l->alvo ?? '';
                $linha[] = $l->lida ? 'Sim' : 'Não';
            }

            return array_map(self::celulaSegura(...), $linha);
        }, $this->linhas);
    }

    /**
     * Neutraliza injeção de fórmula (CSV/Excel): conteúdo vindo de fora que
     * começa com = + - @ ou caracteres de controle vira texto literal.
     */
    public static function celulaSegura(string $valor): string
    {
        return preg_match('/^[=+\-@\t\r]/', $valor) ? "'".$valor : $valor;
    }

    /** CSV no padrão do Excel em português: ponto e vírgula e BOM UTF-8. */
    public function getCsvSettings(): array
    {
        return ['delimiter' => ';', 'use_bom' => true];
    }

    public function title(): string
    {
        return 'Comunicações';
    }
}
