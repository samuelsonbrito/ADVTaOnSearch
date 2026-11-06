<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { Pause, Play, RefreshCw, Trash2 } from '@lucide/vue';
import { ref } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import type { AlvoMonitoramento } from '@/types/monitoramento';

defineProps<{
    alvos: AlvoMonitoramento[];
    selecionado: number | null;
}>();

const emit = defineEmits<{ filtrar: [alvo: number | null] }>();

const paraExcluir = ref<AlvoMonitoramento | null>(null);
const excluindo = ref(false);

function quando(iso: string | null): string {
    if (!iso) {
        return 'Ainda não verificado';
    }

    return `Verificado em ${new Date(iso).toLocaleString('pt-BR', {
        dateStyle: 'short',
        timeStyle: 'short',
    })}`;
}

function alternar(alvo: AlvoMonitoramento) {
    router.patch(
        route('monitoramento.alvos.update', alvo.id),
        { ativo: !alvo.ativo },
        { preserveScroll: true },
    );
}

function verificar(alvo: AlvoMonitoramento) {
    router.post(
        route('monitoramento.alvos.verificar', alvo.id),
        {},
        { preserveScroll: true },
    );
}

function excluir() {
    if (!paraExcluir.value) {
        return;
    }

    router.delete(route('monitoramento.alvos.destroy', paraExcluir.value.id), {
        preserveScroll: true,
        onStart: () => (excluindo.value = true),
        onFinish: () => {
            excluindo.value = false;
            paraExcluir.value = null;
        },
    });
}
</script>

<template>
    <ul class="grid gap-2" aria-label="Alvos monitorados">
        <li
            v-for="alvo in alvos"
            :key="alvo.id"
            class="rounded-lg border p-3 transition-colors"
            :class="{
                'border-primary bg-accent/50': selecionado === alvo.id,
                'opacity-60': !alvo.ativo,
            }"
            :data-testid="`alvo-${alvo.id}`"
        >
            <div class="flex items-start gap-2">
                <button
                    type="button"
                    class="min-w-0 flex-1 text-left"
                    :aria-pressed="selecionado === alvo.id"
                    @click="
                        emit(
                            'filtrar',
                            selecionado === alvo.id ? null : alvo.id,
                        )
                    "
                >
                    <span class="block truncate text-sm font-medium">{{
                        alvo.descricao
                    }}</span>
                    <span class="block text-xs text-muted-foreground">
                        {{ alvo.tipoRotulo }} ·
                        {{
                            alvo.ativo
                                ? quando(alvo.ultimaVerificacaoEm)
                                : 'Pausado'
                        }}
                    </span>
                </button>
                <Badge
                    v-if="alvo.naoLidas > 0"
                    :aria-label="`${alvo.naoLidas} não lidas`"
                    data-testid="nao-lidas"
                    >{{ alvo.naoLidas }}</Badge
                >
            </div>
            <div class="mt-2 flex gap-1">
                <Button
                    variant="ghost"
                    size="sm"
                    class="h-7 px-2 text-xs"
                    :disabled="!alvo.ativo"
                    @click="verificar(alvo)"
                >
                    <RefreshCw class="size-3.5" aria-hidden="true" />
                    Verificar agora
                </Button>
                <Button
                    variant="ghost"
                    size="sm"
                    class="h-7 px-2 text-xs"
                    @click="alternar(alvo)"
                >
                    <component
                        :is="alvo.ativo ? Pause : Play"
                        class="size-3.5"
                        aria-hidden="true"
                    />
                    {{ alvo.ativo ? 'Pausar' : 'Retomar' }}
                </Button>
                <Button
                    variant="ghost"
                    size="sm"
                    class="ml-auto h-7 px-2 text-xs text-destructive hover:text-destructive"
                    :aria-label="`Excluir ${alvo.descricao}`"
                    @click="paraExcluir = alvo"
                >
                    <Trash2 class="size-3.5" aria-hidden="true" />
                </Button>
            </div>
        </li>
    </ul>

    <Dialog
        :open="paraExcluir !== null"
        @update:open="(aberto) => !aberto && (paraExcluir = null)"
    >
        <DialogContent>
            <DialogHeader>
                <DialogTitle>Excluir alvo?</DialogTitle>
                <DialogDescription>
                    O monitoramento de
                    <strong>{{ paraExcluir?.descricao }}</strong>
                    será encerrado e todo o histórico de comunicações dele será
                    apagado. Esta ação não pode ser desfeita.
                </DialogDescription>
            </DialogHeader>
            <DialogFooter>
                <DialogClose as-child>
                    <Button variant="outline">Cancelar</Button>
                </DialogClose>
                <Button
                    variant="destructive"
                    :disabled="excluindo"
                    data-testid="confirmar-exclusao"
                    @click="excluir"
                    >Excluir</Button
                >
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
