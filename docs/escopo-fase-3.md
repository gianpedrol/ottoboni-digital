# Escopo — Fase 3: gestão da clínica

Rascunho de 10/09/2026. Nesta fase os dados são **do Kommo atual** ou
**mockados** (seed local). Toda tela mostra de onde vem cada número:

| Selo | Origem |
|---|---|
| `Kommo` | API ao vivo (com cache e "dados de HH:MM", como hoje) |
| `Painel` | cadastrado no próprio painel |
| `Demonstração` | seed fictício — faixa amarela na tela, nunca misturado com dado real sem o selo |

Módulos novos: **Indicadores**, **Pacientes**, **Agenda**, **Prontuário**,
**Financeiro** e **Automações**.

> **Protótipo (10/09/2026):** todos os módulos estão sendo montados como
> protótipo navegável para apresentar aos médicos (branch
> `prototipo/fase-3`). Dado que ainda não existe no Kommo é fictício e marcado
> como Demonstração; nenhuma tela grava no Kommo, só simula.

---

## 1. O que o Kommo real tem hoje (lido em 10/09/2026)

### Pipelines relevantes

| Pipeline | ID | Uso |
|---|---|---|
| Dr Eduardo | 6441483 | já no painel (Duda) |
| Dra Vanessa Ottoboni | 9219740 | já no painel (Luna) |
| **Fluxo cirurgias** | 11380984 | é o "board de cirurgias" |
| Dra Jéssica Hubner | 8795248 | fora do painel hoje |
| Dr Paulo | 6456383 | fora do painel hoje |
| Dermatologia e Estética - GERAL | 6367607 | fora do painel hoje |
| Funil de vendas | 6366267 | fora do painel hoje |

Os outros 4 (fornecedores, representantes, colaboradores, lead sem
agendamento) não são funil de paciente.

### Etapas equivalentes em cada pipeline

Os nomes mudam de pipeline para pipeline, então **nada pode ser casado por
nome**. A base da fase é um mapa de "etapa canônica" → `status_id` por
pipeline, editável em Configurações:

| Etapa canônica | Dr Eduardo | Dra Vanessa |
|---|---|---|
| Novo | 54917471 Etapa de leads de entrada | 71591204 Etapa de leads de entrada |
| Em atendimento | 54917475 EM ATENDIMENTO | 71591208 EM ATENDIMENTO |
| FUP 1 | 84709188 FOLLOW 24 HRS | 110706659 FU1 · SEM RESPOSTA |
| FUP 2 | 84709192 FOLLOW UP 5 DIAS | 110706663 FU2 · AGEND. NÃO CONCLUÍDO |
| FUP 3 | 84710072 FOLLOW 1 / Ñ AGEND | 110706667 FU3 · ORÇAMENTO ENVIADO |
| Não agendou | 69609380 NÃO AGENDOU | 71591216 NÃO AGENDOU / FOLLOW UP |
| Aguardando sinal | 75912472 AGUARDANDO SINAL · 85174916 agendou e não pagou sinal | — |
| Agendou consulta | 54917479 AGENDOU CONSULTA/PROCEDIMENTO | 71591324 1 AGENDOU CONSULTA · 71591328 1 AGEND. CONS. ONLINE |
| Realizou consulta | 56708683 realizou consulta | 73758392 REALIZOU 1ª CONSULTA |
| Orçamento / negociação | 54917483 EM Negociação | 71591332 CONSULTORIA (?) |
| Agendou cirurgia / procedimento | 69228540 AGENDOU CIRURGIA | 71591336 AGENDOU PROCEDIMENTO |
| Contratado | **não existe** — ver decisão D2 | **não existe** |
| Ganho / perdido | 142 / 143 | 142 / 143 |

### Campos que já existem e servem para a fase

- **Lead:** `Próxima consulta` (data/hora), `Comparecimento` (lista),
  `Status do sinal` (lista), `Valor da proposta` (número), `Valor total`,
  `Data da assinatura`, `Data da cirurgia`, `Cirurgia`, `Hospital`,
  `Anestesista`, `Prótese`, `Link Contrato`, `Médico responsável`,
  `Procedimento de interesse`, `CPF`, `RG` e os campos UTM.
- **Contato:** `Data da consulta`, `Data da cirurgia:`, `Indicação:`.

**Problema:** `Valor total`, `Data da assinatura` e `Data da cirurgia` (no
lead) são campos de **texto livre**, então não dá para somar nem filtrar por
data com confiança. Ver decisão D4.

### Tags

