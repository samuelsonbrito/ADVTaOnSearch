<script setup lang="ts">
import { CalendarDays, FileText, Landmark, Scale, Users } from '@lucide/vue';
import { computed } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { formatarData } from '@/lib/cnj';
import type { Comunicacao } from '@/types/djen';

const props = defineProps<{ comunicacao: Comunicacao }>();

defineEmits<{ verInteiroTeor: [comunicacao: Comunicacao] }>();

const advogados = computed(() =>
    props.comunicacao.advogados.map((a) =>
        a.numeroOab
            ? `${a.nome} (OAB ${a.numeroOab}${a.ufOab ? `/${a.ufOab}` : ''})`
            : a.nome,
    ),
);
</script>

<template>
    <article
        class="rounded-xl border bg-card p-5 text-card-foreground shadow-xs transition-shadow hover:shadow-md"
        :aria-labelledby="`processo-${comunicacao.hash}`"
    >
        <header class="flex flex-wrap items-center gap-2">
            <Badge data-testid="tribunal">{{ comunicacao.tribunal }}</Badge>
            <Badge v-if="comunicacao.tipoComunicacao" variant="secondary">
                {{ comunicacao.tipoComunicacao }}
            </Badge>
            <Badge v-if="comunicacao.tipoDocumento" variant="outline">
                {{ comunicacao.tipoDocumento }}
            </Badge>
            <span
                class="ml-auto inline-flex items-center gap-1 text-xs text-muted-foreground"
            >
                <CalendarDays class="size-3.5" aria-hidden="true" />
                <span class="sr-only">Disponibilizada em</span>
                <time :datetime="comunicacao.dataDisponibilizacao">{{
                    formatarData(comunicacao.dataDisponibilizacao)
                }}</time>
            </span>
        </header>

        <h3
            :id="`processo-${comunicacao.hash}`"
            class="mt-3 font-mono text-base font-semibold tracking-tight"
        >
            {{ comunicacao.numeroProcesso }}
        </h3>

        <dl class="mt-3 grid gap-2 text-sm">
            <div v-if="comunicacao.orgaoJulgador" class="flex gap-2">
                <dt class="shrink-0 text-muted-foreground">
                    <Landmark class="size-4" aria-hidden="true" />
                    <span class="sr-only">Órgão julgador</span>
                </dt>
                <dd>{{ comunicacao.orgaoJulgador }}</dd>
            </div>
            <div v-if="comunicacao.destinatarios.length" class="flex gap-2">
                <dt class="shrink-0 text-muted-foreground">
                    <Users class="size-4" aria-hidden="true" />
                    <span class="sr-only">Destinatários</span>
                </dt>
                <dd data-testid="destinatarios">
                    {{
                        comunicacao.destinatarios.map((d) => d.nome).join(' · ')
                    }}
                </dd>
            </div>
            <div v-if="advogados.length" class="flex gap-2">
                <dt class="shrink-0 text-muted-foreground">
                    <Scale class="size-4" aria-hidden="true" />
                    <span class="sr-only">Advogados</span>
                </dt>
                <dd data-testid="advogados">{{ advogados.join(' · ') }}</dd>
            </div>
        </dl>

        <p
            class="mt-3 line-clamp-3 text-sm leading-relaxed text-muted-foreground"
            data-testid="trecho"
        >
            {{ comunicacao.trecho }}
        </p>

        <footer class="mt-4 flex items-center justify-between gap-2">
            <slot name="acoes" />
            <Button
                variant="outline"
                size="sm"
                class="ml-auto"
                data-testid="ver-inteiro-teor"
                @click="$emit('verInteiroTeor', comunicacao)"
            >
                <FileText class="size-4" aria-hidden="true" />
                Ver inteiro teor
            </Button>
        </footer>
    </article>
</template>
