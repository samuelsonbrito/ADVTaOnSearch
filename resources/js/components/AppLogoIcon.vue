<script setup lang="ts">
import type { HTMLAttributes } from 'vue';

defineOptions({
    inheritAttrs: false,
});

type Props = {
    className?: HTMLAttributes['class'];
};

defineProps<Props>();
</script>

<template>
    <!-- Marca AdvTaOn Search: documento (diário) com lupa (consulta). -->
    <svg
        xmlns="http://www.w3.org/2000/svg"
        viewBox="0 0 24 24"
        fill="none"
        stroke="currentColor"
        stroke-width="2"
        stroke-linecap="round"
        stroke-linejoin="round"
        aria-hidden="true"
        :class="className"
        v-bind="$attrs"
    >
        <path
            fill="none"
            d="M12 21H6a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h8l5 5v2.5"
        />
        <path fill="none" d="M14 3v5h5" />
        <path fill="none" d="M8 9h2M8 13h3M8 17h2" />
        <circle fill="none" cx="16" cy="16" r="3.5" />
        <path fill="none" d="m18.6 18.6 2.6 2.6" />
    </svg>
</template>