São mais de 200 tags, com duplicatas (`botox` / `BOTOX` / `botox `,
`drajessica` / `drajéssica`) e lixo (`a`, `d`, `t=r`). As que importam para
as automações já existem: `Contato1`, `Contato2`, `Contato3` (FUP),
`SINAL`, `PAGAMENTOOK`, `PAGAMENTO✅`, `CONTRATO`, `RESGATECONSULTA`.

### Webhooks

Só um cadastrado, para `n8n.bravon.app` (eventos `add_talk`, `add_message`),
e está **desativado**. Nenhum webhook avisa hoje sobre mudança de etapa ou de
campo.

---

## 1.1 Kommo alvo (reestruturação)

O Kommo vai ser ajustado para caber no painel, e não o contrário. A meta é
que **todo pipeline de médico tenha o mesmo esqueleto**: mesmas etapas,
mesmos campos, mesmas tags de automação. O mapa de etapas continua existindo
(por ID, nunca por nome), mas fica igual em todos os médicos.

### Regra de ouro da migração

**Renomear e reordenar, nunca apagar e recriar.** Renomear uma etapa
mantém o `status_id`. Apagar e recriar gera outro ID e quebra, sem aviso:

- a configuração das agentes Duda e Luna, que movem leads e preenchem campos;
- os gatilhos das réguas de follow-up da Fase 2, que guardam `status_id`;
- as automações nativas do Kommo presas àquela etapa;
- o histórico: `/events` antigos apontam para IDs que deixam de existir, e
  os números de meses anteriores ficam sem etapa.

Quando uma etapa precisar mesmo sumir, primeiro os leads dela são movidos
em lote (script do painel, com simulação antes). O ID antigo continua no
mapa como "legado" apontando para a etapa canônica, para o histórico não
se perder.

### Etapas padrão do funil do médico

| # | Etapa | Observação |
|---|---|---|
| 1 | Entrada | a do Kommo, já existe |
| 2 | Em atendimento | agente ou recepção conversando |
| 3 | FUP 1 | tag `Contato1` (A1) |
| 4 | FUP 2 | tag `Contato2` (A1) |
| 5 | FUP 3 | tag `Contato3` (A1) |
| 6 | Aguardando sinal | só para quem cobra sinal; nos outros fica sem uso |
| 7 | Consulta agendada | presencial ou online vira o campo `Tipo de consulta`, não uma etapa |
| 8 | Consulta realizada | |
| 9 | Negociação | orçamento enviado |
| 142 | **Contratado** | a etapa "Venda ganha" renomeada (o ID 142 continua) |
| 143 | Perdido | com o motivo de perda nativo do Kommo |

Resolve a D2: "Contratado" passa a ser o ganho do pipeline, e a taxa de
ganho dos relatórios vira taxa de contratação sem mudar código.

O que sai do funil de venda:

- **Instagram**: é origem, vira o campo `Origem do lead`.
- **Consulta de acompanhamento** (Dra Vanessa): paciente que já contratou;
  vai para um pipeline próprio de "Acompanhamento".
- **Venda futura**: vai para o pipeline "Lead sem agendamento", que já
  existe.
- **Agendou cirurgia / procedimento**: passa a acontecer no Fluxo cirurgias,
  criado pela A6.

### Campos

| Ação | Campo | Tipo |
|---|---|---|
| criar (substitui o de texto) | `Valor do contrato` | número |
| criar (substitui o de texto) | `Data da assinatura` | data |
| criar (substitui o de texto) | `Data da cirurgia` | data |
| criar | `Tipo de consulta` | lista: Presencial, Online |
| manter | `Próxima consulta`, `Comparecimento`, `Status do sinal`, `Valor da proposta`, `Procedimento de interesse`, `Médico responsável`, `Origem do lead`, `Temperatura` | — |
| padronizar opções | `Comparecimento` (Compareceu / Faltou / Remarcou) e `Status do sinal` (Pendente / Pago) | lista |

Os campos de texto antigos ficam só leitura até um script copiar os valores
convertidos. Os que não convertem vão para uma lista de revisão manual.

### Tags

Com etapa e campo padronizados, a maioria das tags perde a função:

- **procedimento** (`botox`, `mastopexia`, `silicone`…) vira
  `Procedimento de interesse`;
- **médico** (`drajessica`, `DrEduardo`…) vira `Médico responsável`;
- **indicação** (`indThais`, `IndPri`…) vira o campo `Indicação:` do contato;
- **ficam só as tags de operação**, controladas pelas automações:
  `Contato1/2/3`, `SINAL`, `PAGAMENTOOK`, `CONTRATO`, `RESGATECONSULTA`.

