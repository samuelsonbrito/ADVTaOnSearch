import { computed, ref } from 'vue';

/**
 * Seleção em lote que sobrevive à troca de página (o componente é mantido
 * com preserveState). Cada chave guarda a página em que foi selecionada,
 * para a exportação da Consulta saber onde buscar cada item.
 */
export function useSelecao<K extends string | number>() {
    const itens = ref(new Map<K, number>()) as { value: Map<K, number> };

    const quantidade = computed(() => itens.value.size);

    function selecionado(chave: K): boolean {
        return itens.value.has(chave);
    }

    function alternar(chave: K, pagina = 1, marcar?: boolean): void {
        const novo = new Map(itens.value);
        const deveMarcar = marcar ?? !novo.has(chave);

        if (deveMarcar) {
            novo.set(chave, pagina);
        } else {
            novo.delete(chave);
        }

        itens.value = novo;
    }

    /** Marca (ou desmarca, se todas já estiverem marcadas) as chaves da página. */
    function alternarPagina(chaves: K[], pagina = 1): void {
        const todas = chaves.length > 0 && chaves.every(selecionado);
        const novo = new Map(itens.value);

        for (const chave of chaves) {
            if (todas) {
                novo.delete(chave);
            } else {
                novo.set(chave, pagina);
            }
        }

        itens.value = novo;
    }

    function paginaToda(chaves: K[]): boolean {
        return chaves.length > 0 && chaves.every(selecionado);
    }

    function limpar(): void {
        itens.value = new Map();
    }

    /** Agrupa por página: [{ pagina, chaves }], em ordem de página. */
    function porPagina(): { pagina: number; chaves: K[] }[] {
        const grupos = new Map<number, K[]>();

        for (const [chave, pagina] of itens.value) {
            grupos.set(pagina, [...(grupos.get(pagina) ?? []), chave]);
        }

        return [...grupos.entries()]
            .sort(([a], [b]) => a - b)
            .map(([pagina, chaves]) => ({ pagina, chaves }));
    }

    function chaves(): K[] {
        return [...itens.value.keys()];
    }

    return {
        quantidade,
        selecionado,
        alternar,
        alternarPagina,
        paginaToda,
        limpar,
        porPagina,
        chaves,
    };
}
