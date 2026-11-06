/** Mantém só os dígitos. */
export function somenteDigitos(valor: string): string {
    return valor.replace(/\D/g, '');
}

/**
 * Aplica a máscara CNJ (NNNNNNN-DD.AAAA.J.TR.OOOO) progressivamente,
 * conforme o usuário digita. Aceita no máximo 20 dígitos.
 */
export function mascaraCnj(valor: string): string {
    const d = somenteDigitos(valor).slice(0, 20);
    const partes: [number, string][] = [
        [7, '-'],
        [9, '.'],
        [13, '.'],
        [14, '.'],
        [16, '.'],
    ];

    let saida = '';

    for (let i = 0; i < d.length; i++) {
        const separador = partes.find(([pos]) => pos === i);

        if (separador) {
            saida += separador[1];
        }

        saida += d[i];
    }

    return saida;
}

export function cnjCompleto(valor: string): boolean {
    return somenteDigitos(valor).length === 20;
}

/** AAAA-MM-DD → DD/MM/AAAA, sem conversão de fuso. */
export function formatarData(iso: string): string {
    const [ano, mes, dia] = iso.split('-');

    return ano && mes && dia ? `${dia}/${mes}/${ano}` : iso;
}