A limpeza roda pelo painel: relatório de uso de cada tag, depois conversão
tag → campo com simulação, e só então remoção.

### Checklist antes de mexer

1. Exportar o backup do Kommo (leads, contatos, campos).
2. Levantar as automações nativas e os Salesbots de cada pipeline (D5).
3. Levantar o que as agentes Duda e Luna escrevem: etapas, campos e tags.
   Os prompts e a configuração delas mudam junto.
4. Fazer a migração num horário de pouco movimento, um pipeline por vez,
   começando pelo Dr Eduardo.
5. Conferir no painel (funil e contagem por etapa) antes e depois.
6. Treinar a recepção na nova ordem das etapas.

---

## 2. Indicadores (dashboard ampliado)

Hoje o painel olha só o **estado atual** do lead. Para contar "quantos
agendaram no mês" é preciso o **histórico de mudança de etapa**
(`GET /events`, tipo `lead_status_changed`). Isso também resolve a limitação
do funil descrita em `docs/metricas.md`, em que lead perdido conta como tendo
chegado só à primeira etapa.

| Indicador | Regra | Origem |
|---|---|---|
| Leads novos | criados no período (já existe) | Kommo |
| Agendamentos feitos | leads que **entraram** em "Agendou consulta" no período | Kommo (events) |
| Consultas na agenda | `Próxima consulta` dentro do período | Kommo |
| Consultas realizadas | entraram em "Realizou consulta" ou `Comparecimento` = compareceu | Kommo |
| Taxa de agendamento | agendamentos ÷ leads novos | Kommo |
| Comparecimento / no-show | realizadas ÷ agendadas com data já passada | Kommo |
| Sinal pendente | leads em "Aguardando sinal" + valor | Kommo |
| Orçamentos em aberto | qtd e soma de `Valor da proposta` em "Orçamento / negociação" | Kommo |
| Contratos fechados | ver D2 · qtd, soma e ticket médio | Kommo |
| Cirurgias agendadas | cards do Fluxo cirurgias por `Data da cirurgia` (próximos 30/60/90 dias) | Kommo |
| Tempo médio lead → consulta e consulta → contrato | a partir do histórico | Kommo (events) |
| Conversão por origem / campanha | UTM + `Origem do lead` + `Campanha (Ads)` | Kommo |
| Receita do mês, a receber, inadimplência | módulo Financeiro | Demonstração |
| Automações executadas / com falha | módulo Automações | Painel |

Telas: **Visão geral** (cards com comparação ao período anterior),
**Funil** (conversão entre etapas canônicas, por médico) e **Agenda**
(próximas consultas e cirurgias, lida do Kommo).

Custo de API: o `/events` é paginado e caro. Ele entra no mesmo esquema de
cache e job em segundo plano dos relatórios longos (Fase 1).

---

## 3. Pacientes

### Cadastro

Campos: nome, telefone (obrigatório), e-mail, CPF, data de nascimento,
médico, procedimento de interesse, origem, indicação e observações.

Ao salvar:

1. Procura o contato no Kommo pelo telefone (`GET /contacts?query=`) para não
   duplicar.
2. Se achar, **vincula**; se não, cria o contato (`POST /contacts`).
3. Opcional ("Abrir atendimento"): cria um lead no pipeline do médico, na
   etapa de entrada, ligado ao contato.
4. Se o Kommo estiver fora do ar, o paciente fica salvo com "pendente de
   sincronizar" e um job tenta de novo. Nenhum cadastro se perde.

### Ficha do paciente

Dados cadastrais, todos os atendimentos dele no Kommo (qualquer pipeline),
consultas, cirurgias (Fluxo cirurgias), financeiro (recebíveis e pagamentos)
e linha do tempo.

### LGPD

CPF e RG ficam cifrados no banco (cast `encrypted`). A ficha é auditada (a
auditoria já existe), só admin e gestor exportam, e a recepção vê só os
pacientes dos médicos a que tem acesso. Observação: o Kommo já guarda CPF e
RG em texto nos leads, então vale avisar a clínica.

---

## 3.1 Agenda própria

A agenda passa a ser do painel, e o Kommo recebe o reflexo dela:

- **Semana por médico** (seg–sáb, blocos de 30 min), cor por status e tipo
  (consulta, retorno, online, procedimento, cirurgia). Mais uma **visão do
  dia** para a recepção: confirmar, chegou, faltou, cancelar, remarcar.
