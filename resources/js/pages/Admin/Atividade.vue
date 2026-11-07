<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { X } from '@lucide/vue';
import { ref } from 'vue';
import AdminNav from '@/components/admin/AdminNav.vue';
import FiltroPeriodo from '@/components/admin/FiltroPeriodo.vue';
import EstadoVazio from '@/components/EstadoVazio.vue';
import Paginacao from '@/components/Paginacao.vue';
import { queryPeriodo, visitarAdmin } from '@/lib/admin';
import { classeSelect } from '@/lib/admin-estilos';
import { formatarDataHora, formatarValor } from '@/lib/formatar';
import type { AtividadeProps } from '@/types/admin';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Administração', href: '/admin' },
            { title: 'Atividade', href: '/admin/atividade' },
        ],
    },
});

const props = defineProps<AtividadeProps>();

const carregando = ref(false);

function visitar(mudancas: Record<string, string | number | boolean | null>) {
    visitarAdmin(
        'admin.atividade',
        {
            ...queryPeriodo(props.periodo),
            tipo: props.filtros.tipo,
            usuario: props.filtros.usuario?.id ?? null,
            robos: props.filtros.robos,
            ...mudancas,
        },
        (c) => (carregando.value = c),
    );
}

function detalhes(dados: Record<string, unknown>): string {
    return Object.entries(dados)
        .map(
            ([k, v]) =>
                `${k.replaceAll('_', ' ')}: ${Array.isArray(v) ? v.join(', ') : typeof v === 'boolean' ? (v ? 'sim' : 'não') : String(v)}`,
        )
        .join(' · ');
}
</script>

