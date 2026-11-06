<script setup lang="ts">
import { Skeleton } from '@/components/ui/skeleton';

withDefaults(defineProps<{ quantidade?: number }>(), { quantidade: 3 });
</script>

<template>
    <div class="grid gap-4" aria-busy="true" aria-label="Carregando resultados">
        <div
            v-for="n in quantidade"
            :key="n"
            class="rounded-xl border p-5"
            data-testid="skeleton"
        >
            <div class="flex gap-2">
                <Skeleton class="h-5 w-14 rounded-full" />
                <Skeleton class="h-5 w-20 rounded-full" />
                <Skeleton class="ml-auto h-4 w-20" />
            </div>
            <Skeleton class="mt-4 h-5 w-64" />
            <Skeleton class="mt-3 h-4 w-48" />
            <Skeleton class="mt-2 h-4 w-72" />
            <Skeleton class="mt-4 h-12 w-full" />
        </div>
    </div>
</template>
