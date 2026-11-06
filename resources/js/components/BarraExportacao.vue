<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Download, X } from '@lucide/vue';
import { ref } from 'vue';
import { Button } from '@/components/ui/button';
import type { FormatoExportacao } from '@/types/exportacao';

withDefaults(
    defineProps<{
        /** Itens selecionados. */
        quantidade: number;
        /** Total de resultados do filtro atual (para "exportar todos"). */
        total: number;
        logado: boolean;
        enviando?: boolean;
        /** Acima deste número, a exportação vai para a fila. */
        limiteSincrono?: number;
    }>(),
    { enviando: false, limiteSincrono: 100 },
);

const emit = defineEmits<{
    exportar: [pedido: { formato: FormatoExportacao; todos: boolean }];
    limpar: [];
}>();

const formato = ref<FormatoExportacao>('xlsx');

const formatos: { valor: FormatoExportacao; rotulo: string }[] = [
    { valor: 'xlsx', rotulo: 'Excel (XLSX)' },
    { valor: 'csv', rotulo: 'CSV' },
    { valor: 'pdf', rotulo: 'PDF' },
];
</script>

<template>
    <div
        v-if="total > 0"
        class="sticky bottom-3 z-10 flex flex-wrap items-center gap-2 rounded-xl border bg-card/95 p-3 shadow-lg backdrop-blur"
        role="region"
        aria-label="Exportação"
        data-testid="barra-exportacao"
    >
        <template v-if="logado">
            <span class="text-sm" data-testid="quantidade-selecionada">
                <strong>{{ quantidade }}</strong>
                {{ quantidade === 1 ? 'selecionada' : 'selecionadas' }}
            </span>
            <Button
                v-if="quantidade > 0"
                variant="ghost"
                size="sm"
                class="h-8 px-2"
                data-testid="limpar-selecao"
                @click="emit('limpar')"
            >
                <X class="size-4" aria-hidden="true" />
                Limpar
            </Button>

            <div class="ml-auto flex flex-wrap items-center gap-2">
                <label for="formato-exportacao" class="sr-only">Formato</label>
                <select
                    id="formato-exportacao"
                    v-model="formato"
                    class="h-8 rounded-md border border-input bg-transparent px-2 text-sm dark:bg-input/30"
                >
                    <option
                        v-for="f in formatos"
                        :key="f.valor"
                        :value="f.valor"
                    >
                        {{ f.rotulo }}
                    </option>
                </select>
                <Button
                    size="sm"
                    :disabled="enviando || quantidade === 0"
                    data-testid="exportar-selecionadas"
                    @click="emit('exportar', { formato, todos: false })"
                >
                    <Download class="size-4" aria-hidden="true" />
                    Exportar selecionadas
                </Button>
                <Button
                    size="sm"
                    variant="outline"
                    :disabled="enviando"
                    :title="
                        total > limiteSincrono
                            ? 'Será gerado em segundo plano; avisaremos por e-mail.'
                            : undefined
                    "
                    data-testid="exportar-todas"
                    @click="emit('exportar', { formato, todos: true })"
                >
                    Exportar todas ({{ total.toLocaleString('pt-BR') }})
                </Button>
            </div>
        </template>

        <p
            v-else
            class="text-sm text-muted-foreground"
            data-testid="exportar-login"
        >
            <Link
                :href="route('login')"
                class="font-medium text-foreground underline"
            >
                Entre
            </Link>
            para selecionar e exportar resultados em CSV, Excel ou PDF.
        </p>
    </div>
</template>