<template>
    <Head title="Atividade · Administração" />

    <div
        class="mx-auto flex w-full max-w-7xl flex-col gap-5 p-4 md:p-6"
        :class="carregando && 'opacity-60 transition-opacity'"
    >
        <header>
            <h1 class="text-2xl font-semibold tracking-tight">Atividade</h1>
            <p class="text-sm text-muted-foreground">
                Páginas vistas, login e cadastro, alvos, alertas, notificações e
                exportações.
            </p>
        </header>

        <AdminNav :query="queryPeriodo(periodo)" />

        <div class="flex flex-wrap items-center gap-3">
            <FiltroPeriodo
                :periodo="periodo"
                :presets="presets"
                @mudar="
                    (q) =>
                        visitarAdmin('admin.atividade', {
                            tipo: filtros.tipo,
                            usuario: filtros.usuario?.id,
                            robos: filtros.robos,
                            ...q,
                        })
                "
            />
            <label class="sr-only" for="f-tipo">Tipo de evento</label>
            <select
                id="f-tipo"
                :class="classeSelect"
                :value="filtros.tipo ?? ''"
                @change="
                    visitar({
                        tipo:
                            ($event.target as HTMLSelectElement).value || null,
                        pagina: null,
                    })
                "
            >
                <option value="">Todos os eventos</option>
                <option
                    v-for="(rotulo, chave) in tipos"
                    :key="chave"
                    :value="chave"
                >
                    {{ rotulo }}
                </option>
            </select>
            <label
                class="flex items-center gap-2 text-sm text-muted-foreground"
            >
                <input
                    type="checkbox"
                    class="size-4"
                    :checked="filtros.robos"
                    @change="
                        visitar({
                            robos: ($event.target as HTMLInputElement).checked,
                        })
                    "
                />
                Incluir robôs
            </label>
            <span
                v-if="filtros.usuario"
                class="inline-flex items-center gap-1.5 rounded-full border bg-card py-1 pr-1 pl-3 text-sm"
            >
                {{ filtros.usuario.nome }}
                <span class="text-muted-foreground"
                    >({{ filtros.usuario.email }})</span
                >
                <button
                    type="button"
                    class="rounded-full p-0.5 hover:bg-accent"
                    aria-label="Remover filtro de usuário"
                    @click="visitar({ usuario: null })"
                >
                    <X class="size-3.5" />
                </button>
            </span>
        </div>

        <div
            v-if="porTipo.length"
            class="flex flex-wrap gap-2"
            aria-label="Eventos por tipo no período"
        >
            <button
                v-for="t in porTipo"
                :key="t.chave"
                type="button"
                class="inline-flex items-center gap-1.5 rounded-full border px-3 py-1 text-xs"
                :class="
                    filtros.tipo === t.chave
                        ? 'border-foreground bg-accent'
                        : 'bg-card hover:bg-accent'
                "
                :aria-pressed="filtros.tipo === t.chave"
                @click="
                    visitar({
                        tipo: filtros.tipo === t.chave ? null : t.chave,
                        pagina: null,
                    })
                "
            >
                {{ t.rotulo }}
                <span class="font-semibold tabular-nums">{{
                    formatarValor(t.total)
                }}</span>
            </button>
        </div>

        <EstadoVazio
            v-if="eventos.data.length === 0"
            titulo="Nenhum evento"
            descricao="Nada registrado com esses filtros no período."
        />

        <div v-else class="overflow-x-auto rounded-xl border bg-card">
            <table class="w-full min-w-[52rem] text-sm">
                <thead class="border-b text-left text-xs text-muted-foreground">
                    <tr>
                        <th class="px-4 py-2.5 font-medium">Quando</th>
                        <th class="px-4 py-2.5 font-medium">Evento</th>
                        <th class="px-4 py-2.5 font-medium">Quem</th>
                        <th class="px-4 py-2.5 font-medium">Origem</th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="e in eventos.data"
                        :key="e.id"
                        class="border-b align-top last:border-0"
                    >
                        <td class="px-4 py-2.5 whitespace-nowrap tabular-nums">
                            {{ formatarDataHora(e.quando) }}
                        </td>
                        <td class="px-4 py-2.5">
                            <div class="font-medium">{{ e.rotulo }}</div>
                            <div
                                v-if="e.caminho"
                                class="font-mono text-xs text-muted-foreground"
                            >
                                {{ e.caminho }}
                            </div>
                            <div
                                v-if="Object.keys(e.dados).length"
                                class="text-xs text-muted-foreground"
                            >
                                {{ detalhes(e.dados) }}
                            </div>
                        </td>
                        <td class="px-4 py-2.5">
                            <template v-if="e.usuario">
                                <Link
                                    :href="
                                        route('admin.atividade', {
                                            ...queryPeriodo(periodo),
                                            usuario: e.usuario.id,
                                        })
                                    "
                                    class="font-medium hover:underline"
                                >
                                    {{ e.usuario.nome }}
                                </Link>
                                <div class="text-xs text-muted-foreground">
                                    {{ e.usuario.email }}
                                </div>
                            </template>
                            <template v-else-if="e.visitante">
                                <span class="text-muted-foreground"
                                    >Visitante</span
                                >
                                <div
                                    class="font-mono text-xs text-muted-foreground"
                                >
                                    {{ e.visitante }}
                                </div>
                            </template>
                            <span v-else class="text-muted-foreground"
                                >Sistema</span
                            >
                        </td>
                        <td class="px-4 py-2.5 text-xs text-muted-foreground">
                            <template v-if="e.dispositivo">
                                <div class="text-foreground">
                                    {{
                                        [e.navegador, e.sistema]
                                            .filter(Boolean)
                                            .join(' · ') || e.dispositivo
                                    }}
                                </div>
                                <div>
                                    {{ e.ip ?? '—'
                                    }}<template v-if="e.referencia">
                                        · via {{ e.referencia }}</template
                                    >
                                </div>
                            </template>
                            <span v-else>Fila / agendador</span>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <Paginacao
            v-if="eventos.meta.ultimaPagina > 1"
            :pagina="eventos.meta.pagina"
            :ultima-pagina="eventos.meta.ultimaPagina"
            :desabilitado="carregando"
            @mudar="(p) => visitar({ pagina: p })"
        />
    </div>
</template>
