import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vite-plus/test';
import { defineComponent, h } from 'vue';

describe('ambiente de testes do front-end', () => {
    it('monta um componente Vue no jsdom', () => {
        const Ola = defineComponent({
            props: { nome: { type: String, required: true } },
            setup: (props) => () => h('p', `Olá, ${props.nome}`),
        });

        const wrapper = mount(Ola, { props: { nome: 'DJEN' } });

        expect(wrapper.text()).toBe('Olá, DJEN');
    });
});
