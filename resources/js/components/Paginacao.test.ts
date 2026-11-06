import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vite-plus/test';
import Paginacao from './Paginacao.vue';

function paginas(wrapper: ReturnType<typeof mount>) {
    return wrapper
        .findAll('button')
        .filter((b) => /^Página \d+$/.test(b.attributes('aria-label') ?? ''))
        .map((b) => b.text());
}

describe('Paginacao', () => {
    it('não aparece com uma página só', () => {
        const wrapper = mount(Paginacao, {
            props: { pagina: 1, ultimaPagina: 1 },
        });

        expect(wrapper.find('nav').exists()).toBe(false);
    });

    it('mostra primeira, última, vizinhas e reticências', () => {
        const wrapper = mount(Paginacao, {
            props: { pagina: 5, ultimaPagina: 10 },
        });

        expect(paginas(wrapper)).toEqual(['1', '4', '5', '6', '10']);
        expect(wrapper.text().match(/…/g)).toHaveLength(2);
        expect(wrapper.find('[aria-current="page"]').text()).toBe('5');
    });

    it('emite a página escolhida e navega por anterior/próxima', async () => {
        const wrapper = mount(Paginacao, {
            props: { pagina: 2, ultimaPagina: 3 },
        });

        await wrapper.find('button[aria-label="Página 3"]').trigger('click');
        await wrapper.find('[data-testid="anterior"]').trigger('click');
        await wrapper.find('[data-testid="proxima"]').trigger('click');

        expect(wrapper.emitted('mudar')).toEqual([[3], [1], [3]]);
    });

    it('não emite para a página atual nem fora dos limites', async () => {
        const wrapper = mount(Paginacao, {
            props: { pagina: 1, ultimaPagina: 2 },
        });

        await wrapper.find('button[aria-label="Página 1"]').trigger('click');

        expect(
            wrapper.find('[data-testid="anterior"]').attributes('disabled'),
        ).toBeDefined();
        expect(wrapper.emitted('mudar')).toBeUndefined();
    });

    it('desabilita tudo durante o carregamento', () => {
        const wrapper = mount(Paginacao, {
            props: { pagina: 2, ultimaPagina: 3, desabilitado: true },
        });

        expect(
            wrapper
                .findAll('button')
                .every((b) => b.attributes('disabled') !== undefined),
        ).toBe(true);
    });
});
