# Regras dos números — Relatórios do Painel Ottoboni

Este documento é a fonte da verdade das métricas. Qualquer mudança no
`ReportService` precisa ser refletida aqui **e** nos testes
(`tests/Feature/Reports/ReportServiceTest.php`).

## Regras gerais

- **"Lead do período"** = `created_at` dentro do intervalo, interpretado no
  fuso **America/Sao_Paulo**. O intervalo é convertido para timestamp Unix
  (UTC) só na hora de montar o filtro da API — um lead criado às 23h30 de
  04/08 em SP conta no dia 04/08, mesmo sendo 02h30 UTC de 05/08.
  *(testado)*
- Lead sem valor num campo entra como **"Não informado"** — nunca some do
  total. *(testado)*
- A soma das fatias de qualquer bloco de distribuição **bate com o total do
  bloco 1**. *(testado)*
- Comparação com o período anterior usa a **mesma quantidade de dias**,
  imediatamente antes do início do período atual. *(testado)*

## Bloco 1 — Volume

- `total`: leads criados no período (regra geral acima).
- `por_dia`: contagem por dia local; dias sem lead aparecem com zero.
- `variacao_pct`: `(atual − anterior) / anterior × 100`, arredondado a 1
  casa. Se o período anterior tem zero leads, a variação é `null`
  ("sem base de comparação"), nunca infinito ou 100%.

## Blocos 2 e 3 — Origem e Temperatura

- Distribuição pelo valor do campo personalizado (`CF_ORIGEM`,
  `CF_TEMPERATURA`), comparação de texto sem diferenciar maiúsculas.
- `pct` = fatia / total do bloco 1, arredondado a 1 casa.

## Bloco 4 — Procedimentos

- Ranking por `CF_PROCEDIMENTO`: top 10 + fatia **"Outros"** (soma do resto)
  + "Não informado". A soma continua batendo com o total. *(testado)*

## Bloco 5 — Funil

- Um funil por médico selecionado (pipeline do Kommo).
- Etapas na ordem do `sort` do Kommo; `status 142` = ganho e `143` =
  perdido ficam fora da lista de etapas e viram os contadores
  `ganhos`/`perdidos`.
- `taxa_ganho_pct` = ganhos / total do pipeline.
- **"Chegaram até a etapa X"** = leads que estão em X, numa etapa
  posterior, ou ganharam. **Limitação do dado ao vivo:** a API não informa
  em qual etapa um lead foi perdido, então leads perdidos contam como
  tendo chegado apenas à primeira etapa. *(testado)*
- `taxa_passagem_pct` da etapa X = chegaram(X) / chegaram(X−1).

## Bloco 6 — Follow-up

- Fonte: tabelas locais do painel (`followup_runs`), não o Kommo.
- `enviados` = runs com status `enviado` ou `respondido`, com
  `executado_em` dentro do período.
- `taxa_entrega_pct` = enviados / (enviados + falhados).
- `taxa_resposta_pct` = respondidos em até **72h** após o envio / enviados.
- `reativados` (voltou a responder após 7+ dias parado) exige histórico de
  conversa que só existirá na Fase 3 — até lá o valor é `null` de
  propósito, nunca zero enganoso.

## Bloco 7 — Produtividade

- `por_responsavel`: leads do período agrupados pelo usuário responsável
  no Kommo (nome resolvido via `GET /users`, cacheado 24h).
- `tempo_medio_primeira_nota_horas`: média entre `created_at` do lead e a
  primeira **nota humana** (`note_type = common` e `created_by > 0` —
  robôs e integrações entram com id 0). Custa uma chamada de notas por
  lead, então **só é calculado no relatório gerado em segundo plano**
  (`modo_profundo`). No modo síncrono o valor é `null` com observação.

## Quando o relatório vira job

- Período **acima de 60 dias** (`kommo.report_job_threshold_days`) é
  gerado pela fila (`GerarRelatorioJob`), salvo em `report_snapshots` e o
  usuário é avisado por notificação no painel.
