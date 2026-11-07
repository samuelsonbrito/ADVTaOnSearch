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
    <!--
        Marca FarolJuris: um farol cuja luz revela as publicações, com a base em
        gancho que desenha o J de Juris. A torre segue a cor do texto
        (currentColor); a luz é sempre âmbar. Versão completa em branding/faroljuris.
    -->
    <svg
        xmlns="http://www.w3.org/2000/svg"
        viewBox="0 0 64 64"
        aria-hidden="true"
        :class="className"
        v-bind="$attrs"
    >
        <path d="M38.5 19 63 7.5v23z" fill="var(--marca-ambar)" opacity=".8" />
        <path d="M21 14.5 32 6l11 8.5z" fill="currentColor" />
        <rect
            x="25"
            y="14.5"
            width="14"
            height="10"
            rx="2"
            fill="var(--marca-ambar)"
        />
        <circle cx="32" cy="19.5" r="2.6" fill="var(--marca-luz)" />
        <path d="M25.5 26h13l3.6 22H21.9z" fill="currentColor" />
        <path d="M24.35 33h15.3l.65 4H23.7z" fill="var(--marca-ambar)" />
        <path
            d="M40 45c1.6 11.5-13 15.5-23 8"
            fill="none"
            stroke="currentColor"
            stroke-width="6.5"
            stroke-linecap="round"
        />
    </svg>
</template>
