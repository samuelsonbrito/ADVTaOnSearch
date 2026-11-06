# Fixtures da Comunica API (DJEN)

`comunicacao.json` é **derivado de uma resposta real** de
`GET https://comunicaapi.pje.jus.br/api/v1/comunicacao?itensPorPagina=100`
(capturada em 06/11/2026 no navegador, 100 itens, `count: 10000`).

Por LGPD, os dados pessoais foram substituídos por valores fictícios. Foram
preservados **exatamente**: os nomes das chaves, os tipos, os formatos de data,
os valores de domínio (tribunal, tipos, meio, classe, polo, status) e as
variações de formato encontradas nos 100 itens reais:

- `texto` em texto puro com `\n`, em HTML de fragmento, em documento HTML
  completo (`<!DOCTYPE>`, `<?xml?>`, `<head><style>`), com entidades HTML
  (`&Ccedil;`, `&nbsp;`) e com o aviso "Processo sigiloso";
- `link` nulo; `destinatarioadvogados` vazio;
- advogado e destinatário duplicados na mesma comunicação;
- `numero_oab` alfanumérico (`11811N`, `20413-O`, `309-B`) e o advogado
  placeholder `SISTEMA DE CITAÇÃO E INTIMAÇÃO ELETRÔNICA` (OAB `99999999`);
- `nomeClasse` com caixa inconsistente (`APELAçãO / REMESSA NECESSáRIA`);
- destinatários sigilosos (`SIGILO`, `SIGILOSO 1`, iniciais).

`comunicacao.headers.json` traz os cabeçalhos relevantes da mesma resposta.
Para atualizar com uma resposta nova: `php artisan djen:capturar-fixture` e
anonimize antes de commitar.
