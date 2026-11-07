import { router } from '@inertiajs/vue3';
import type { Periodo } from '@/types/admin';

/** Query string que reproduz o período (como App\Support\Admin\Periodo::toQuery). */
export function queryPeriodo(p: Periodo): Record<string, string> {
    return p.chave === 'personalizado'
        ? { periodo: p.chave, inicio: p.inicio, fim: p.fim }
        : { periodo: p.chave };
}

/** Navega na mesma tela do admin, sem valores vazios na URL. */
export function visitarAdmin(
    rota: string,
    query: Record<string, string | number | boolean | null | undefined>,
    aoMudarCarregando?: (carregando: boolean) => void,
): void {
    const limpa: Record<string, string | number> = {};

    for (const [chave, valor] of Object.entries(query)) {
        if (
            valor === null ||
            valor === undefined ||
            valor === '' ||
            valor === false
        ) {
            continue;
        }

        limpa[chave] = valor === true ? 1 : valor;
    }

    router.get(route(rota), limpa, {
        preserveState: true,
        preserveScroll: true,
        onStart: () => aoMudarCarregando?.(true),
        onFinish: () => aoMudarCarregando?.(false),
    });
}
