# Sistema de Gestão Clínica Multiclínicas

Sistema em Laravel 13 + Filament 5 para gestão de clínicas multiprofissionais com isolamento por clínica, agenda interna, auditoria e histórico completo de atendimento.

## Stack

- PHP 8.3+
- Laravel 13
- Filament 5
- MySQL 8+

## Instalação

1. Instale dependências:

```bash
composer install
npm install
```

2. Configure ambiente:

```bash
cp .env.example .env
php artisan key:generate
```

3. Ajuste o banco MySQL no `.env`.

4. Execute migrations e seeders:

```bash
php artisan migrate --seed
```

5. Rode a aplicação:

```bash
composer run dev
```

## Acessos de seed

- `admin@clinica.test` — Administrador do sistema, não pertence a nenhuma clínica fixa. Após o login, use a página "Selecionar clínica" para escolher qual clínica visualizar/operar.
- `gerente@clinica.test` — Gerente, sempre vinculado à Clínica Central, com acesso completo a ela.
- Senha padrão: `password`

## Perfis de acesso

- **Administrador**: não pertence a nenhuma clínica fixa. Seleciona qual clínica quer visualizar/operar (a escolha vale para a sessão atual). Enquanto nenhuma clínica estiver selecionada, não vê nem cria dados operacionais. É o único perfil que cadastra novas clínicas. Uma vez com uma clínica selecionada, tem acesso completo a ela, igual ao Gerente.
- **Gerente**: sempre vinculado a uma única clínica (definida no cadastro do usuário) e tem acesso completo a tudo dentro dela — pacientes, agenda, cadastros, procedimentos, outros usuários da própria clínica etc.

## Módulos entregues

- Clínicas
- Usuários internos com perfis `administrador` e `gerente`
- Pacientes
- Profissionais
- Especialidades
- Salas
- Disponibilidades
- Procedimentos
- Agendamentos
- Registros de atendimento
- Página `Agendar consulta`
- Página `Agenda por sala`
- Auditoria e histórico de situação

## Regras principais

- Todo registro de negócio possui `clinica_id`.
- Nenhum item é compartilhado entre clínicas.
- Conflitos de profissional e sala são validados no serviço antes da gravação.
- Remarcação preserva o agendamento original e cria um novo vínculo.
- Atendimentos realizados geram registro próprio e associação com procedimentos.

## Diagrama textual do banco

```text
clinicas
  1:N usuarios
  1:N pacientes
  1:N profissionais
  1:N especialidades
  1:N salas
  1:N disponibilidades_profissionais
  1:N procedimentos
  1:N agendamentos
  1:N registros_atendimentos
  1:N auditorias

profissionais N:N especialidades -> especialidade_profissional
profissionais N:N salas -> profissional_sala

pacientes 1:N agendamentos
profissionais 1:N agendamentos
especialidades 1:N agendamentos
salas 1:N agendamentos

agendamentos 1:1 registros_atendimentos
agendamentos N:N procedimentos -> agendamento_procedimento
agendamentos 1:N historicos_situacoes_agendamentos
agendamentos 1:N agendamentos (remarcações via agendamento_anterior_id)
```

## Testes

Os testes de regra de negócio estão em `tests/Feature/Agendamentos/ServicosAgendamentoTest.php` e cobrem:

- criação sem conflito
- conflito de profissional
- conflito de sala
- capacidade simultânea
- remarcação
- cancelamento
- finalização com procedimentos
- horário fora da disponibilidade
- horário durante intervalo
- dupla tentativa do mesmo horário
