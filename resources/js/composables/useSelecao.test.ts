import { describe, expect, it } from 'vite-plus/test';
import { useSelecao } from './useSelecao';

describe('useSelecao', () => {
    it('alterna itens e conta', () => {
        const s = useSelecao<string>();

        s.alternar('a', 1);
        s.alternar('b', 2);
        s.alternar('a');

        expect(s.quantidade.value).toBe(1);
        expect(s.selecionado('b')).toBe(true);
        expect(s.selecionado('a')).toBe(false);
    });

    it('respeita o valor explícito ao alternar', () => {
        const s = useSelecao<number>();

        s.alternar(1, 1, true);
        s.alternar(1, 1, true);
        expect(s.quantidade.value).toBe(1);

        s.alternar(1, 1, false);
        expect(s.quantidade.value).toBe(0);
    });

    it('seleciona e desmarca a página inteira', () => {
        const s = useSelecao<string>();
        s.alternar('x', 1);

        s.alternarPagina(['x', 'y', 'z'], 1);
        expect(s.paginaToda(['x', 'y', 'z'])).toBe(true);
        expect(s.quantidade.value).toBe(3);

        s.alternarPagina(['x', 'y', 'z'], 1);
        expect(s.quantidade.value).toBe(0);
        expect(s.paginaToda([])).toBe(false);
    });

    it('agrupa por página em ordem e mantém seleção entre páginas', () => {
        const s = useSelecao<string>();
        s.alternar('c', 3);
        s.alternar('a', 1);
        s.alternar('b', 1);

        expect(s.porPagina()).toEqual([
            { pagina: 1, chaves: ['a', 'b'] },
            { pagina: 3, chaves: ['c'] },
        ]);
        expect(s.chaves()).toEqual(['c', 'a', 'b']);

        s.limpar();
        expect(s.quantidade.value).toBe(0);
    });
});
