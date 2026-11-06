// Espelha app/Http/Resources/Exportacao e as props do ExportacaoController.

export type FormatoExportacao = 'csv' | 'xlsx' | 'pdf';
export type OrigemExportacao = 'consulta' | 'monitoramento';
export type StatusExportacao =
    | 'pendente'
    | 'processando'
    | 'concluida'
    | 'falhou';

/** App\Http\Resources\Exportacao\ExportacaoResource */
export interface Exportacao {
    id: number;
    origem: OrigemExportacao;
    origemRotulo: string;
    formato: FormatoExportacao;
    formatoRotulo: string;
    status: StatusExportacao;
    statusRotulo: string;
    todos: boolean;
    totalItens: number | null;
    erro: string | null;
    /** ISO 8601 */
    criadaEm: string;
    concluidaEm: string | null;
    expiraEm: string;
    downloadUrl: string | null;
}

/** Props da página Exportacao/Index. */
export interface ExportacaoPageProps {
    exportacoes: Exportacao[];
    emAndamento: boolean;
    diasRetencao: number;
    formatos: { valor: FormatoExportacao; rotulo: string }[];
}

/** Corpo do POST exportacoes.store (StoreExportacaoRequest). */
export interface PedidoExportacao {
    origem: OrigemExportacao;
    formato: FormatoExportacao;
    todos: boolean;
    filtros: Record<string, string | number | null>;
    selecao: { pagina: number; hashes: string[] }[] | number[];
}
