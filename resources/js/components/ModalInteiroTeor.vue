<script setup lang="ts">
import { ExternalLink } from '@lucide/vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { formatarData } from '@/lib/cnj';
import type { Comunicacao } from '@/types/djen';

defineProps<{ comunicacao: Comunicacao | null }>();

const aberto = defineModel<boolean>('open', { default: false });
</script>

<template>
    <Dialog v-model:open="aberto">
        <DialogContent
            v-if="comunicacao"
            class="flex max-h-[90svh] flex-col sm:max-w-3xl"
        >
            <DialogHeader>
                <DialogTitle class="font-mono">
                    {{ comunicacao.numeroProcesso }}
                </DialogTitle>
                <DialogDescription>
                    {{ comunicacao.tribunal }}
                    <template v-if="comunicacao.orgaoJulgador">
                        · {{ comunicacao.orgaoJulgador }}</template
                    >
                    · {{ formatarData(comunicacao.dataDisponibilizacao) }}
                </DialogDescription>
            </DialogHeader>

            <!-- textoHtml é sanitizado no servidor (App\Support\HtmlSanitizer). -->
            <div
                class="prose-sm -mx-1 min-h-0 flex-1 space-y-3 overflow-y-auto px-1 text-sm leading-relaxed [&_table]:w-full [&_td]:border [&_td]:p-1 [&_th]:border [&_th]:p-1"
                data-testid="inteiro-teor"
                v-html="comunicacao.textoHtml"
            />

            <DialogFooter>
                <Button v-if="comunicacao.linkDocumento" as-child>
                    <a
                        :href="comunicacao.linkDocumento"
                        target="_blank"
                        rel="noopener noreferrer"
                        data-testid="link-documento"
                    >
                        <ExternalLink class="size-4" aria-hidden="true" />
                        Abrir documento
                    </a>
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
