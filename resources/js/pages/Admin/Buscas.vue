<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Bot, Database, Search } from '@lucide/vue';
import { ref } from 'vue';
import AdminNav from '@/components/admin/AdminNav.vue';
import FiltroPeriodo from '@/components/admin/FiltroPeriodo.vue';
import EstadoVazio from '@/components/EstadoVazio.vue';
import Paginacao from '@/components/Paginacao.vue';
import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';
import { queryPeriodo, visitarAdmin } from '@/lib/admin';
import { classeSelect } from '@/lib/admin-estilos';
import { formatarDataHora, formatarValor } from '@/lib/formatar';
import type { BuscaRegistrada, BuscasProps } from '@/types/admin';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Administração', href: '/admin' },
            { title: 'Buscas', href: '/admin/buscas' },
        ],
    },
});

const props = defineProps<BuscasProps>();

const carregando = ref(false);
const termo = ref(props.filtros.termo ?? '');

function visitar(mudancas: Record<string, string | number | boolean | null>) {
    visitarAdmin(
        'admin.buscas',
        {
            ...queryPeriodo(props.periodo),
            ...props.filtros,
            termo: termo.value,
            ...mudancas,
        },
        (c) => (carregando.value = c),
    );
}

const corStatus: Record<
    BuscaRegistrada['status'],
    'default' | 'secondary' | 'destructive' | 'outline'
> = {
    ok: 'default',
    vazio: 'secondary',
    invalido: 'outline',
    erro: 'destructive',
};
</script>

