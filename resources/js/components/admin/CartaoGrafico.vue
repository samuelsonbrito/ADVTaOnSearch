<script setup lang="ts">
import { Table2 } from '@lucide/vue';
import { ref } from 'vue';

/**
 * Cartão de um gráfico, com o botão "Tabela" que mostra os mesmos números
 * em texto (acessível sem hover e sem depender de cor).
 */
defineProps<{
    titulo: string;
    descricao?: string;
    /** Linhas da visão em tabela; sem elas, o botão não aparece. */
    tabela?: { cabecalhos: string[]; linhas: (string | number)[][] };
}>();

const verTabela = ref(false);
</script>

<template>
    <section class="flex flex-col rounded-xl border bg-card p-5 shadow-xs">
        <header class="mb-4 flex items-start justify-between gap-3">
            <div class="min-w-0">
                <h2 class="text-sm font-semibold">{{ titulo }}</h2>
                <p
                    v-if="descricao"
                    class="mt-0.5 text-xs text-muted-foreground"
                >
                    {{ descricao }}
                </p>
            </div>
            <button
                v-if="tabela"
                type="button"
                class="inline-flex shrink-0 items-center gap-1 rounded-md px-2 py-1 text-xs text-muted-foreground hover:bg-accent hover:text-foreground"
                :aria-pressed="verTabela"
                @click="verTabela = !verTabela"
            >
                <Table2 class="size-3.5" aria-hidden="true" />
                {{ verTabela ? 'Gráfico' : 'Tabela' }}
            </button>
        </header>

        <div v-if="tabela && verTabela" class="max-h-72 overflow-auto">
            <table class="w-full text-sm">
                <thead
                    class="sticky top-0 bg-card text-left text-xs text-muted-foreground"
                >
                    <tr>
                        <th
                            v-for="(c, i) in tabela.cabecalhos"
                            :key="c"
                            class="py-1.5 font-medium"
                            :class="i > 0 && 'text-right'"
                        >
                            {{ c }}
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="(linha, i) in tabela.linhas"
                        :key="i"
                        class="border-t"
                    >
                        <td
                            v-for="(celula, j) in linha"
                            :key="j"
                            class="py-1.5"
                            :class="j > 0 && 'text-right tabular-nums'"
                        >
                            {{ celula }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <slot v-else />
    </section>
</template>
