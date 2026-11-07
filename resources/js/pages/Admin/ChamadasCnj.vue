<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { ref } from 'vue';
import AdminNav from '@/components/admin/AdminNav.vue';
import CartaoGrafico from '@/components/admin/CartaoGrafico.vue';
import FiltroPeriodo from '@/components/admin/FiltroPeriodo.vue';
import ListaBarras from '@/components/admin/ListaBarras.vue';
import EstadoVazio from '@/components/EstadoVazio.vue';
import Paginacao from '@/components/Paginacao.vue';
import { Badge } from '@/components/ui/badge';
import { queryPeriodo, visitarAdmin } from '@/lib/admin';
import { classeSelect } from '@/lib/admin-estilos';
import { formatarDataHora, formatarValor } from '@/lib/formatar';
import type { ChamadasCnjProps } from '@/types/admin';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Administração', href: '/admin' },
            { title: 'API do CNJ', href: '/admin/cnj' },
        ],
    },
});

const props = defineProps<ChamadasCnjProps>();

const carregando = ref(false);

function visitar(mudancas: Record<string, string | number | null>) {
    visitarAdmin(
        'admin.cnj',
        { ...queryPeriodo(props.periodo), ...props.filtros, ...mudancas },
        (c) => (carregando.value = c),
    );
}

function parametros(p: Record<string, string | number>): string {
    return Object.entries(p)
        .map(([k, v]) => `${k}=${v}`)
        .join(' · ');
}
</script>

<template>
    <Head title="API do CNJ · Administração" />

    <div
        class="mx-auto flex w-full max-w-7xl flex-col gap-5 p-4 md:p-6"
        :class="carregando && 'opacity-60 transition-opacity'"
    >
        <header>
            <h1 class="text-2xl font-semibold tracking-tight">API do CNJ</h1>
            <p class="text-sm text-muted-foreground">
                Cada requisição real à Comunica API: quem chamou, quanto
                demorou, o que voltou e quanto sobrou do limite.
            </p>
        </header>

        <AdminNav :query="queryPeriodo(periodo)" />

        <div class="flex flex-wrap items-center gap-3">
            <FiltroPeriodo
                :periodo="periodo"
                :presets="presets"
                @mudar="(q) => visitarAdmin('admin.cnj', { ...filtros, ...q })"
            />
            <label class="sr-only" for="f-resultado">Resultado</label>
            <select
                id="f-resultado"
                :class="classeSelect"
                :value="filtros.resultado ?? ''"
                @change="
                    visitar({
                        resultado:
                            ($event.target as HTMLSelectElement).value || null,
                        pagina: null,
                    })
                "
            >
                <option value="">Todos os resultados</option>
                <option
                    v-for="(rotulo, chave) in resultados"
                    :key="chave"
                    :value="chave"
                >
                    {{ rotulo }}
                </option>
            </select>
            <label class="sr-only" for="f-origem">Origem</label>
            <select
                id="f-origem"
                :class="classeSelect"
                :value="filtros.origem ?? ''"
                @change="
                    visitar({
                        origem:
                            ($event.target as HTMLSelectElement).value || null,
                        pagina: null,
                    })
                "
            >
                <option value="">Todas as origens</option>
                <option
                    v-for="(rotulo, chave) in origens"
                    :key="chave"
                    :value="chave"
                >
                    {{ rotulo }}
                </option>
            </select>
        </div>

        <div class="grid gap-4 lg:grid-cols-3">
            <dl class="grid grid-cols-2 gap-3 lg:col-span-1">
                <div
                    v-for="(item, i) in [
                        ['Chamadas', formatarValor(resumo.total)],
                        [
                            'Latência média',
                            formatarValor(resumo.latenciaMediaMs, 'ms'),
                        ],
                        [
                            'Latência p95',
                            formatarValor(resumo.latenciaP95Ms, 'ms'),
                        ],
                        [
                            'Limite restante',
                            formatarValor(resumo.ratelimitRestante),
                        ],
                    ]"
                    :key="i"
                    class="rounded-xl border bg-card p-4 shadow-xs"
                >
                    <dt class="text-xs font-medium text-muted-foreground">
                        {{ item[0] }}
                    </dt>
                    <dd class="mt-1.5 text-xl font-semibold tabular-nums">
                        {{ item[1] }}
                    </dd>
                </div>
            </dl>
            <CartaoGrafico titulo="Por resultado">
                <ListaBarras :itens="resumo.porResultado" />
            </CartaoGrafico>
            <CartaoGrafico titulo="Por origem">
                <ListaBarras :itens="resumo.porOrigem" />
            </CartaoGrafico>
        </div>

        <EstadoVazio
            v-if="chamadas.data.length === 0"
            titulo="Nenhuma chamada"
            descricao="Nenhuma requisição ao CNJ com esses filtros no período."
        />

        <div v-else class="overflow-x-auto rounded-xl border bg-card">
            <table class="w-full min-w-[56rem] text-sm">
                <thead class="border-b text-left text-xs text-muted-foreground">
                    <tr>
                        <th class="px-4 py-2.5 font-medium">Quando</th>
                        <th class="px-4 py-2.5 font-medium">Origem</th>
                        <th class="px-4 py-2.5 font-medium">Requisição</th>
                        <th class="px-4 py-2.5 font-medium">Resultado</th>
                        <th class="px-4 py-2.5 text-right font-medium">
                            Tempo
                        </th>
                        <th class="px-4 py-2.5 text-right font-medium">
                            Itens
                        </th>
                        <th class="px-4 py-2.5 text-right font-medium">
                            Limite
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="c in chamadas.data"
                        :key="c.id"
                        class="border-b align-top last:border-0"
                    >
                        <td class="px-4 py-2.5 whitespace-nowrap tabular-nums">
                            {{ formatarDataHora(c.quando) }}
                        </td>
                        <td class="px-4 py-2.5">
                            {{ origens[c.origem] ?? c.origem }}
                        </td>
                        <td class="px-4 py-2.5">
                            <div class="font-mono text-xs">
                                GET /{{ c.endpoint }}
                            </div>
                            <div
                                class="font-mono text-xs break-all text-muted-foreground"
                            >
                                {{ parametros(c.parametros) }}
                            </div>
                            <div
                                v-if="c.erro"
                                class="mt-1 text-xs text-destructive"
                            >
                                {{ c.erro }}
                            </div>
                        </td>
                        <td class="px-4 py-2.5 whitespace-nowrap">
                            <Badge
                                :variant="
                                    c.resultado === 'sucesso'
                                        ? 'default'
                                        : 'destructive'
                                "
                                >{{
                                    resultados[c.resultado] ?? c.resultado
                                }}</Badge
                            >
                            <div
                                v-if="c.status"
                                class="mt-1 text-xs text-muted-foreground tabular-nums"
                            >
                                HTTP {{ c.status }}
                            </div>
                        </td>
                        <td class="px-4 py-2.5 text-right tabular-nums">
                            {{ formatarValor(c.duracaoMs, 'ms') }}
                        </td>
                        <td class="px-4 py-2.5 text-right tabular-nums">
                            {{ formatarValor(c.itens) }}
                        </td>
                        <td class="px-4 py-2.5 text-right tabular-nums">
                            {{ formatarValor(c.ratelimitRestante) }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <Paginacao
            v-if="chamadas.meta.ultimaPagina > 1"
            :pagina="chamadas.meta.pagina"
            :ultima-pagina="chamadas.meta.ultimaPagina"
            :desabilitado="carregando"
            @mudar="(p) => visitar({ pagina: p })"
        />
    </div>
</template>
