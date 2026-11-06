import { router } from '@inertiajs/vue3';
import { ref } from 'vue';
import { toast } from 'vue-sonner';
import type { PedidoExportacao } from '@/types/exportacao';

/**
 * Envia um pedido de exportação. Seleções pequenas voltam como
 * Inertia::location para o download (o navegador baixa o arquivo); as
 * grandes vão para a fila e redirecionam para a aba Exportações.
 */
export function useExportar() {
    const enviando = ref(false);

    function exportar(pedido: PedidoExportacao, aoConcluir?: () => void): void {
        router.post(
            route('exportacoes.store'),
            { ...pedido },
            {
                preserveScroll: true,
                preserveState: true,
                onStart: () => (enviando.value = true),
                onFinish: () => (enviando.value = false),
                onSuccess: () => aoConcluir?.(),
                onError: (erros) => {
                    toast.error(
                        Object.values(erros)[0] ??
                            'Não foi possível iniciar a exportação.',
                    );
                },
            },
        );
    }

    return { enviando, exportar };
}
