<?php

/*
| Tribunais para o filtro da Consulta. As siglas seguem o formato que a
| Comunica API usa em siglaTribunal (confirmado na resposta real: TJMG,
| TRT3, TRF6, TJDFT, TJMMG, STJ, TST, STM, CJF, SEEU...).
*/

$estados = [
    'AC' => 'Acre', 'AL' => 'Alagoas', 'AM' => 'Amazonas', 'AP' => 'Amapá', 'BA' => 'Bahia',
    'CE' => 'Ceará', 'DFT' => 'Distrito Federal e Territórios', 'ES' => 'Espírito Santo',
    'GO' => 'Goiás', 'MA' => 'Maranhão', 'MG' => 'Minas Gerais', 'MS' => 'Mato Grosso do Sul',
    'MT' => 'Mato Grosso', 'PA' => 'Pará', 'PB' => 'Paraíba', 'PE' => 'Pernambuco', 'PI' => 'Piauí',
    'PR' => 'Paraná', 'RJ' => 'Rio de Janeiro', 'RN' => 'Rio Grande do Norte', 'RO' => 'Rondônia',
    'RR' => 'Roraima', 'RS' => 'Rio Grande do Sul', 'SC' => 'Santa Catarina', 'SE' => 'Sergipe',
    'SP' => 'São Paulo', 'TO' => 'Tocantins',
];

$lista = [
    'STJ' => 'Superior Tribunal de Justiça',
    'TST' => 'Tribunal Superior do Trabalho',
    'STM' => 'Superior Tribunal Militar',
    'CJF' => 'Conselho da Justiça Federal',
    'SEEU' => 'Sistema Eletrônico de Execução Unificado',
];

foreach ($estados as $uf => $nome) {
    $lista["TJ{$uf}"] = "Tribunal de Justiça - {$nome}";
}

foreach (range(1, 6) as $n) {
    $lista["TRF{$n}"] = "Tribunal Regional Federal da {$n}ª Região";
}

foreach (range(1, 24) as $n) {
    $lista["TRT{$n}"] = "Tribunal Regional do Trabalho da {$n}ª Região";
}

$lista['TJMMG'] = 'Tribunal de Justiça Militar - Minas Gerais';
$lista['TJMRS'] = 'Tribunal de Justiça Militar - Rio Grande do Sul';
$lista['TJMSP'] = 'Tribunal de Justiça Militar - São Paulo';

return $lista;
