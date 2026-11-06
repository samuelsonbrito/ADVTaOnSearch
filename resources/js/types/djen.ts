// Espelha os API Resources de app/Http/Resources/Djen e as props do
// ConsultaController. Mantenha os dois lados sincronizados.

/** App\Http\Resources\Djen\ComunicacaoResource → destinatarios[] */
export interface Destinatario {
    nome: string;
}

/** App\Http\Resources\Djen\ComunicacaoResource → advogados[] */
export interface Advogado {
    nome: string;
    numeroOab: string | null;
    ufOab: string | null;
}

/** App\Http\Resources\Djen\ComunicacaoResource */
export interface Comunicacao {
    hash: string;
    tribunal: string;
    orgaoJulgador: string | null;
    tipoComunicacao: string | null;
    tipoDocumento: string | null;
    /** AAAA-MM-DD */
    dataDisponibilizacao: string;
    /** Formatado no padrão CNJ quando possível. */
    numeroProcesso: string;
    destinatarios: Destinatario[];
    advogados: Advogado[];
    trecho: string;
    /** HTML já sanitizado no servidor (App\Support\HtmlSanitizer). */
    textoHtml: string;
    linkDocumento: string | null;
}

/** App\Http\Resources\Djen\ResultadoConsultaResource */
export interface ResultadoConsulta {
    data: Comunicacao[];
    meta: {
        pagina: number;
        porPagina: number;
        total: number;
        ultimaPagina: number;
    };
}

/** App\Http\Resources\Djen\TribunalResource */
export interface Tribunal {
    sigla: string;
    nome: string;
}

/** ConsultaController::erro() */
export interface ErroConsulta {
    tipo: 'limite' | 'bloqueado' | 'indisponivel' | 'inesperado';
    mensagem: string;
}

/** Filtros da query string (ConsultaRequest). */
export interface FiltrosConsulta {
    oab: string | null;
    uf: string | null;
    parte: string | null;
    advogado: string | null;
    processo: string | null;
    tribunal: string | null;
    inicio: string | null;
    fim: string | null;
}

/** Props da página Consulta/Index. */
export interface ConsultaPageProps {
    filtros: FiltrosConsulta;
    resultados: ResultadoConsulta | null;
    erro: ErroConsulta | null;
    tribunais: Tribunal[];
    ufs: string[];
}
