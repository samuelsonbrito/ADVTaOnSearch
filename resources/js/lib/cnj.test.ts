import { describe, expect, it } from 'vite-plus/test';
import {
    cnjCompleto,
    formatarData,
    mascaraCnj,
    normalizarOab,
    somenteDigitos,
} from './cnj';

describe('mascaraCnj', () => {
    it('formata um número completo', () => {
        expect(mascaraCnj('00012345620248040001')).toBe(
            '0001234-56.2024.8.04.0001',
        );
    });

    it('aplica a máscara progressivamente', () => {
        expect(mascaraCnj('1234567')).toBe('1234567');
        expect(mascaraCnj('12345678')).toBe('1234567-8');
        expect(mascaraCnj('123456789012')).toBe('1234567-89.012');
    });

    it('ignora caracteres não numéricos e limita a 20 dígitos', () => {
        expect(mascaraCnj('0001234-56.2024.8.04.0001999')).toBe(
            '0001234-56.2024.8.04.0001',
        );
        expect(mascaraCnj('abc')).toBe('');
    });

    it('reconhece número completo', () => {
        expect(cnjCompleto('0001234-56.2024.8.04.0001')).toBe(true);
        expect(cnjCompleto('0001234-56')).toBe(false);
        expect(somenteDigitos('12.3-4')).toBe('1234');
    });
});

describe('formatarData', () => {
    it('converte ISO para o formato brasileiro', () => {
        expect(formatarData('2026-11-06')).toBe('06/11/2026');
        expect(formatarData('inválida')).toBe('inválida');
    });
});

describe('normalizarOab', () => {
    it('mantém letra e hífen e limpa o resto', () => {
        expect(normalizarOab(' 12.345 ')).toBe('12345');
        expect(normalizarOab('20413-o')).toBe('20413-O');
        expect(normalizarOab('OAB 11811n')).toBe('11811N');
    });
});
