<script setup lang="ts">
defineProps<{
    titulo: string;
    descricao?: string;
}>();
</script>

<template>
    <div
        class="flex flex-col items-center justify-center rounded-xl border border-dashed px-6 py-14 text-center"
        role="status"
    >
        <div v-if="$slots.icone" class="mb-4 text-muted-foreground">
            <slot name="icone" />
        </div>
        <h3 class="text-base font-semibold">{{ titulo }}</h3>
        <p v-if="descricao" class="mt-1 max-w-md text-sm text-muted-foreground">
            {{ descricao }}
        </p>
        <div v-if="$slots.default" class="mt-5">
            <slot />
        </div>
    </div>
</template>
