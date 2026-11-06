<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { Search, X } from '@lucide/vue';
import { computed, onBeforeUnmount } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    cnjCompleto,
    mascaraCnj,
    normalizarOab,
    somenteDigitos,
} from '@/lib/cnj';
import type { FiltrosConsulta, Tribunal } from '@/types/djen';

const props = withDefaults(
    defineProps<{
        filtros: FiltrosConsulta;
        tribunais: Tribunal[];
        ufs: string[];
        erros?: Partial<Record<keyof FiltrosConsulta, string>>;
        carregando?: boolean;
        /** Espera (ms) após a digitação antes de buscar. */
        debounce?: number;
    }>(),
    { erros: () => ({}), carregando: false, debounce: 700 },
);

const emit = defineEmits<{ buscar: [filtros: FiltrosConsulta] }>();

const form = useForm<Record<keyof FiltrosConsulta, string>>({
    oab: props.filtros.oab ?? '',
    uf: props.filtros.uf ?? '',
    parte: props.filtros.parte ?? '',
    advogado: props.filtros.advogado ?? '',
    processo: mascaraCnj(props.filtros.processo ?? ''),
    tribunal: props.filtros.tribunal ?? '',
    inicio: props.filtros.inicio ?? '',
    fim: props.filtros.fim ?? '',
});

const selectClass =
    'h-9 w-full rounded-md border border-input bg-transparent px-3 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 aria-invalid:border-destructive dark:bg-input/30';

function normalizado(): FiltrosConsulta {
    const valor = (v: string) => (v.trim() === '' ? null : v.trim());

    return {
        oab: valor(normalizarOab(form.oab)),
        uf: valor(form.uf),
        parte: valor(form.parte),
        advogado: valor(form.advogado),
        processo: valor(somenteDigitos(form.processo)),
        tribunal: valor(form.tribunal),
        inicio: valor(form.inicio),
        fim: valor(form.fim),
    };
}

/** Evita bater no CNJ com filtros que certamente seriam recusados. */
const prontoParaBuscaAutomatica = computed(() => {
    const f = normalizado();
    const temCriterio = Boolean(
        f.oab || f.parte || f.advogado || f.processo || f.tribunal,
    );
    const textoCurto = [f.parte, f.advogado].some(
        (v) => v !== null && v.length < 3,
    );
    const processoIncompleto = f.processo !== null && !cnjCompleto(f.processo);
    const oabSemUf = Boolean(f.oab) !== Boolean(f.uf);

    return temCriterio && !textoCurto && !processoIncompleto && !oabSemUf;
});

let timer: ReturnType<typeof setTimeout> | undefined;

function cancelarDebounce() {
    clearTimeout(timer);
    timer = undefined;
}

function buscarAgora() {
    // Uma busca imediata invalida qualquer busca com debounce pendente; sem
    // isso, o debounce disparava depois e atropelava a navegação (ex.: página 2).
    cancelarDebounce();
    emit('buscar', normalizado());
}

/** Os filtros do formulário já são os da URL atual? */
function igualAosFiltrosAtuais(): boolean {
    const atual = normalizado();

    return (Object.keys(atual) as (keyof FiltrosConsulta)[]).every(
        (chave) => (atual[chave] ?? null) === (props.filtros[chave] ?? null),
    );
}

function buscarComDebounce() {
    cancelarDebounce();
    timer = setTimeout(() => {
        timer = undefined;

        if (prontoParaBuscaAutomatica.value && !igualAosFiltrosAtuais()) {
            buscarAgora();
        }
    }, props.debounce);
}

onBeforeUnmount(cancelarDebounce);

function aoDigitarProcesso(valor: string | number) {
    form.processo = mascaraCnj(String(valor));
    buscarComDebounce();
}

function aoMudarSelecaoOuData() {
    if (prontoParaBuscaAutomatica.value) {
        buscarAgora();
    }
}

function limpar() {
    form.oab = '';
    form.uf = '';
    form.parte = '';
    form.advogado = '';
    form.processo = '';
    form.tribunal = '';
    form.inicio = '';
    form.fim = '';
    buscarAgora();
}

defineExpose({ form });
</script>

