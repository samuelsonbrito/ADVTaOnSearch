<script setup lang="ts">
import { computed } from 'vue';
import type { Formato } from '@/lib/formatar';
import { formatarValor } from '@/lib/formatar';
import type { Fatia } from '@/types/admin';

/**
 * Barras horizontais com rótulo e valor escritos (sem depender de cor nem de
 * hover). Uma série só: a mesma cor para todas as barras.
 */
const props = withDefaults(
    defineProps<{
        itens: Fatia[];
        formato?: Formato;
        /** Mostra a participação de cada item no total. */
        comPercentual?: boolean;
        vazio?: string;
    }>(),
    { formato: 'numero', comPercentual: true, vazio: 'Sem dados no período.' },
);

const maximo = computed(() => Math.max(1, ...props.itens.map((i) => i.total)));
const soma = computed(() => props.itens.reduce((s, i) => s + i.total, 0));

function percentual(total: number): string {
    return soma.value > 0 ? `${Math.round((total * 100) / soma.value)}%` : '';
}
</script>

<template>
    <p
        v-if="itens.length === 0"
        class="py-6 text-center text-sm text-muted-foreground"
    >
        {{ vazio }}
    </p>
    <ul v-else class="grid gap-2.5">
        <li
            v-for="item in itens"
            :key="item.chave"
            class="grid grid-cols-[minmax(0,1fr)_auto] items-center gap-x-3 gap-y-1"
            :title="`${item.rotulo}: ${formatarValor(item.total, formato)}`"
        >
            <span class="truncate text-sm">{{ item.rotulo }}</span>
            <span class="text-sm font-medium tabular-nums">
                {{ formatarValor(item.total, formato) }}
                <span
                    v-if="comPercentual"
                    class="ml-1 text-xs font-normal text-muted-foreground"
                >
                    {{ percentual(item.total) }}
                </span>
            </span>
            <span
                class="col-span-2 h-2 w-full overflow-hidden rounded-full bg-muted"
                aria-hidden="true"
            >
                <span
                    class="block h-full rounded-full"
                    :style="{
                        width: `${Math.max(1.5, (item.total * 100) / maximo)}%`,
                        background: 'var(--viz-serie)',
                    }"
                />
            </span>
        </li>
    </ul>
</template>
