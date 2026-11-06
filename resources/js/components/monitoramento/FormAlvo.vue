<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { Plus } from '@lucide/vue';
import { computed } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { mascaraCnj } from '@/lib/cnj';
import type { TipoAlvo } from '@/types/monitoramento';

defineProps<{
    tiposAlvo: { valor: TipoAlvo; rotulo: string }[];
    ufs: string[];
    desabilitado?: boolean;
}>();

const form = useForm({
    tipo: 'oab' as TipoAlvo,
    valor: '',
    uf: '',
    apelido: '',
});

const selectClass =
    'h-9 w-full rounded-md border border-input bg-transparent px-3 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 aria-invalid:border-destructive dark:bg-input/30';

const placeholder = computed(
    () =>
        ({
            oab: 'Número da OAB, ex.: 12345',
            parte: 'Nome completo da parte',
            processo: '0000000-00.0000.0.00.0000',
        })[form.tipo],
);

function aoDigitar(valor: string | number) {
    form.valor =
        form.tipo === 'processo' ? mascaraCnj(String(valor)) : String(valor);
}

function aoMudarTipo() {
    form.valor = '';
    form.uf = '';
    form.clearErrors();
}

function enviar() {
    form.post(route('monitoramento.alvos.store'), {
        preserveScroll: true,
        onSuccess: () => form.reset('valor', 'uf', 'apelido'),
    });
}

defineExpose({ form });
</script>

<template>
    <form class="grid gap-3" aria-label="Novo alvo" @submit.prevent="enviar">
        <div class="grid gap-1.5">
            <Label for="alvo-tipo">Monitorar por</Label>
            <select
                id="alvo-tipo"
                v-model="form.tipo"
                name="tipo"
                :class="selectClass"
                @change="aoMudarTipo"
            >
                <option v-for="t in tiposAlvo" :key="t.valor" :value="t.valor">
                    {{ t.rotulo }}
                </option>
            </select>
            <InputError :message="form.errors.tipo" />
        </div>

        <div
            class="grid gap-3"
            :class="form.tipo === 'oab' ? 'grid-cols-[1fr_5.5rem]' : ''"
        >
            <div class="grid gap-1.5">
                <Label for="alvo-valor">{{
                    tiposAlvo.find((t) => t.valor === form.tipo)?.rotulo
                }}</Label>
                <Input
                    id="alvo-valor"
                    :model-value="form.valor"
                    name="valor"
                    autocomplete="off"
                    :inputmode="form.tipo === 'parte' ? 'text' : 'numeric'"
                    :placeholder="placeholder"
                    :class="{ 'font-mono': form.tipo === 'processo' }"
                    :aria-invalid="Boolean(form.errors.valor)"
                    @update:model-value="aoDigitar"
                />
            </div>
            <div v-if="form.tipo === 'oab'" class="grid gap-1.5">
                <Label for="alvo-uf">UF</Label>
                <select
                    id="alvo-uf"
                    v-model="form.uf"
                    name="uf"
                    :class="selectClass"
                    :aria-invalid="Boolean(form.errors.uf)"
                >
                    <option value="">—</option>
                    <option v-for="uf in ufs" :key="uf" :value="uf">
                        {{ uf }}
                    </option>
                </select>
            </div>
        </div>
        <InputError :message="form.errors.valor" />
        <InputError :message="form.errors.uf" />

        <div class="grid gap-1.5">
            <Label for="alvo-apelido">
                Apelido
                <span class="font-normal text-muted-foreground"
                    >(opcional)</span
                >
            </Label>
            <Input
                id="alvo-apelido"
                v-model="form.apelido"
                name="apelido"
                autocomplete="off"
                placeholder="Ex.: Meu escritório"
                maxlength="80"
            />
            <InputError :message="form.errors.apelido" />
        </div>

        <Button
            type="submit"
            :disabled="form.processing || desabilitado"
            data-testid="adicionar-alvo"
        >
            <Plus class="size-4" aria-hidden="true" />
            Adicionar alvo
        </Button>
    </form>
</template>
