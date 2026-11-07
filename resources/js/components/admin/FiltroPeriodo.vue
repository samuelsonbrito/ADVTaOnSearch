<script setup lang="ts">
import { CalendarRange } from '@lucide/vue';
import { ref, watch } from 'vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import type { Periodo } from '@/types/admin';

/** Período dos relatórios: atalhos primeiro, intervalo personalizado depois. */
const props = defineProps<{
    periodo: Periodo;
    presets: Record<string, string>;
}>();

const emit = defineEmits<{ mudar: [query: Record<string, string>] }>();

const personalizado = ref(props.periodo.chave === 'personalizado');
const inicio = ref(props.periodo.inicio);
const fim = ref(props.periodo.fim);

watch(
    () => props.periodo,
    (p) => {
        inicio.value = p.inicio;
        fim.value = p.fim;
        personalizado.value = p.chave === 'personalizado';
    },
);

function aplicar() {
    if (inicio.value && fim.value) {
        emit('mudar', {
            periodo: 'personalizado',
            inicio: inicio.value,
            fim: fim.value,
        });
    }
}
</script>

<template>
    <div class="flex flex-wrap items-center gap-2">
        <div
            class="inline-flex rounded-lg border bg-card p-0.5"
            role="group"
            aria-label="Período"
        >
            <button
                v-for="(rotulo, chave) in presets"
                :key="chave"
                type="button"
                class="shrink-0 rounded-md px-3 py-1.5 text-sm whitespace-nowrap"
                :class="
                    !personalizado && periodo.chave === chave
                        ? 'bg-primary text-primary-foreground'
                        : 'text-muted-foreground hover:bg-accent hover:text-foreground'
                "
                :aria-pressed="!personalizado && periodo.chave === chave"
                @click="
                    personalizado = false;
                    emit('mudar', { periodo: String(chave) });
                "
            >
                {{ rotulo }}
            </button>
            <button
                type="button"
                class="inline-flex shrink-0 items-center gap-1.5 rounded-md px-3 py-1.5 text-sm whitespace-nowrap"
                :class="
                    personalizado
                        ? 'bg-primary text-primary-foreground'
                        : 'text-muted-foreground hover:bg-accent hover:text-foreground'
                "
                :aria-pressed="personalizado"
                @click="personalizado = true"
            >
                <CalendarRange class="size-4" aria-hidden="true" />
                Personalizado
            </button>
        </div>

        <form
            v-if="personalizado"
            class="flex flex-wrap items-center gap-2"
            @submit.prevent="aplicar"
        >
            <label class="sr-only" for="periodo-inicio">De</label>
            <Input
                id="periodo-inicio"
                v-model="inicio"
                type="date"
                class="h-9 w-auto"
                required
            />
            <span class="text-sm text-muted-foreground">até</span>
            <label class="sr-only" for="periodo-fim">Até</label>
            <Input
                id="periodo-fim"
                v-model="fim"
                type="date"
                class="h-9 w-auto"
                :min="inicio"
                required
            />
            <Button type="submit" size="sm">Aplicar</Button>
        </form>
    </div>
</template>
