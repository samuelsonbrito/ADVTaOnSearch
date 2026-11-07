<script setup lang="ts">
import { ArrowDownRight, ArrowUpRight, Minus } from '@lucide/vue';
import { computed } from 'vue';
import type { Formato } from '@/lib/formatar';
import { formatarValor, variacao } from '@/lib/formatar';
import type { Indicador } from '@/types/admin';

/** Número em destaque + variação contra o período anterior (texto neutro). */
const props = withDefaults(
    defineProps<{
        rotulo: string;
        indicador: Indicador;
        formato?: Formato;
        detalhe?: string;
    }>(),
    { formato: 'numero' },
);

const delta = computed(() =>
    variacao(props.indicador.valor, props.indicador.anterior, props.formato),
);

const icone = computed(() =>
    delta.value?.direcao === 'sobe'
        ? ArrowUpRight
        : delta.value?.direcao === 'desce'
          ? ArrowDownRight
          : Minus,
);
</script>

<template>
    <div class="rounded-xl border bg-card p-4 shadow-xs">
        <p class="text-xs font-medium text-muted-foreground">{{ rotulo }}</p>
        <p class="mt-1.5 text-2xl font-semibold tracking-tight tabular-nums">
            {{ formatarValor(indicador.valor, formato) }}
        </p>
        <p
            class="mt-1 flex items-center gap-1 truncate text-xs whitespace-nowrap text-muted-foreground"
            :title="
                delta
                    ? `${delta.texto} em relação ao período anterior`
                    : undefined
            "
        >
            <template v-if="delta">
                <component
                    :is="icone"
                    class="size-3.5 shrink-0"
                    aria-hidden="true"
                />
                <span class="tabular-nums">{{ delta.texto }}</span>
                <span>vs. anterior</span>
            </template>
            <span v-else-if="detalhe">{{ detalhe }}</span>
            <span v-else>&nbsp;</span>
        </p>
    </div>
</template>