<template>
    <form
        class="grid gap-4 rounded-xl border bg-card p-5 shadow-xs"
        role="search"
        aria-label="Filtros da consulta"
        @submit.prevent="buscarAgora"
    >
        <div class="grid gap-4 md:grid-cols-[1fr_6rem_1fr]">
            <div class="grid gap-1.5">
                <Label for="filtro-oab">Número da OAB</Label>
                <Input
                    id="filtro-oab"
                    v-model="form.oab"
                    name="oab"
                    autocapitalize="characters"
                    autocomplete="off"
                    placeholder="Ex.: 12345 ou 12345-A"
                    :aria-invalid="Boolean(erros.oab)"
                    @update:model-value="buscarComDebounce"
                />
                <InputError :message="erros.oab" />
            </div>
            <div class="grid gap-1.5">
                <Label for="filtro-uf">UF</Label>
                <select
                    id="filtro-uf"
                    v-model="form.uf"
                    name="uf"
                    :class="selectClass"
                    :aria-invalid="Boolean(erros.uf)"
                    @change="aoMudarSelecaoOuData"
                >
                    <option value="">—</option>
                    <option v-for="uf in ufs" :key="uf" :value="uf">
                        {{ uf }}
                    </option>
                </select>
                <InputError :message="erros.uf" />
            </div>
            <div class="grid gap-1.5">
                <Label for="filtro-processo">Número do processo</Label>
                <Input
                    id="filtro-processo"
                    :model-value="form.processo"
                    name="processo"
                    inputmode="numeric"
                    autocomplete="off"
                    placeholder="0000000-00.0000.0.00.0000"
                    class="font-mono"
                    :aria-invalid="Boolean(erros.processo)"
                    @update:model-value="aoDigitarProcesso"
                />
                <InputError :message="erros.processo" />
            </div>
        </div>

        <div class="grid gap-4 md:grid-cols-2">
            <div class="grid gap-1.5">
                <Label for="filtro-parte">Nome da parte</Label>
                <Input
                    id="filtro-parte"
                    v-model="form.parte"
                    name="parte"
                    autocomplete="off"
                    :aria-invalid="Boolean(erros.parte)"
                    @update:model-value="buscarComDebounce"
                />
                <InputError :message="erros.parte" />
            </div>
            <div class="grid gap-1.5">
                <Label for="filtro-advogado">Nome do advogado</Label>
                <Input
                    id="filtro-advogado"
                    v-model="form.advogado"
                    name="advogado"
                    autocomplete="off"
                    :aria-invalid="Boolean(erros.advogado)"
                    @update:model-value="buscarComDebounce"
                />
                <InputError :message="erros.advogado" />
            </div>
        </div>

        <div class="grid gap-4 md:grid-cols-3">
            <div class="grid gap-1.5">
                <Label for="filtro-tribunal">Tribunal</Label>
                <select
                    id="filtro-tribunal"
                    v-model="form.tribunal"
                    name="tribunal"
                    :class="selectClass"
                    :aria-invalid="Boolean(erros.tribunal)"
                    @change="aoMudarSelecaoOuData"
                >
                    <option value="">Todos</option>
                    <option
                        v-for="t in tribunais"
                        :key="t.sigla"
                        :value="t.sigla"
                    >
                        {{ t.sigla }} — {{ t.nome }}
                    </option>
                </select>
                <InputError :message="erros.tribunal" />
            </div>
            <div class="grid gap-1.5">
                <Label for="filtro-inicio">Disponibilizada de</Label>
                <Input
                    id="filtro-inicio"
                    v-model="form.inicio"
                    name="inicio"
                    type="date"
                    :aria-invalid="Boolean(erros.inicio)"
                    @change="aoMudarSelecaoOuData"
                />
                <InputError :message="erros.inicio" />
            </div>
            <div class="grid gap-1.5">
                <Label for="filtro-fim">até</Label>
                <Input
                    id="filtro-fim"
                    v-model="form.fim"
                    name="fim"
                    type="date"
                    :min="form.inicio || undefined"
                    :aria-invalid="Boolean(erros.fim)"
                    @change="aoMudarSelecaoOuData"
                />
                <InputError :message="erros.fim" />
            </div>
        </div>

        <div class="flex flex-wrap items-center justify-end gap-2">
            <Button
                type="button"
                variant="ghost"
                data-testid="limpar"
                @click="limpar"
            >
                <X class="size-4" aria-hidden="true" />
                Limpar
            </Button>
            <Button type="submit" :disabled="carregando" data-testid="buscar">
                <Search class="size-4" aria-hidden="true" />
                Buscar
            </Button>
        </div>
    </form>
</template>