<template>
    <Head title="Buscas · Administração" />

    <div
        class="mx-auto flex w-full max-w-7xl flex-col gap-5 p-4 md:p-6"
        :class="carregando && 'opacity-60 transition-opacity'"
    >
        <header>
            <h1 class="text-2xl font-semibold tracking-tight">Buscas</h1>
            <p class="text-sm text-muted-foreground">
                Cada busca feita na Consulta, com filtros, resultado, tempo e
                origem.
            </p>
        </header>

        <AdminNav :query="queryPeriodo(periodo)" />

        <div class="flex flex-col gap-3">
            <FiltroPeriodo
                :periodo="periodo"
                :presets="presets"
                @mudar="
                    (q) => visitarAdmin('admin.buscas', { ...filtros, ...q })
                "
            />
            <form
                class="flex flex-wrap items-center gap-2"
                role="search"
                @submit.prevent="visitar({ pagina: null })"
            >
                <label class="sr-only" for="f-criterio">Critério</label>
                <select
                    id="f-criterio"
                    :class="classeSelect"
                    :value="filtros.criterio ?? ''"
                    @change="
                        visitar({
                            criterio:
                                ($event.target as HTMLSelectElement).value ||
                                null,
                        })
                    "
                >
                    <option value="">Todos os critérios</option>
                    <option
                        v-for="(rotulo, chave) in criterios"
                        :key="chave"
                        :value="chave"
                    >
                        {{ rotulo }}
                    </option>
                </select>
                <label class="sr-only" for="f-status">Resultado</label>
                <select
                    id="f-status"
                    :class="classeSelect"
                    :value="filtros.status ?? ''"
                    @change="
                        visitar({
                            status:
                                ($event.target as HTMLSelectElement).value ||
                                null,
                        })
                    "
                >
                    <option value="">Todos os resultados</option>
                    <option
                        v-for="(rotulo, chave) in status"
                        :key="chave"
                        :value="chave"
                    >
                        {{ rotulo }}
                    </option>
                </select>
                <label class="sr-only" for="f-quem">Quem</label>
                <select
                    id="f-quem"
                    :class="classeSelect"
                    :value="filtros.quem ?? ''"
                    @change="
                        visitar({
                            quem:
                                ($event.target as HTMLSelectElement).value ||
                                null,
                        })
                    "
                >
                    <option value="">Todos</option>
                    <option value="usuarios">Só usuários logados</option>
                    <option value="visitantes">Só visitantes</option>
                </select>
                <div class="relative">
                    <Search
                        class="pointer-events-none absolute top-2.5 left-2.5 size-4 text-muted-foreground"
                        aria-hidden="true"
                    />
                    <label class="sr-only" for="f-termo">Termo</label>
                    <Input
                        id="f-termo"
                        v-model="termo"
                        class="h-9 w-56 pl-8"
                        placeholder="Nome, OAB, processo, tribunal"
                    />
                </div>
                <label
                    class="flex items-center gap-2 text-sm text-muted-foreground"
                >
                    <input
                        type="checkbox"
                        class="size-4"
                        :checked="filtros.robos"
                        @change="
                            visitar({
                                robos: ($event.target as HTMLInputElement)
                                    .checked,
                            })
                        "
                    />
                    Incluir robôs
                </label>
                <Link
                    v-if="filtros.usuario"
                    :href="route('admin.buscas', queryPeriodo(periodo))"
                    class="text-sm underline underline-offset-4"
                >
                    Limpar filtro de usuário
                </Link>
            </form>
        </div>

        <p class="text-sm text-muted-foreground">
            {{ formatarValor(buscas.meta.total) }} busca(s)
        </p>

        <EstadoVazio
            v-if="buscas.data.length === 0"
            titulo="Nenhuma busca"
            descricao="Nada encontrado com esses filtros no período."
        />

        <div v-else class="overflow-x-auto rounded-xl border bg-card">
            <table class="w-full min-w-[56rem] text-sm">
                <thead class="border-b text-left text-xs text-muted-foreground">
                    <tr>
                        <th class="px-4 py-2.5 font-medium">Quando</th>
                        <th class="px-4 py-2.5 font-medium">Filtros</th>
                        <th class="px-4 py-2.5 font-medium">Resultado</th>
                        <th class="px-4 py-2.5 text-right font-medium">
                            Tempo
                        </th>
                        <th class="px-4 py-2.5 font-medium">Quem</th>
                        <th class="px-4 py-2.5 font-medium">Dispositivo</th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="b in buscas.data"
                        :key="b.id"
                        class="border-b align-top last:border-0"
                    >
                        <td class="px-4 py-2.5 whitespace-nowrap tabular-nums">
                            {{ formatarDataHora(b.quando) }}
                        </td>
                        <td class="px-4 py-2.5">
                            <div class="flex flex-wrap gap-x-3 gap-y-1">
                                <span
                                    v-for="(valor, nome) in b.filtros"
                                    :key="nome"
                                >
                                    <span class="text-muted-foreground"
                                        >{{ nome }}:</span
                                    >
                                    {{ valor }}
                                </span>
                            </div>
                        </td>
                        <td class="px-4 py-2.5">
                            <Badge :variant="corStatus[b.status]">{{
                                status[b.status]
                            }}</Badge>
                            <div
                                v-if="b.resultados !== null"
                                class="mt-1 text-xs text-muted-foreground tabular-nums"
                            >
                                {{ formatarValor(b.resultados) }}
                                comunicação(ões)
                            </div>
                            <div
                                v-if="b.erroTipo"
                                class="mt-1 text-xs text-muted-foreground"
                            >
                                {{ b.erroTipo }}
                            </div>
                        </td>
                        <td
                            class="px-4 py-2.5 text-right whitespace-nowrap tabular-nums"
                        >
                            {{ formatarValor(b.duracaoMs, 'ms') }}
                            <div
                                v-if="b.doCache"
                                class="mt-1 inline-flex items-center gap-1 text-xs text-muted-foreground"
                            >
                                <Database class="size-3" aria-hidden="true" />
                                cache
                            </div>
                        </td>
                        <td class="px-4 py-2.5">
                            <template v-if="b.usuario">
                                <Link
                                    :href="
                                        route('admin.atividade', {
                                            ...queryPeriodo(periodo),
                                            usuario: b.usuario.id,
                                        })
                                    "
                                    class="font-medium hover:underline"
                                >
                                    {{ b.usuario.nome }}
                                </Link>
                                <div class="text-xs text-muted-foreground">
                                    {{ b.usuario.email }}
                                </div>
                            </template>
                            <template v-else>
                                <span class="text-muted-foreground"
                                    >Visitante</span
                                >
                                <div
                                    class="font-mono text-xs text-muted-foreground"
                                >
                                    {{ b.visitante }}
                                </div>
                            </template>
                        </td>
                        <td class="px-4 py-2.5 text-xs text-muted-foreground">
                            <div
                                class="flex items-center gap-1 text-foreground"
                            >
                                <Bot
                                    v-if="b.bot"
                                    class="size-3.5"
                                    aria-label="Robô"
                                />
                                {{
                                    [b.navegador, b.sistema]
                                        .filter(Boolean)
                                        .join(' · ') || b.dispositivo
                                }}
                            </div>
                            <div>
                                {{ b.ip ?? '—'
                                }}<template v-if="b.referencia">
                                    · via {{ b.referencia }}</template
                                >
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <Paginacao
            v-if="buscas.meta.ultimaPagina > 1"
            :pagina="buscas.meta.pagina"
            :ultima-pagina="buscas.meta.ultimaPagina"
            :desabilitado="carregando"
            @mudar="(p) => visitar({ pagina: p })"
        />
    </div>
</template>
