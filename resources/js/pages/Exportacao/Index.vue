<script setup lang="ts">
import { Head, Link, router, usePoll } from '@inertiajs/vue3';
import {
    AlertTriangle,
    Download,
    FileDown,
    Loader2,
    Trash2,
} from '@lucide/vue';
import { watch } from 'vue';
import EstadoVazio from '@/components/EstadoVazio.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import type { Exportacao, ExportacaoPageProps } from '@/types/exportacao';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Exportações', href: '/exportacoes' }],
    },
});

const props = defineProps<ExportacaoPageProps>();

// Atualiza a lista sozinha enquanto houver exportação na fila.
const { start, stop } = usePoll(
    3000,
    { only: ['exportacoes', 'emAndamento'] },
    { autoStart: false },
);

watch(
    () => props.emAndamento,
    (andamento) => (andamento ? start() : stop()),
    { immediate: true },
);

function dataHora(iso: string): string {
    return new Date(iso).toLocaleString('pt-BR', {
        dateStyle: 'short',
        timeStyle: 'short',
    });
}

function excluir(e: Exportacao) {
    if (window.confirm('Excluir esta exportação e o arquivo?')) {
        router.delete(route('exportacoes.destroy', e.id), {
            preserveScroll: true,
        });
    }
}

const variante = {
    pendente: 'secondary',
    processando: 'secondary',
    concluida: 'default',
    falhou: 'destructive',
} as const;
</script>

<template>
    <Head title="Exportações" />

    <div class="grid gap-6 p-4">
        <div>
            <h1 class="text-xl font-semibold tracking-tight">Exportações</h1>
            <p class="mt-1 max-w-2xl text-sm text-muted-foreground">
                Selecione comunicações na
                <Link :href="route('consulta.index')" class="underline"
                    >Consulta</Link
                >
                ou no
                <Link :href="route('monitoramento.index')" class="underline"
                    >Monitoramento</Link
                >
                e exporte em CSV, Excel ou PDF. Seleções grandes são geradas em
                segundo plano e avisamos por e-mail. Os arquivos ficam
                disponíveis por {{ diasRetencao }} dias.
            </p>
        </div>

        <EstadoVazio
            v-if="exportacoes.length === 0"
            titulo="Nenhuma exportação ainda"
            descricao="Use as caixas de seleção nos resultados e o botão Exportar."
        >
            <template #icone><FileDown class="size-10" /></template>
        </EstadoVazio>

        <ul v-else class="grid gap-2" aria-label="Exportações">
            <li
                v-for="e in exportacoes"
                :key="e.id"
                class="flex flex-wrap items-center gap-3 rounded-xl border bg-card p-4"
                :data-testid="`exportacao-${e.id}`"
            >
                <div class="min-w-0 flex-1">
                    <p class="flex flex-wrap items-center gap-2 font-medium">
                        {{ e.formatoRotulo }}
                        <span class="text-sm font-normal text-muted-foreground">
                            · {{ e.origemRotulo }}
                            <template v-if="e.totalItens !== null">
                                · {{ e.totalItens }}
                                {{
                                    e.totalItens === 1
                                        ? 'comunicação'
                                        : 'comunicações'
                                }}
                            </template>
                            <template v-else-if="e.todos">
                                · todos os resultados</template
                            >
                        </span>
                    </p>
                    <p class="text-xs text-muted-foreground">
                        Pedida em {{ dataHora(e.criadaEm) }} · expira em
                        {{ dataHora(e.expiraEm) }}
                    </p>
                    <p
                        v-if="e.erro"
                        class="mt-1 flex items-center gap-1 text-xs text-destructive"
                    >
                        <AlertTriangle class="size-3.5" aria-hidden="true" />
                        {{ e.erro }}
                    </p>
                </div>

                <Badge :variant="variante[e.status]" data-testid="status">
                    <Loader2
                        v-if="
                            e.status === 'pendente' ||
                            e.status === 'processando'
                        "
                        class="size-3 animate-spin"
                        aria-hidden="true"
                    />
                    {{ e.statusRotulo }}
                </Badge>

                <Button v-if="e.downloadUrl" as-child size="sm">
                    <a :href="e.downloadUrl" data-testid="baixar">
                        <Download class="size-4" aria-hidden="true" />
                        Baixar
                    </a>
                </Button>
                <Button
                    variant="ghost"
                    size="sm"
                    class="text-destructive hover:text-destructive"
                    :aria-label="`Excluir exportação ${e.id}`"
                    @click="excluir(e)"
                >
                    <Trash2 class="size-4" aria-hidden="true" />
                </Button>
            </li>
        </ul>
    </div>
</template>