- **Conflito de horário** do mesmo médico é bloqueado.
- **Serviço da tabela de preços** define a duração e o valor.
- **Reflexo no Kommo:**
  - agendar preenche `Próxima consulta` e move para "Consulta agendada";
  - marcar chegou ou faltou preenche `Comparecimento`.
  - Esses campos disparam as automações A2, A3 e A5. Ou seja, a recepção
    trabalha na agenda e o funil do Kommo se atualiza sozinho.

## 3.2 Prontuário clínico

Os registros ficam na ficha do paciente, em linha do tempo:

- **Anamnese estruturada:** queixa, história, antecedentes, alergias,
  medicações e IMC.
- **Evolução**, **prescrição**, **pedido de exame** e **atestado**.
- **Fotos antes/depois**, com ângulo e legenda.

Regras:

- Conteúdo cifrado no banco; cada abertura é auditada; só o médico, admin e
  gestor acessam.
- Registro assinado vira somente leitura.
- **Para produção (não é o protótipo):** o prontuário eletrônico sem papel
  exige assinatura com certificado **ICP-Brasil** e guarda por no mínimo **20
  anos** (Lei 13.787/2018 e resoluções do CFM). Isso é custo e
  responsabilidade legal, e precisa estar no contrato: quem guarda, backup,
  e o que acontece se a clínica sair do sistema.

---

## 4. Financeiro (demonstração nesta fase)

A única coisa real que vem do Kommo é `Valor da proposta` / `Valor total`.
Todo o resto é seed fictício até decidir a fonte real (D6).

- **Tabela de serviços por médico:** consulta, retorno e procedimentos, com
  valor, duração e % de repasse ao médico.
- **Contas a receber:** por paciente, com parcelas, forma de pagamento
  (PIX, cartão, boleto, dinheiro), vencimento e status. Um contrato fechado
  gera o recebível (automação A6).
- **Contas a pagar:** fornecedor, categoria, vencimento, recorrência e status.
- **Extrato bancário:** contas bancárias, importação de OFX/CSV e conciliação
  manual (ligar o lançamento a uma conta a pagar ou a receber).
- **Relatórios:** fluxo de caixa (previsto × realizado), receita por médico e
  por procedimento, repasses e inadimplência.
- **Acesso:** papel novo `financeiro`, mais o admin. A recepção não vê.

Fora do escopo: nota fiscal, integração bancária automática (Open Finance),
emissão de boleto ou PIX e contabilidade.

---

## 5. Automações

### Por que ficam no painel e não no Kommo

As automações nativas do Kommo (Digital Pipeline) **não podem ser criadas
nem editadas pela API**. Para "gerenciar automações pelo painel", o motor
precisa morar no painel:

```
Kommo ── webhook (mudou etapa / campo / tag) ──▶ painel
  ▲                                              │ fila
  │                                              ▼
  └──── ação via KommoClient ◀── regra: gatilho + condições + ações
                                                 │
                                          log de execução
```

- O webhook é cadastrado pelo painel (`POST /webhooks`) com os eventos
  `status_lead`, `update_lead` e `add_lead`, e assinatura validada.
- **Anti-loop:** o que o próprio painel altera também dispara webhook. Cada
  execução guarda `lead + regra + versão do lead`, e eventos gerados pela
  integração são ignorados.
- **Modo "só registrar":** toda regra nasce registrando o que *faria*, sem
  escrever no Kommo. Liga de verdade depois de conferida.
- **Simulador:** roda a regra contra um lead real sem executar, como o
  simulador de régua da Fase 2.
- O motor da Fase 2 é reaproveitado: janela de horário, cancelamento e o log
  no mesmo formato das execuções de follow-up.

**Gatilhos:** entrou na etapa X · campo mudou (`Comparecimento`,
`Status do sinal`, `Próxima consulta`, `Data da assinatura`) · tag
adicionada/removida · lead parado há X horas · lead criado.

**Ações:** mover de etapa · adicionar/remover tag · preencher campo · criar
tarefa · criar card em outro pipeline (copiando contato e campos) ·
iniciar/cancelar régua de follow-up · disparar Salesbot · criar recebível
no Financeiro.

### Catálogo inicial (7 regras)

