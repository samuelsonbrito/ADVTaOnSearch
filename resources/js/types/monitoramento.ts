// Espelha app/Http/Resources/Monitoramento e as props do MonitoramentoController.
import type { Comunicacao } from './djen';

export type TipoAlvo = 'oab' | 'parte' | 'processo';

/** App\Http\Resources\Monitoramento\MonitoringTargetResource */
export interface AlvoMonitoramento {
    id: number;
    tipo: TipoAlvo;
    tipoRotulo: string;
    /** Processo já formatado no padrão CNJ. */
    valor: string;
    uf: string | null;
    apelido: string | null;
    descricao: string;
    ativo: boolean;
    /** ISO 8601 */
    ultimaVerificacaoEm: string | null;
    naoLidas: number;
    total: number;
}

/** App\Http\Resources\Monitoramento\AlertaResource */
export interface Alerta {
    id: number;
    lida: boolean;
    /** ISO 8601 */
    recebidaEm: string;
    alvo: { id: number; descricao: string };
    comunicacao: Comunicacao;
}

export type StatusAlerta = 'nao_lidas' | 'lidas' | 'todas';

export interface PreferenciasNotificacao {
    email: string;
    whatsapp: string | null;
    whatsappDriver: string;
    /** HH:MM */
    horario: string;
    diasAnteriores: number;
}

/** Props da página Monitoramento/Index. */
export interface MonitoramentoPageProps {
    alvos: AlvoMonitoramento[];
    alertas: {
        data: Alerta[];
        meta: {
            pagina: number;
            porPagina: number;
            total: number;
            ultimaPagina: number;
        };
    };
    filtros: { alvo: number | null; status: StatusAlerta };
    totalNaoLidas: number;
    notificacoes: PreferenciasNotificacao;
    tiposAlvo: { valor: TipoAlvo; rotulo: string }[];
    ufs: string[];
    limiteAlvos: number;
}
