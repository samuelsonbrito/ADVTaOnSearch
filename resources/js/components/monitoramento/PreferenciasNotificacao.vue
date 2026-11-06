<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { ShieldAlert } from '@lucide/vue';
import { ref } from 'vue';
import InputError from '@/components/InputError.vue';
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
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { PreferenciasNotificacao } from '@/types/monitoramento';

const props = defineProps<{ preferencias: PreferenciasNotificacao }>();

function formatarWhatsapp(numero: string | null): string {
    const d = (numero ?? '').replace(/^55/, '');

    return d.length >= 10
        ? `(${d.slice(0, 2)}) ${d.slice(2, d.length - 4)}-${d.slice(-4)}`
        : d;
}

const form = useForm({
    whatsapp: formatarWhatsapp(props.preferencias.whatsapp),
});
const exclusao = useForm({ password: '' });
const confirmando = ref(false);

function salvar() {
    form.put(route('monitoramento.notificacoes.update'), {
        preserveScroll: true,
    });
}

function excluirTudo() {
    exclusao.delete(route('monitoramento.dados.destroy'), {
        preserveScroll: true,
        onSuccess: () => (confirmando.value = false),
        onFinish: () => exclusao.reset(),
    });
}
</script>

<template>
    <div class="grid gap-5">
        <div class="text-sm text-muted-foreground">
            Verificamos seus alvos todos os dias às
            <strong class="text-foreground">{{ preferencias.horario }}</strong
            >, olhando os últimos {{ preferencias.diasAnteriores }} dias. Os
            avisos vão para
            <strong class="text-foreground">{{ preferencias.email }}</strong
            >.
        </div>

        <form class="grid gap-2" @submit.prevent="salvar">
            <Label for="whatsapp">
                WhatsApp
                <span class="font-normal text-muted-foreground"
                    >(opcional)</span
                >
            </Label>
            <div class="flex gap-2">
                <Input
                    id="whatsapp"
                    v-model="form.whatsapp"
                    name="whatsapp"
                    inputmode="tel"
                    autocomplete="tel"
                    placeholder="(92) 99999-9999"
                    :aria-invalid="Boolean(form.errors.whatsapp)"
                />
                <Button
                    type="submit"
                    variant="outline"
                    :disabled="form.processing"
                    >Salvar</Button
                >
            </div>
            <InputError :message="form.errors.whatsapp" />
            <p
                v-if="preferencias.whatsappDriver === 'log'"
                class="text-xs text-muted-foreground"
            >
                O envio por WhatsApp ainda está em modo de teste: as mensagens
                são registradas, mas não enviadas.
            </p>
        </form>

        <div class="rounded-lg border border-destructive/30 p-3">
            <p class="flex items-center gap-1.5 text-sm font-medium">
                <ShieldAlert
                    class="size-4 text-destructive"
                    aria-hidden="true"
                />
                Seus dados (LGPD)
            </p>
            <p class="mt-1 text-xs text-muted-foreground">
                Guardamos só o necessário para o monitoramento: seus alvos, as
                comunicações encontradas e o número de WhatsApp, se informado.
            </p>
            <Button
                variant="ghost"
                size="sm"
                class="mt-2 h-auto px-2 py-1.5 text-left whitespace-normal text-destructive hover:text-destructive"
                data-testid="excluir-dados"
                @click="confirmando = true"
                >Excluir todos os meus dados de monitoramento</Button
            >
        </div>

        <Dialog v-model:open="confirmando">
            <DialogContent>
                <form class="grid gap-4" @submit.prevent="excluirTudo">
                    <DialogHeader>
                        <DialogTitle>Excluir todos os dados?</DialogTitle>
                        <DialogDescription>
                            Todos os alvos, o histórico de comunicações e o
                            WhatsApp cadastrado serão apagados. Sua conta
                            continua ativa. Digite sua senha para confirmar.
                        </DialogDescription>
                    </DialogHeader>
                    <div class="grid gap-1.5">
                        <Label for="confirmar-senha" class="sr-only"
                            >Senha</Label
                        >
                        <Input
                            id="confirmar-senha"
                            v-model="exclusao.password"
                            type="password"
                            name="password"
                            autocomplete="current-password"
                            placeholder="Senha"
                        />
                        <InputError :message="exclusao.errors.password" />
                    </div>
                    <DialogFooter>
                        <DialogClose as-child>
                            <Button type="button" variant="outline"
                                >Cancelar</Button
                            >
                        </DialogClose>
                        <Button
                            type="submit"
                            variant="destructive"
                            :disabled="exclusao.processing"
                            >Excluir tudo</Button
                        >
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    </div>
</template>