| # | Nome | Quando | Então |
|---|---|---|---|
| A1 | **Tags do FUP** | entrou em FUP 1 / 2 / 3 | põe `Contato1` / `Contato2` / `Contato3`, tira as outras tags de FUP e inicia a régua de follow-up da etapa |
| A2 | **Agendou no FUP** | lead em FUP 1–3 ou "Não agendou" **e** `Próxima consulta` preenchida | move para "Agendou consulta", tira as tags de FUP e cancela os follow-ups pendentes |
| A3 | **Consulta realizada** | `Comparecimento` = compareceu | move para "Realizou consulta" |
| A4 | **Orçamento enviado** | lead em "Realizou consulta" **e** `Valor da proposta` preenchido | move para "Orçamento / negociação" |
| A5 | **No-show** | `Comparecimento` = faltou | move para "Não agendou", põe `RESGATECONSULTA` e inicia a régua de resgate |
| A6 | **Contrato assinado** | `Data da assinatura` preenchida (ou tag `CONTRATO`) | move para "Contratado" (D2), **cria o card no Fluxo cirurgias** (etapa de entrada, mesmo contato, copiando Cirurgia, Data da cirurgia, Hospital, Anestesista, Prótese e Valor total), cria a tarefa "Iniciar pré-operatório" e gera o recebível |
| A7 | **Sinal pago** (Dr Eduardo) | `Status do sinal` = pago | sai de "Aguardando sinal" para "Agendou consulta" e põe `PAGAMENTOOK` |

A3 + A4 atendem o pedido "consulta realizada → orçamento/negociação" sem
pular a etapa "realizou consulta", que existe nos dois pipelines. Se a
clínica preferir ir direto, basta juntar as duas regras.

---

## 6. Banco de dados (tabelas novas)

`stage_mappings` · `patients` · `services` · `receivables` +
`receivable_installments` · `payables` · `bank_accounts` ·
`bank_transactions` · `automation_rules` · `automation_runs` ·
`kommo_webhook_events`

Seeders de demonstração para todo o Financeiro e para os pacientes de teste.

---

## 7. Ordem de entrega

| Etapa | Conteúdo | Por que nessa ordem |
|---|---|---|
| **3.0 — Kommo alvo** | aprovar a seção 1.1 com a clínica; scripts de migração (mover leads, converter campos, tags → campos) com simulação; migrar pipeline por pipeline | tudo depois lê essa estrutura; mudar depois de ter automação ligada é o dobro do trabalho |
| **3A — Base** | mapa de etapas canônicas, leitura do histórico (`/events`), dashboard ampliado, funil e agenda | usa dado real e dá valor imediato; tudo depois depende do mapa de etapas |
| **3B — Automações** | recebimento de webhook, motor de regras, A1–A7 em "só registrar" e depois ligadas uma a uma | maior ganho operacional; exige servidor com URL pública |
| **3C — Pacientes** | cadastro, sincronização com o Kommo e ficha | depende do mapa de etapas (abrir atendimento) |
| **3D — Financeiro** | telas com dados de demonstração | só vira valor real depois de D6; por isso fica por último |

---

## 8. Decisões pendentes

| # | Decisão | Recomendação |
|---|---|---|
| D1 | Pacientes em tabela local? O README pede para perguntar antes de espelhar o Kommo. | **Sim, com o mínimo.** O painel é a origem do cadastro e manda para o Kommo. Não é espelho de todos os contatos. O Financeiro precisa dessa âncora. |
| D2 | "Contratado" não existe como etapa. | **Resolvida na 1.1:** renomear "Venda ganha" (142) para "Contratado" + card no Fluxo cirurgias. |
| D3 | O que conta como "agendou" no FUP? | Campo **`Próxima consulta` preenchido**. Hoje não há outro sinal confiável. |
| D4 | `Valor total`, `Data da assinatura` e `Data da cirurgia` são texto. | **Resolvida na 1.1:** campos novos de número/data, com script de conversão. |
| D5 | Automações nativas que já existem no Kommo. | Levantar pelo Kommo com a clínica **antes** da 3B. Se o Digital Pipeline já move essas etapas, as ações vão duplicar. |
| D6 | Fonte real do Financeiro. A clínica já usa algum sistema (Feegow, Clinicorp, iClinic, Conta Azul…)? | Se já usa, **integrar em vez de construir**. |
| D7 | Quais médicos entram? | Começar por Eduardo e Vanessa. O mapa de etapas deixa Jéssica, Paulo e Dermatologia só como configuração. |
| D8 | Limpeza das tags. | **Resolvida na 1.1:** tag de procedimento, médico e indicação vira campo; ficam só as tags de operação. |
| D9 | Quem executa a reestruturação do Kommo e quem aprova o "Kommo alvo"? | Uma pessoa da clínica aprova a 1.1 por escrito antes da 3.0. A execução pelo painel (scripts) fica com a Pedrol. |

## Fora do escopo desta fase

Nota fiscal, integração bancária automática, assinatura ICP-Brasil (entra
só na versão de produção do prontuário), app mobile e envio de mensagens
reais em ambiente de desenvolvimento.
