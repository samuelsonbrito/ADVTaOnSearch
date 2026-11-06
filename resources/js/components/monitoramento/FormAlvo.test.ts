import { router } from '@inertiajs/vue3';
import { mount } from '@vue/test-utils';
import {
    afterEach,
    beforeEach,
    describe,
    expect,
    it,
    vi,
} from 'vite-plus/test';
import FormAlvo from './FormAlvo.vue';

const tiposAlvo = [
    { valor: 'oab' as const, rotulo: 'OAB' },
    { valor: 'parte' as const, rotulo: 'Nome da parte' },
    { valor: 'processo' as const, rotulo: 'Número do processo' },
];

function montar() {
    return mount(FormAlvo, {
        props: { tiposAlvo, ufs: ['AM', 'SP'] },
        attachTo: document.body,
    });
}

describe('FormAlvo', () => {
    beforeEach(() => {
        vi.stubGlobal('route', (nome: string) => `/rota/${nome}`);
    });

    afterEach(() => {
        vi.unstubAllGlobals();
        vi.restoreAllMocks();
        document.body.innerHTML = '';
    });

    it('mostra UF só para OAB', async () => {
        const wrapper = montar();
        expect(wrapper.find('#alvo-uf').exists()).toBe(true);

        await wrapper.find('#alvo-tipo').setValue('parte');

        expect(wrapper.find('#alvo-uf').exists()).toBe(false);
        expect(wrapper.find('label[for="alvo-valor"]').text()).toBe(
            'Nome da parte',
        );
    });

    it('aplica a máscara CNJ para processo e limpa o valor ao trocar de tipo', async () => {
        const wrapper = montar();
        await wrapper.find('#alvo-valor').setValue('123');
        await wrapper.find('#alvo-tipo').setValue('processo');

        const input = wrapper.find('#alvo-valor');
        expect((input.element as HTMLInputElement).value).toBe('');

        await input.setValue('00012345620248040001');
        expect((input.element as HTMLInputElement).value).toBe(
            '0001234-56.2024.8.04.0001',
        );
    });

    it('envia para a rota de cadastro', async () => {
        const visit = vi.spyOn(router, 'visit').mockImplementation(() => {});
        const wrapper = montar();

        await wrapper.find('#alvo-valor').setValue('12345');
        await wrapper.find('#alvo-uf').setValue('AM');
        await wrapper.find('form').trigger('submit');

        expect(visit).toHaveBeenCalledOnce();
        const [url, opcoes] = visit.mock.calls[0]!;
        expect(url).toBe('/rota/monitoramento.alvos.store');
        expect(opcoes).toMatchObject({
            method: 'post',
            data: { tipo: 'oab', valor: '12345', uf: 'AM', apelido: '' },
        });
    });

    it('desabilita o envio quando o limite de alvos foi atingido', () => {
        const wrapper = mount(FormAlvo, {
            props: { tiposAlvo, ufs: ['AM'], desabilitado: true },
        });

        expect(
            wrapper
                .find('[data-testid="adicionar-alvo"]')
                .attributes('disabled'),
        ).toBeDefined();
    });
});
