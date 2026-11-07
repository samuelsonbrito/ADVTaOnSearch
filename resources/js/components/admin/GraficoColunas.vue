<script setup lang="ts">
import { useElementSize } from '@vueuse/core';
import { computed, ref, useTemplateRef } from 'vue';
import type { Formato } from '@/lib/formatar';
import { formatarValor } from '@/lib/formatar';

export interface Ponto {
    /** Rótulo curto do eixo X (ex.: "07/11", "14h"). */
    rotulo: string;
    /** Rótulo completo no tooltip (ex.: "07/11/2026"). */
    dica: string;
    valor: number;
}

const props = withDefaults(
    defineProps<{
        pontos: Ponto[];
        /** Nome da série, para o leitor de tela e o tooltip. */
        serie: string;
        formato?: Formato;
        altura?: number;
    }>(),
    { formato: 'numero', altura: 180 },
);

const caixa = useTemplateRef<HTMLDivElement>('caixa');
const { width } = useElementSize(caixa);

const margem = { topo: 12, direita: 4, base: 22, esquerda: 40 };
const largura = computed(() => Math.max(width.value, 120));
const areaW = computed(() => largura.value - margem.esquerda - margem.direita);
const areaH = computed(() => props.altura - margem.topo - margem.base);

/** Teto "redondo" do eixo Y (1, 2, 2,5, 5 × 10ⁿ). */
const teto = computed(() => {
    const max = Math.max(0, ...props.pontos.map((p) => p.valor));

    if (max === 0) {
        return 1;
    }

    const potencia = 10 ** Math.floor(Math.log10(max));

    return (
        [1, 2, 2.5, 5, 10].map((m) => m * potencia).find((v) => v >= max) ?? max
    );
});

const linhasGrade = computed(() =>
    [0, 0.5, 1].map((f) => ({
        valor: teto.value * f,
        y: margem.topo + areaH.value * (1 - f),
    })),
);

const faixa = computed(() => areaW.value / Math.max(1, props.pontos.length));
/** Barra fina (≤ 24px) com folga de 2px entre vizinhas. */
const barraW = computed(() => Math.max(1, Math.min(24, faixa.value - 2)));

const barras = computed(() =>
    props.pontos.map((p, i) => {
        const h = (p.valor / teto.value) * areaH.value;
        const x =
            margem.esquerda +
            faixa.value * i +
            (faixa.value - barraW.value) / 2;
        const y = margem.topo + areaH.value - h;

        return { ...p, i, x, y, h };
    }),
);

/** Topo arredondado (4px), base reta sobre a linha de base. */
function caminho(x: number, y: number, w: number, h: number): string {
    if (h <= 0) {
        return '';
    }

    const r = Math.min(4, w / 2, h);
    const base = y + h;

    return `M${x},${base}V${y + r}Q${x},${y} ${x + r},${y}H${x + w - r}Q${x + w},${y} ${x + w},${y + r}V${base}Z`;
}

/** Rótulos do eixo X sem sobreposição (~1 a cada 44px). */
const passoRotulo = computed(() =>
    Math.max(1, Math.ceil(44 / Math.max(1, faixa.value))),
);

const ativo = ref<number | null>(null);
const barraAtiva = computed(() =>
    ativo.value === null ? null : (barras.value[ativo.value] ?? null),
);

function aoMover(evento: PointerEvent) {
    const svg = evento.currentTarget as SVGSVGElement;
    const x = evento.clientX - svg.getBoundingClientRect().left;
    const i = Math.floor((x - margem.esquerda) / faixa.value);

    ativo.value = i >= 0 && i < props.pontos.length ? i : null;
}

function aoTeclar(evento: KeyboardEvent) {
    const n = props.pontos.length;

    if (n === 0) {
        return;
    }

    if (evento.key === 'ArrowRight' || evento.key === 'ArrowLeft') {
        evento.preventDefault();
        const passo = evento.key === 'ArrowRight' ? 1 : -1;
        ativo.value =
            ativo.value === null
                ? passo > 0
                    ? 0
                    : n - 1
                : Math.min(n - 1, Math.max(0, ativo.value + passo));
    }
}

const dica = computed(() => {
    const b = barraAtiva.value;

    if (!b) {
        return null;
    }

    const centro = b.x + barraW.value / 2;

    return {
        b,
        esquerda: Math.min(Math.max(centro, 60), largura.value - 60),
    };
});
</script>

<template>
    <div ref="caixa" class="relative w-full">
        <svg
            :width="largura"
            :height="altura"
            :viewBox="`0 0 ${largura} ${altura}`"
            class="block touch-none overflow-visible outline-none focus-visible:rounded-md focus-visible:ring-2 focus-visible:ring-ring"
            role="img"
            :aria-label="`${serie}: gráfico de colunas com ${pontos.length} pontos. Use as setas para percorrer.`"
            tabindex="0"
            @pointermove="aoMover"
            @pointerleave="ativo = null"
            @keydown="aoTeclar"
            @blur="ativo = null"
        >
            <!-- grade recessiva -->
            <g class="text-[10px]" fill="currentColor">
                <template v-for="g in linhasGrade" :key="g.y">
                    <line
                        :x1="margem.esquerda"
                        :x2="largura - margem.direita"
                        :y1="g.y"
                        :y2="g.y"
                        class="stroke-border"
                        :stroke-dasharray="g.valor === 0 ? undefined : '2 3'"
                    />
                    <text
                        :x="margem.esquerda - 6"
                        :y="g.y + 3"
                        text-anchor="end"
                        class="fill-muted-foreground"
                    >
                        {{ formatarValor(g.valor, formato) }}
                    </text>
                </template>
            </g>

            <!-- colunas -->
            <path
                v-for="b in barras"
                :key="b.i"
                :d="caminho(b.x, b.y, barraW, b.h)"
                fill="var(--viz-serie)"
                :opacity="ativo === null || ativo === b.i ? 1 : 0.45"
            />

            <!-- marcador do ativo -->
            <rect
                v-if="barraAtiva"
                :x="barraAtiva.x - 1"
                :y="margem.topo"
                :width="barraW + 2"
                :height="areaH"
                fill="currentColor"
                class="text-foreground"
                opacity="0.06"
                rx="3"
            />

            <!-- eixo X -->
            <g class="text-[10px]">
                <template v-for="b in barras" :key="`r${b.i}`">
                    <text
                        v-if="b.i % passoRotulo === 0"
                        :x="b.x + barraW / 2"
                        :y="altura - 6"
                        text-anchor="middle"
                        class="fill-muted-foreground"
                    >
                        {{ b.rotulo }}
                    </text>
                </template>
            </g>
        </svg>

        <div
            v-if="dica"
            class="pointer-events-none absolute top-0 z-10 -translate-x-1/2 rounded-md border bg-popover px-2.5 py-1.5 text-xs shadow-md"
            :style="{ left: `${dica.esquerda}px` }"
            role="status"
        >
            <div
                class="text-sm font-semibold text-popover-foreground tabular-nums"
            >
                {{ formatarValor(dica.b.valor, formato) }}
            </div>
            <div class="flex items-center gap-1.5 text-muted-foreground">
                <span
                    class="inline-block h-0.5 w-3 rounded-full"
                    style="background: var(--viz-serie)"
                    aria-hidden="true"
                />
                {{ serie }} · {{ dica.b.dica }}
            </div>
        </div>
    </div>
</template>
