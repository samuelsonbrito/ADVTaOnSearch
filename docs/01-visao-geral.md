# 01 · Visão geral e glossário

## O problema

Advogados precisam acompanhar diariamente as comunicações dos processos em que atuam.
Os tribunais publicam essas comunicações no **DJEN** (Diário de Justiça Eletrônico
Nacional), mantido pelo CNJ, e os prazos processuais contam a partir dessa publicação.
Perder uma intimação pode significar perder um prazo.

O CNJ oferece a **Comunica API** (`https://comunicaapi.pje.jus.br/api/v1`), pública e
sem autenticação, mas crua: JSON, sem alertas, sem histórico, sem exportação. O
AdvTaOn Search põe uma interface e uma rotina de monitoramento em cima dela.

## As três abas

| Aba               | Quem usa       | Para quê                                                                                                      |
| ----------------- | -------------- | ------------------------------------------------------------------------------------------------------------- |
| **Consulta**      | qualquer um    | Buscar agora por OAB+UF, nome da parte, nome do advogado, número do processo, tribunal e período.             |
| **Monitoramento** | usuário logado | Cadastrar "alvos" (OAB, parte ou processo). Todo dia o sistema busca, guarda o que é novo e avisa por e-mail. |
| **Exportações**   | usuário logado | Baixar uma seleção (ou tudo) da Consulta ou do Monitoramento em CSV, XLSX ou PDF.                             |

### Jornada típica

1. A advogada abre `/consulta`, digita a OAB `12345` e a UF `AM` e vê as comunicações.
   A URL (`/consulta?oab=12345&uf=AM`) pode ser compartilhada.
2. Ela cria uma conta, confirma o e-mail e cadastra a mesma OAB como alvo no
   Monitoramento. A primeira verificação roda na hora, em segundo plano.
3. Todo dia às 07:00 (horário de Manaus, configurável) o sistema verifica os últimos
   3 dias. Se houver comunicação nova, chega **um** e-mail com a lista. O que já foi
   avisado nunca é avisado de novo.
4. No painel, ela marca as comunicações como lidas e exporta as do mês em PDF.

## Glossário

### Termos jurídicos

| Termo                        | Significado                                                                                                            |
| ---------------------------- | ---------------------------------------------------------------------------------------------------------------------- |
| **DJEN**                     | Diário de Justiça Eletrônico Nacional. Onde os tribunais publicam as comunicações processuais.                         |
| **CNJ**                      | Conselho Nacional de Justiça. Mantém o DJEN e a Comunica API.                                                          |
| **Comunicação processual**   | Ato pelo qual o juízo dá ciência de algo às partes: intimação, citação, edital, etc.                                   |
| **Intimação**                | Comunica um ato do processo (decisão, prazo) a quem já faz parte dele.                                                 |
| **Citação**                  | Chama o réu ao processo pela primeira vez.                                                                             |
| **Edital**                   | Comunicação pública, para destinatários incertos ou não localizados.                                                   |
| **Data de disponibilização** | Dia em que a comunicação foi publicada no DJEN. É uma **data de calendário**, sem hora.                                |
| **Inteiro teor**             | O texto completo da comunicação (no app, aberto em um modal).                                                          |
| **Número CNJ**               | Numeração única de processos: `NNNNNNN-DD.AAAA.J.TR.OOOO`, 20 dígitos. Ex.: `0001234-56.2024.8.04.0001`.               |
| **OAB**                      | Número de inscrição do advogado na Ordem, **sempre junto com a UF** (`12345/AM`). Pode ter letra: `11811N`, `20413-O`. |
| **Parte**                    | Pessoa ou empresa envolvida no processo (autor, réu, terceiro...). Na API, "destinatários".                            |
| **Polo**                     | Lado da parte no processo. Na API: `A` (ativo), `P` (passivo) e outros códigos.                                        |
| **Órgão julgador**           | Vara, câmara ou turma que emitiu a comunicação (`nomeOrgao` na API).                                                   |
| **Sigla do tribunal**        | Ex.: `TJSP`, `TRF1`, `TRT11`. Na API, `siglaTribunal`.                                                                 |

### Termos do sistema

| Termo           | Significado                                                                                                             |
| --------------- | ----------------------------------------------------------------------------------------------------------------------- |
| **Alvo**        | O que um usuário monitora: uma OAB+UF, um nome de parte ou um número de processo (`monitoring_targets`).                |
| **Alerta**      | O vínculo "esta comunicação foi encontrada para este alvo" (`communication_target`, model `Alerta`). Tem lida/não lida. |
| **Hash**        | Identificador único que a API dá a cada comunicação. Usamos para não guardar nem avisar duas vezes.                     |
| **Gateway**     | A fonte de comunicações: `api` (CNJ de verdade) ou `fake` (45 comunicações fictícias para desenvolver).                 |
| **Geobloqueio** | A Comunica API responde `403` para IPs fora do Brasil. Por isso o servidor precisa estar no Brasil.                     |

## Limites conhecidos

- Os nomes dos **parâmetros de filtro** da API (exceto `itensPorPagina`) não puderam
  ser confirmados em uma chamada real, por causa do geobloqueio. O comando
  `php artisan djen:verificar-filtros` confere isso no servidor. Ver
  [03 · Integração](03-integracao-djen.md#parâmetros-de-filtro).
- A API limita o `count` a 10000 e tem rate limit (`x-ratelimit-limit: 20`).
- O WhatsApp está pronto na arquitetura, mas só existe o driver `log` (não envia).
- Não há endpoint confirmado de tribunais: a lista é estática (`config/tribunais.php`).
