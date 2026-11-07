import { describe, expect, it } from 'vite-plus/test';
import {
    dataCurta,
    formatarDataHora,
    formatarValor,
    variacao,
} from './formatar';

describe('formatarValor', () => {
    it('formata números, percentuais e milissegundos em pt-BR', () => {
        expect(formatarValor(12345)).toBe('12.345');
        expect(formatarValor(87.25, 'percentual')).toBe('87,3%');
        expect(formatarValor(640, 'ms')).toBe('640 ms');
        expect(formatarValor(2350, 'ms')).toBe('2,4 s');
        expect(formatarValor(null)).toBe('—');
    });
});

describe('variacao', () => {
    it('compara com o período anterior', () => {
        expect(variacao(150, 100)).toEqual({ texto: '+50%', direcao: 'sobe' });
        expect(variacao(50, 100)).toEqual({ texto: '−50%', direcao: 'desce' });
        expect(variacao(10, 0)).toEqual({ texto: 'novo', direcao: 'sobe' });
        expect(variacao(0, 0)).toEqual({
            texto: '0%',
            direcao: 'igual',
        });
        expect(variacao(80, 75.5, 'percentual')).toEqual({
            texto: '+4,5 p.p.',
            direcao: 'sobe',
        });
        expect(variacao(null, 3)).toBeNull();
    });
});

describe('datas', () => {
    it('formata ISO e dia curto', () => {
        expect(formatarDataHora('2026-11-07T14:05:09-04:00')).toBe(
            '07/11/2026 14:05',
        );
        expect(formatarDataHora(null)).toBe('—');
        expect(dataCurta('2026-11-07')).toBe('07/11');
    });
});
