import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vite-plus/test';
import CartaoIndicador from './CartaoIndicador.vue';
import GraficoColunas from './GraficoColunas.vue';
import ListaBarras from './ListaBarras.vue';

const pontos = [
    { rotulo: '05/11', dica: '05/11/2026', valor: 4 },
    { rotulo: '06/11', dica: '06/11/2026', valor: 0 },
    { rotulo: '07/11', dica: '07/11/2026', valor: 10 },
];

describe('GraficoColunas', () => {
    it('desenha uma coluna por ponto com valor e um eixo de 0 a um teto redondo', () => {
        const w = mount(GraficoColunas, { props: { pontos, serie: 'Buscas' } });

        // A coluna de valor 0 não desenha nada.
        expect(
            w
                .findAll('path[fill="var(--viz-serie)"]')
                .filter((p) => p.attributes('d')),
        ).toHaveLength(2);
        expect(w.findAll('text').map((t) => t.text())).toEqual(
            expect.arrayContaining(['0', '5', '10', '05/11', '07/11']),
        );
        expect(w.find('svg').attributes('aria-label')).toContain('Buscas');
    });

    it('mostra o tooltip pelo teclado', async () => {
        const w = mount(GraficoColunas, { props: { pontos, serie: 'Buscas' } });

        await w.find('svg').trigger('keydown', { key: 'ArrowRight' });
        await w.find('svg').trigger('keydown', { key: 'ArrowRight' });
        await w.find('svg').trigger('keydown', { key: 'ArrowRight' });

        const dica = w.find('[role="status"]');
        expect(dica.text()).toContain('10');
        expect(dica.text()).toContain('Buscas · 07/11/2026');
    });
});

describe('ListaBarras', () => {
    it('escreve rótulo, valor e participação; mostra o vazio', () => {
        const w = mount(ListaBarras, {
            props: {
                itens: [
                    { chave: 'oab', rotulo: 'OAB', total: 3 },
                    { chave: 'parte', rotulo: 'Parte', total: 1 },
                ],
            },
        });

        expect(w.text()).toContain('OAB');
        expect(w.text()).toContain('75%');
        expect(w.text()).toContain('25%');

        const vazio = mount(ListaBarras, {
            props: { itens: [], vazio: 'Nada aqui.' },
        });
        expect(vazio.text()).toBe('Nada aqui.');
    });
});

describe('CartaoIndicador', () => {
    it('mostra o valor e a variação contra o período anterior', () => {
        const w = mount(CartaoIndicador, {
            props: {
                rotulo: 'Buscas',
                indicador: { valor: 1500, anterior: 1000 },
            },
        });

        expect(w.text()).toContain('1.500');
        expect(w.text()).toContain('+50%');
        expect(w.text()).toContain('vs. anterior');
    });

    it('usa o detalhe quando não há comparação', () => {
        const w = mount(CartaoIndicador, {
            props: {
                rotulo: 'Alvos',
                indicador: { valor: 7, anterior: null },
                detalhe: 'agora',
            },
        });

        expect(w.text()).toContain('agora');
    });
});
