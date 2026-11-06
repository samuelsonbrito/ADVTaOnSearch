<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import { Button } from '@/components/ui/button';

const props = defineProps<{
    status: number;
    /** Retry-After do 429, quando houver. */
    tenteEmSegundos: number | null;
}>();

const textos: Record<number, { titulo: string; descricao: string }> = {
    403: {
        titulo: 'Acesso negado',
        descricao: 'Você não tem permissão para ver esta página.',
    },
    404: {
        titulo: 'Página não encontrada',
        descricao: 'O endereço pode estar errado ou o conteúdo foi removido.',
    },
    429: {
        titulo: 'Muitas consultas em pouco tempo',
        descricao:
            'Para não sobrecarregar o serviço do CNJ, limitamos o número de consultas por minuto.',
    },
    500: {
        titulo: 'Algo deu errado',
        descricao:
            'Tivemos um problema inesperado. Tente novamente em instantes.',
    },
    503: {
        titulo: 'Em manutenção',
        descricao: 'Voltamos em breve.',
    },
};

const texto = computed(() => textos[props.status] ?? textos[500]!);

const espera = computed(() => {
    if (props.status !== 429) {
        return null;
    }

    const s = props.tenteEmSegundos ?? 60;

    return s >= 60
        ? `Tente de novo em ${Math.ceil(s / 60)} minuto${s >= 120 ? 's' : ''}.`
        : `Tente de novo em ${s} segundos.`;
});
</script>

<template>
    <Head :title="texto.titulo" />

    <div
        class="mx-auto flex max-w-lg flex-col items-center py-16 text-center"
        data-testid="pagina-erro"
    >
        <p class="font-mono text-sm text-muted-foreground">Erro {{ status }}</p>
        <h1 class="mt-2 text-2xl font-semibold tracking-tight">
            {{ texto.titulo }}
        </h1>
        <p class="mt-2 text-muted-foreground">{{ texto.descricao }}</p>
        <p v-if="espera" class="mt-1 font-medium">{{ espera }}</p>
        <Button as-child class="mt-6">
            <Link :href="route('consulta.index')">Ir para a Consulta</Link>
        </Button>
    </div>
</template>
