# Validação da Fase 1 — números conferidos contra o Kommo

Data: 19/08/2026 · Conta: ottoboni.kommo.com (token real, leitura apenas)

## Critério 1 do briefing — "Duda + mês passado" bate com o Kommo

Relatório do painel para **Dr. Eduardo (pipeline 6441483), julho/2026**:

- Total do mês: **128 leads** (junho: 35 → variação +265,7%)
- Origem: Orgânico-IA 78 (60,9%) · Não informado 50 (39,1%)
- Temperatura: Quente 48 · Morno 25 · Frio 5 · Não informado 50
- Soma das fatias = total ✓ · Soma dos dias = total ✓

Conferência independente (chamadas cruas à API, por dia, fora do
repositório e do cache):

| Dia | Painel | API direta | |
|---|---|---|---|
| 05/07 | 0 | 0 | ✓ |
| 15/07 | 3 | 3 | ✓ |
| 25/07 | 0 | 0 | ✓ |
| 28/07 (3º mais movimentado) | 11 | 11 | ✓ |
| 30/07 (2º mais movimentado) | 15 | 15 | ✓ |
| 31/07 (mais movimentado) | 47 | 47 | ✓ |

Luna (9219740), julho/2026: painel 34 = API direta 34 ✓.

## Achados sobre os dados reais

1. **Os campos Procedimento, Urgência e Score NÃO existem na conta.**
   Os campos de lead reais são: `Origem do lead` (1891666), `Temperatura`
   (1891668), `@ Instagram` (1891670) e `Campanha (Ads)` (1891672) — todos
   resolvidos por nome pelo painel. Os três campos que o briefing prevê
   precisam ser criados no Kommo (ou preenchidos pelas agentes) para que
   o ranking de procedimentos e a ordenação por score tenham dado.
   Enquanto isso o painel mostra "Não informado", sem quebrar nada.
2. **A Luna entrou no ar em agosto/2026**: julho tem 34 leads todos
   manuais; até 19/08 são 46 leads, sendo 27 gerados pela agente (com
   origem, temperatura e @ preenchidos). A Duda tem 67 de 94 em agosto.
3. Alguns leads `IG @…` trazem o **ID numérico** do usuário do Instagram
   em vez do @ legível (ex.: `IG @1295046725845050`) — é como a agente
   registra quando o username não está disponível. O painel exibe como
   está.
4. Único valor de origem visto até agora: `Orgânico-IA` (145 leads
   Duda jul+ago). `Tráfego pago` ainda não apareceu — o filtro existe e
   está pronto.
5. A conta tem 11 pipelines; o painel enxerga apenas os dois do briefing
   (Dr Eduardo 6441483 e Dra Vanessa Ottoboni 9219740), por construção.
