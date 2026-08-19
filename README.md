# Painel Ottoboni

Painel web para a clínica Ottoboni acompanhar os atendimentos feitos pelas
agentes de IA **Duda** (Dr. Eduardo) e **Luna** (Dra. Vanessa) no Kommo, e
disparar follow-ups para os leads.

- **Stack:** Laravel 12 · Filament v4 · PHP 8.3 · MySQL 8 · Pest · Larastan
- **Fonte dos dados:** API do Kommo ao vivo (não há espelho do CRM no banco)
- O briefing completo do projeto está na raiz da conversa/documentação
  interna; as regras dos números dos relatórios estão em
  [docs/metricas.md](docs/metricas.md).

## Estado atual — Fase 1 (leitura) pronta

- `KommoClient` com throttle central (5 req/s), retry com backoff em
  429/5xx, bloqueio total em 403 e log de todas as chamadas.
- Campos personalizados resolvidos **por nome** via
  `GET /leads/custom_fields` (cache 24h) — nunca por ID chutado.
- Tela **Atendimentos** com todos os filtros do briefing; filtros de campo
  personalizado aplicados em memória sobre o cache (trocar filtro não
  dispara chamadas novas).
- **Ficha do atendimento** com notas sob demanda, tarefas e contato.
- **Relatórios** com os 7 blocos, export CSV/XLSX por bloco e PDF completo;
  período acima de 60 dias vira job na fila com notificação.
- **Dashboard** com indicadores, leads por dia e origem.
- Papéis: `admin`, `gestor`, `recepcao` (escopo por médico aplicado no
  filtro de pipeline — comprovado por teste de URL direta).
- 2FA por aplicativo autenticador, auditoria de exportações e fichas,
  retenção de logs de 90 dias.

Fases 2 (follow-up manual) e 3 (IA e canais) ainda não começaram — as
tabelas já existem, o motor não.

## Rodando localmente (WAMP)

```powershell
# dependências
php composer.phar install

# banco (MySQL do WAMP, engine InnoDB forçada no config)
php artisan migrate --seed

# assets do Filament já vêm publicados; suba o servidor:
php artisan serve
```

Acesse `http://localhost:8000/painel`. O seed cria o usuário
`admin@ottoboni.local` (senha em `SEED_ADMIN_PASSWORD` no `.env`, padrão
`trocar-esta-senha` — **troque no primeiro login**).

### .env obrigatório

```env
KOMMO_SUBDOMAIN=ottoboni
KOMMO_TOKEN=            # token de longa duração — NUNCA commitar
DUDA_PIPELINE_ID=6441483
LUNA_PIPELINE_ID=9219740
```

Sem `KOMMO_TOKEN`, o painel abre normalmente e mostra o erro amigável
"KOMMO_TOKEN não configurado" nas telas que consultam a API. Use
**Configurações → Testar conexão Kommo** para validar.

### Fila e agendador

Em produção rode os dois processos:

```bash
php artisan queue:work        # jobs de relatório (e follow-up na Fase 2)
php artisan schedule:work     # retenção de logs; followup:dispatch na Fase 2
```

No Windows/WAMP de desenvolvimento, `php artisan queue:work` num terminal
separado é suficiente (Horizon exige pcntl e não roda no Windows — usar
apenas em produção Linux, se desejado).

## Testes e análise estática

```bash
vendor/bin/pest
vendor/bin/phpstan analyse --memory-limit=1G
```

A API do Kommo é **sempre mockada** nos testes (fixtures em
`tests/Fixtures/kommo/`). Nada bate em produção.

## Regras do projeto (resumo)

- Nenhum `Http::get()` para o Kommo fora do `KommoClient`.
- Toda tela que mostra dado em cache exibe "dados de HH:MM" e tem botão
  "Atualizar agora".
- Rate limit do Kommo: 7 req/s (trabalhamos com 5). 403 = possível
  bloqueio de IP: o cliente para tudo e loga em nível crítico.
- Dados de pacientes são dado de saúde (LGPD): nada de PII em log,
  exportação restrita a admin/gestor e auditada.
- Perguntar antes de: criar tabela espelhando o Kommo, mudar o contrato do
  webhook, ou enviar mensagem real em ambiente de desenvolvimento.
