<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { ScrollText } from '@lucide/vue';
import { computed } from 'vue';
import { Button } from '@/components/ui/button';

const page = usePage();
const usuario = computed(() => page.props.auth?.user ?? null);
const nome = computed(() => page.props.name ?? 'AdvTaOn Search');
</script>

<template>
    <div class="flex min-h-svh flex-col bg-background">
        <header class="border-b bg-card/60 backdrop-blur">
            <div
                class="mx-auto flex h-16 w-full max-w-6xl items-center gap-3 px-4 sm:gap-6"
            >
                <Link
                    :href="route('consulta.index')"
                    class="flex items-center gap-2 font-semibold tracking-tight"
                >
                    <span
                        class="flex size-8 items-center justify-center rounded-lg bg-primary text-primary-foreground"
                    >
                        <ScrollText class="size-4" aria-hidden="true" />
                    </span>
                    <span class="hidden sm:inline">{{ nome }}</span>
                    <span class="sr-only sm:hidden">{{ nome }}</span>
                </Link>

                <!-- No celular, a marca já leva à Consulta e "Entrar"/"Painel" ao monitoramento. -->
                <nav
                    aria-label="Principal"
                    class="hidden items-center gap-1 text-sm sm:flex"
                >
                    <Link
                        :href="route('consulta.index')"
                        class="rounded-md px-3 py-1.5 font-medium hover:bg-accent"
                        :class="{ 'bg-accent': route().current('consulta.*') }"
                    >
                        Consulta
                    </Link>
                    <Link
                        :href="route('monitoramento.index')"
                        class="rounded-md px-3 py-1.5 font-medium hover:bg-accent"
                    >
                        Monitoramento
                    </Link>
                </nav>

                <div class="ml-auto flex items-center gap-2">
                    <Button v-if="usuario" as-child variant="outline" size="sm">
                        <Link :href="route('monitoramento.index')">Painel</Link>
                    </Button>
                    <template v-else>
                        <Button as-child variant="ghost" size="sm">
                            <Link :href="route('login')">Entrar</Link>
                        </Button>
                        <Button as-child size="sm">
                            <Link :href="route('register')">Criar conta</Link>
                        </Button>
                    </template>
                </div>
            </div>
        </header>

        <main class="mx-auto w-full max-w-6xl flex-1 px-4 py-8">
            <slot />
        </main>

        <footer class="border-t py-6 text-center text-xs text-muted-foreground">
            Dados públicos do DJEN — Diário de Justiça Eletrônico Nacional
            (CNJ). Este serviço não tem vínculo com o CNJ.
        </footer>
    </div>
</template>
