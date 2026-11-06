<script setup lang="ts">
import { ChevronLeft, ChevronRight } from '@lucide/vue';
import { computed } from 'vue';
import { Button } from '@/components/ui/button';

const props = withDefaults(
    defineProps<{
        pagina: number;
        ultimaPagina: number;
        /** Quantas páginas mostrar de cada lado da atual. */
        vizinhas?: number;
        desabilitado?: boolean;
    }>(),
    { vizinhas: 1, desabilitado: false },
);

const emit = defineEmits<{ mudar: [pagina: number] }>();

type Item =
    | { tipo: 'pagina'; numero: number }
    | { tipo: 'reticencias'; chave: string };

const itens = computed<Item[]>(() => {
    const { pagina, ultimaPagina, vizinhas } = props;
    const numeros = new Set<number>([1, ultimaPagina]);

    for (let n = pagina - vizinhas; n <= pagina + vizinhas; n++) {
        if (n >= 1 && n <= ultimaPagina) {
            numeros.add(n);
        }
    }

    const ordenados = [...numeros].sort((a, b) => a - b);
    const lista: Item[] = [];

    ordenados.forEach((numero, i) => {
        const anterior = ordenados[i - 1];

        if (anterior !== undefined && numero - anterior > 1) {
            lista.push({ tipo: 'reticencias', chave: `r${numero}` });
        }

        lista.push({ tipo: 'pagina', numero });
    });

    return lista;
});

function ir(numero: number) {
    if (
        !props.desabilitado &&
        numero >= 1 &&
        numero <= props.ultimaPagina &&
        numero !== props.pagina
    ) {
        emit('mudar', numero);
    }
}
</script>

<template>
    <nav
        v-if="ultimaPagina > 1"
        aria-label="Paginação"
        class="flex flex-wrap items-center justify-center gap-1"
    >
        <Button
            variant="outline"
            size="sm"
            :disabled="desabilitado || pagina <= 1"
            aria-label="Página anterior"
            data-testid="anterior"
            @click="ir(pagina - 1)"
        >
            <ChevronLeft class="size-4" />
            <span class="hidden sm:inline">Anterior</span>
        </Button>

        <template
            v-for="item in itens"
            :key="item.tipo === 'pagina' ? item.numero : item.chave"
        >
            <span
                v-if="item.tipo === 'reticencias'"
                class="px-2 text-muted-foreground"
                aria-hidden="true"
                >…</span
            >
            <Button
                v-else
                :variant="item.numero === pagina ? 'default' : 'ghost'"
                size="sm"
                class="min-w-9"
                :disabled="desabilitado"
                :aria-current="item.numero === pagina ? 'page' : undefined"
                :aria-label="`Página ${item.numero}`"
                @click="ir(item.numero)"
            >
                {{ item.numero }}
            </Button>
        </template>

        <Button
            variant="outline"
            size="sm"
            :disabled="desabilitado || pagina >= ultimaPagina"
            aria-label="Próxima página"
            data-testid="proxima"
            @click="ir(pagina + 1)"
        >
            <span class="hidden sm:inline">Próxima</span>
            <ChevronRight class="size-4" />
        </Button>
    </nav>
</template>
