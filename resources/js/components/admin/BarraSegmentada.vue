<script setup lang="ts">
import type { Component } from 'vue';
import { computed } from 'vue';
import { formatarValor } from '@/lib/formatar';

export interface Parte {
    chave: string;
    rotulo: string;
    total: number;
    /** Variável CSS da cor de estado (ex.: var(--viz-bom)). */
    cor: string;
    icone: Component;
}

/** Um todo dividido em partes de estado: barra fina + legenda com ícone, rótulo e número. */
const props = defineProps<{ partes: Parte[] }>();

const soma = computed(() => props.partes.reduce((s, p) => s + p.total, 0));
const visiveis = computed(() => props.partes.filter((p) => p.total > 0));

function pct(total: number): string {
    return soma.value > 0 ? `${Math.round((total * 100) / soma.value)}%` : '0%';
}
</script>

<template>
    <div>
        <div
            class="flex h-2.5 w-full gap-0.5 overflow-hidden rounded-full bg-muted"
            role="img"
            :aria-label="
                partes.map((p) => `${p.rotulo}: ${p.total}`).join(', ')
            "
        >
            <span
                v-for="p in visiveis"
                :key="p.chave"
                class="h-full first:rounded-l-full last:rounded-r-full"
                :style="{ flexGrow: p.total, background: p.cor }"
                :title="`${p.rotulo}: ${formatarValor(p.total)} (${pct(p.total)})`"
            />
        </div>
        <ul class="mt-3 grid gap-y-2 text-sm">
            <li
                v-for="p in partes"
                :key="p.chave"
                class="flex items-center gap-2"
            >
                <component
                    :is="p.icone"
                    class="size-4 shrink-0"
                    :style="{ color: p.cor }"
                    aria-hidden="true"
                />
                <span class="min-w-0 flex-1 truncate text-muted-foreground">{{
                    p.rotulo
                }}</span>
                <span class="font-medium tabular-nums">{{
                    formatarValor(p.total)
                }}</span>
                <span
                    class="w-9 text-right text-xs text-muted-foreground tabular-nums"
                    >{{ pct(p.total) }}</span
                >
            </li>
        </ul>
    </div>
</template>
