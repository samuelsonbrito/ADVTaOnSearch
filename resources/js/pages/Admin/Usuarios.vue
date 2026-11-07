<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Search } from '@lucide/vue';
import { ref } from 'vue';
import AdminNav from '@/components/admin/AdminNav.vue';
import EstadoVazio from '@/components/EstadoVazio.vue';
import Paginacao from '@/components/Paginacao.vue';
import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';
import { visitarAdmin } from '@/lib/admin';
import { formatarDataHora, formatarValor } from '@/lib/formatar';
import type { UsuariosProps } from '@/types/admin';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Administração', href: '/admin' },
            { title: 'Usuários', href: '/admin/usuarios' },
        ],
    },
});

const props = defineProps<UsuariosProps>();

const carregando = ref(false);
const q = ref(props.filtros.q ?? '');

function visitar(mudancas: Record<string, string | number | null> = {}) {
    visitarAdmin(
        'admin.usuarios',
        { q: q.value, ...mudancas },
        (c) => (carregando.value = c),
    );
}
</script>

<template>
    <Head title="Usuários · Administração" />

    <div
        class="mx-auto flex w-full max-w-7xl flex-col gap-5 p-4 md:p-6"
        :class="carregando && 'opacity-60 transition-opacity'"
    >
        <header>
            <h1 class="text-2xl font-semibold tracking-tight">Usuários</h1>
            <p class="text-sm text-muted-foreground">
                Contas, uso de cada uma e último acesso. Totais de todo o
                histórico.
            </p>
        </header>

        <AdminNav />

        <dl class="grid grid-cols-2 gap-3 md:grid-cols-4">
            <div
                v-for="(item, i) in [
                    ['Contas', totais.usuarios],
                    ['E-mail confirmado', totais.verificados],
                    ['Com WhatsApp', totais.comWhatsapp],
                    ['Administradores', totais.admins],
                ]"
                :key="i"
                class="rounded-xl border bg-card p-4 shadow-xs"
            >
                <dt class="text-xs font-medium text-muted-foreground">
                    {{ item[0] }}
                </dt>
                <dd class="mt-1.5 text-2xl font-semibold tabular-nums">
                    {{ formatarValor(item[1] as number) }}
                </dd>
            </div>
        </dl>

        <form
            class="relative w-full max-w-sm"
            role="search"
            @submit.prevent="visitar({ pagina: null })"
        >
            <Search
                class="pointer-events-none absolute top-2.5 left-2.5 size-4 text-muted-foreground"
                aria-hidden="true"
            />
            <label class="sr-only" for="f-q">Buscar por nome ou e-mail</label>
            <Input
                id="f-q"
                v-model="q"
                class="h-9 pl-8"
                placeholder="Nome ou e-mail"
            />
        </form>

        <EstadoVazio
            v-if="usuarios.data.length === 0"
            titulo="Nenhuma conta encontrada"
        />

        <div v-else class="overflow-x-auto rounded-xl border bg-card">
            <table class="w-full min-w-[60rem] text-sm">
                <thead class="border-b text-left text-xs text-muted-foreground">
                    <tr>
                        <th class="px-4 py-2.5 font-medium">Conta</th>
                        <th class="px-4 py-2.5 font-medium">Cadastro</th>
                        <th class="px-4 py-2.5 font-medium">Último acesso</th>
                        <th class="px-4 py-2.5 text-right font-medium">
                            Alvos
                        </th>
                        <th class="px-4 py-2.5 text-right font-medium">
                            Alertas
                        </th>
                        <th class="px-4 py-2.5 text-right font-medium">
                            Buscas
                        </th>
                        <th class="px-4 py-2.5 text-right font-medium">
                            Exportações
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="u in usuarios.data"
                        :key="u.id"
                        class="border-b last:border-0"
                    >
                        <td class="px-4 py-2.5">
                            <div class="flex flex-wrap items-center gap-2">
                                <Link
                                    :href="
                                        route('admin.atividade', {
                                            periodo: '90d',
                                            usuario: u.id,
                                        })
                                    "
                                    class="font-medium hover:underline"
                                    >{{ u.nome }}</Link
                                >
                                <Badge v-if="u.admin">Admin</Badge>
                                <Badge v-if="!u.verificado" variant="outline"
                                    >E-mail não confirmado</Badge
                                >
                                <Badge v-if="u.whatsapp" variant="secondary"
                                    >WhatsApp</Badge
                                >
                            </div>
                            <div class="text-xs text-muted-foreground">
                                {{ u.email }}
                            </div>
                        </td>
                        <td class="px-4 py-2.5 whitespace-nowrap tabular-nums">
                            {{ formatarDataHora(u.cadastro) }}
                        </td>
                        <td class="px-4 py-2.5 whitespace-nowrap tabular-nums">
                            {{ formatarDataHora(u.ultimoAcesso) }}
                        </td>
                        <td class="px-4 py-2.5 text-right tabular-nums">
                            {{ formatarValor(u.alvos) }}
                        </td>
                        <td class="px-4 py-2.5 text-right tabular-nums">
                            {{ formatarValor(u.alertas) }}
                        </td>
                        <td class="px-4 py-2.5 text-right tabular-nums">
                            <Link
                                v-if="u.buscas"
                                :href="
                                    route('admin.buscas', {
                                        periodo: '90d',
                                        usuario: u.id,
                                    })
                                "
                                class="hover:underline"
                                >{{ formatarValor(u.buscas) }}</Link
                            >
                            <template v-else>0</template>
                        </td>
                        <td class="px-4 py-2.5 text-right tabular-nums">
                            {{ formatarValor(u.exportacoes) }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <Paginacao
            v-if="usuarios.meta.ultimaPagina > 1"
            :pagina="usuarios.meta.pagina"
            :ultima-pagina="usuarios.meta.ultimaPagina"
            :desabilitado="carregando"
            @mudar="(p) => visitar({ pagina: p })"
        />
    </div>
</template>
