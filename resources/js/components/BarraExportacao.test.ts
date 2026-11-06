import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vite-plus/test';
import BarraExportacao from './BarraExportacao.vue';

const stubs = { Link: { template: '<a><slot /></a>' } };

function montar(props: Record<string, unknown>) {
    return mount(BarraExportacao, {
        props: { quantidade: 0, total: 10, logado: true, ...props },
        global: { stubs, mocks: { route: (n: string) => `/${n}` } },
    });
}

describe('BarraExportacao', () => {
    it('não aparece sem resultados', () => {
        expect(
            montar({ total: 0 })
                .find('[data-testid=barra-exportacao]')
                .exists(),
        ).toBe(false);
    });

    it('convida visitantes a entrar', () => {
        const wrapper = montar({ logado: false });

        expect(wrapper.find('[data-testid=exportar-login]').text()).toContain(
            'Entre',
        );
        expect(
            wrapper.find('[data-testid=exportar-selecionadas]').exists(),
        ).toBe(false);
    });

    it('exporta as selecionadas no formato escolhido', async () => {
        const wrapper = montar({ quantidade: 2 });

        expect(
            wrapper.find('[data-testid=quantidade-selecionada]').text(),
        ).toBe('2 selecionadas');
        await wrapper.find('#formato-exportacao').setValue('pdf');
        await wrapper
            .find('[data-testid=exportar-selecionadas]')
            .trigger('click');

        expect(wrapper.emitted('exportar')).toEqual([
            [{ formato: 'pdf', todos: false }],
        ]);
    });

    it('exporta todas, mesmo sem seleção, e mostra o total', async () => {
        const wrapper = montar({ quantidade: 0, total: 1234 });

        expect(
            wrapper
                .find('[data-testid=exportar-selecionadas]')
                .attributes('disabled'),
        ).toBeDefined();
        expect(wrapper.find('[data-testid=exportar-todas]').text()).toBe(
            'Exportar todas (1.234)',
        );

        await wrapper.find('[data-testid=exportar-todas]').trigger('click');
        expect(wrapper.emitted('exportar')).toEqual([
            [{ formato: 'xlsx', todos: true }],
        ]);
    });

    it('limpa a seleção e desabilita durante o envio', async () => {
        const wrapper = montar({ quantidade: 1, enviando: true });

        await wrapper.find('[data-testid=limpar-selecao]').trigger('click');

        expect(wrapper.emitted('limpar')).toHaveLength(1);
        expect(
            wrapper.find('[data-testid=exportar-todas]').attributes('disabled'),
        ).toBeDefined();
    });
});
