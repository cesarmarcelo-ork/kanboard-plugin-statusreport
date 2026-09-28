# Relatório técnico — StatusReport 1.0.0

## 1. Objetivo

O StatusReport foi desenvolvido para Kanboard com o objetivo de separar **histórico operacional** de **Situação Atual**.

Nem todo comentário altera o estado da demanda. Portanto:

- comentários comuns permanecem apenas no histórico;
- comentários classificados como **Informe de Situação** permanecem no histórico e podem representar a situação vigente;
- somente a classificação explícita do usuário altera a Situação Atual.

A solução final também espelha a Situação Atual no campo **Descrição**, permitindo sua visualização diretamente no cartão do quadro Kanban.

---

## 2. Compatibilidade declarada

- Plugin: **StatusReport 1.0.0**
- Kanboard: **>= 1.2.50**, série 1.2.x
- Bancos: SQLite, MySQL/MariaDB, PostgreSQL
- Sem dependências externas
- Sem alteração do core

### Evidências de validação

1. Auditoria do código do Kanboard 1.2.50 antes da implementação.
2. Validação automatizada de referência durante o desenvolvimento em Kanboard 1.2.50 + PHP 8.3 + SQLite.
3. Validação real de interface na homelab em Kanboard 1.2.52, na qual foram identificados e corrigidos comportamentos que não eram exercitados pelo runner inicial.
4. Runner consolidado do pacote atual: **43 verificações**, a executar novamente em cada ambiente de homologação.

---

## 3. Achados da auditoria que determinaram a arquitetura

### 3.1 Eventos de comentário

`comment.create` e `comment.update` podem ser processados pelo `QueueManager`. Portanto, o plugin não depende do payload do evento para descobrir se o usuário marcou um comentário como Informe de Situação.

A classificação é resolvida no fluxo HTTP próprio do plugin.

### 3.2 Formulário nativo de comentários

O controller nativo persiste os valores do request sem aceitar com segurança um campo arbitrário adicional do plugin. Por isso o fluxo nativo não foi modificado.

O StatusReport possui ação própria:

```text
Adicionar Informe de Situação
```

O comentário comum continua 100% nativo.

### 3.3 Ausência de hooks granulares nos comentários

Não há hook suficiente em `comment/show` para inserir o selo diretamente. Foi necessário um único `setTemplateOverride('comment/show')`.

Esse override é um **wrapper**, não uma cópia do template do core.

### 3.4 ACL

Controllers desconhecidos podem receber papel padrão permissivo demais para a finalidade do plugin. O `StatusReportController` é registrado explicitamente como `PROJECT_MEMBER`.

### 3.5 Eventos via dispatcher

`Base::on()` não fornece o objeto de evento como necessário para o caso do plugin. Os listeners de atualização/exclusão utilizam `dispatcher->addListener()` diretamente.

### 3.6 Foreign keys

O modelo depende de `ON DELETE CASCADE`. O runner verifica o suporte efetivo no ambiente antes de criar recursos temporários.

---

## 4. Arquitetura final

### 4.1 Situação derivada

A tabela `status_reports` apenas classifica comentários.

```sql
status_reports (
    id,
    task_id,
    comment_id UNIQUE,
    user_id,
    date_creation
)
```

Relacionamentos:

```text
task_id    -> tasks(id)    ON DELETE CASCADE
comment_id -> comments(id) ON DELETE CASCADE
```

Regra da Situação Atual:

```text
último status_reports vivo da tarefa
ORDER BY status_reports.id DESC
+
texto atual do comentário relacionado
```

O texto nunca é duplicado na tabela `status_reports`.

### 4.2 Benefícios do modelo

- edição do comentário vigente reflete imediatamente no estado derivado;
- edição de Informe histórico não muda a vigência;
- exclusão do vigente faz o Informe anterior voltar automaticamente;
- nenhuma referência órfã permanece após exclusão;
- não há divergência entre metadata, tabela e comentário;
- a ordenação é determinística pelo `id` da classificação.

---

## 5. Criação e promoção

### 5.1 Novo Informe

Fluxo:

```text
POST controller do plugin
-> ACL / validação
-> criação do comentário
-> criação de status_reports
-> commit
-> sincronização da descrição
-> redirect
```

Comentário e classificação são gravados na mesma transação lógica.

### 5.2 Promoção de comentário existente

A ação de promoção é chamada por link GET com token CSRF reutilizável. Na validação real sobre Kanboard 1.2.52 foi confirmado que o método correto é o verificador específico de GET.

Fluxo final:

```text
checkReusableGETCSRFParam()
-> validar tarefa
-> validar comentário pertencente à tarefa
-> StatusReportModel::promote()
-> sincronizar espelho
```

### 5.3 Repromoção de Informe histórico

A idempotência de `create()` foi preservada.

A operação `promote()` é diferente: quando o comentário já é um Informe histórico, sua linha de classificação é removida e recriada dentro de transação para receber o maior `id` e voltar a ser vigente.

Se o comentário já é a Situação Atual, a promoção é idempotente e não altera a ordenação.

Isso resolve o caso:

```text
Informe A -> atual
Informe B -> atual
promover A novamente
=> A volta a ser atual
```

sem duplicar o comentário e sem violar `UNIQUE(comment_id)`.

---

## 6. Data de atualização

O modelo deriva:

```text
date_update = max(status_reports.date_creation,
                  comments.date_modification)
```

Essa regra é superior a utilizar sempre `date_modification` porque cobre dois casos:

1. um Informe é classificado e depois editado: a data da edição prevalece;
2. um comentário antigo é promovido hoje: a data da classificação atual prevalece sobre uma edição antiga.

Não houve alteração de schema.

---

## 7. Espelho na descrição e visualização no cartão

### 7.1 Política final

O espelho é **ativado por padrão** porque o objetivo operacional é permitir que a Situação Atual seja percebida no quadro sem abrir cada tarefa.

O painel interno da tarefa continua existindo como representação detalhada.

### 7.2 Preservação do conteúdo manual

O plugin altera somente a região sob seu controle. Todo conteúdo manual fora dessa região permanece intacto.

### 7.3 Marcadores internos

A primeira versão utilizava comentários HTML:

```text
<!-- STATUS_REPORT_START -->
<!-- STATUS_REPORT_END -->
```

Na validação real do cartão esses marcadores apareceram visualmente devido ao comportamento seguro do renderer Markdown das versões recentes do Kanboard.

A solução consolidada utiliza definições de referência Markdown:

```text
[status-report-start]: #
[status-report-end]: #
```

Esses delimitadores:

- permanecem no Markdown armazenado;
- não aparecem no cartão renderizado;
- permitem substituição segura da região;
- evitam depender de linhas tracejadas ou padrões que o usuário possa inserir manualmente.

O modelo continua reconhecendo os delimitadores HTML antigos para migração automática na próxima sincronização.

### 7.4 Formatação final

O título é gerado como:

```markdown
**Situação Atual**
```

Não utiliza `##`, portanto mantém o tamanho de fonte padrão do cartão e apenas aplica negrito.

---

## 8. Hooks e extensões

| Mecanismo | Finalidade |
|---|---|
| `projectAccessMap` | exige `PROJECT_MEMBER` |
| `template:task:show:before-description` | painel Situação Atual |
| `template:task:dropdown:after-add-comment` | ação Adicionar Informe |
| `template:task:sidebar:after-add-comment` | ação Adicionar Informe |
| `template:config:application` | configuração do espelho |
| `template:layout:top` | aviso de conflito |
| `template:layout:css` | CSS do plugin |
| `setTemplateOverride('comment/show')` | selo/ação no histórico |
| `comment.update` | sincronizar espelho após edição |
| `comment.delete` | recalcular espelho antes da exclusão |
| migrations por driver | criação da tabela |

### Correção do asset CSS

Durante o teste real no Kanboard 1.2.52, o registro do CSS como string provocou erro fatal em `HookHelper::asset()`.

O formato consolidado é:

```php
$this->hook->on(
    'template:layout:css',
    array('template' => 'plugins/StatusReport/Asset/css/status-report.css')
);
```

Essa correção foi incorporada ao pacote consolidado.

---

## 9. Override e modo restrito

O único override é `comment/show`.

Antes de registrá-lo, o plugin verifica o mapeamento efetivo. Uma segunda verificação ocorre em `app.bootstrap`, após o carregamento dos demais plugins.

Se houver colisão:

- o override não é instalado;
- o plugin não é desativado;
- o administrador recebe aviso;
- o plugin opera em **modo restrito**.

Indisponível:

- selo no histórico;
- ação contextual em cada comentário.

Mantido:

- painel;
- criação de Informe;
- promoção pelo modal;
- Situação Atual no cartão.

---

## 10. Segurança

### 10.1 ACL

`StatusReportController` exige `PROJECT_MEMBER`.

### 10.2 CSRF

- formulários usam CSRF nativo;
- promoção via GET utiliza `checkReusableGETCSRFParam()`;
- o token é gerado com `getReusableCSRFToken()`.

### 10.3 Whitelist

Somente campos esperados são enviados ao `CommentModel`.

### 10.4 Visibilidade

O parâmetro `visibility` é reduzido ao máximo permitido pelo papel de aplicação do usuário.

### 10.5 Vínculos

O controller valida:

- comentário existente;
- comentário pertencente à tarefa;
- tarefa dentro do contexto autorizado.

### 10.6 Saída

- escape com helpers do Kanboard;
- conteúdo do Informe renderizado pelo mecanismo Markdown nativo.

---

## 11. Concorrência

A criação de novo Informe grava comentário e classificação em transação.

A repromoção de Informe histórico também ocorre em transação.

A vigência utiliza o maior `status_reports.id`, sem depender da resolução do timestamp.

---

## 12. Exclusão

`ON DELETE CASCADE` remove automaticamente a classificação do comentário excluído.

O listener de `comment.delete` é chamado antes da remoção e recalcula o espelho excluindo logicamente o comentário que está saindo.

Resultado:

- Informe anterior volta a vigorar;
- cartão é atualizado;
- não há referência quebrada.

---

## 13. Runner consolidado

Arquivo:

```text
Test/run.php
```

O runner final contém **43 verificações**.

### Pré-flight

- CLI obrigatório;
- localização de `app/common.php`;
- mensagem orientativa quando executado apenas no host de volumes Docker;
- versão Kanboard >= 1.2.50;
- versão PHP;
- driver real;
- foreign keys efetivas.

### Cobertura

- comentário comum;
- primeiro Informe;
- comentário após Informe;
- nova Situação;
- edição vigente;
- data de atualização;
- edição histórica;
- descrição manual preservada;
- espelho;
- marcadores invisíveis;
- migração dos marcadores legados;
- exclusão/restauração;
- ACL;
- múltiplos ciclos;
- repromoção de Informe histórico;
- idempotência de `create()`;
- conflito de template da instalação.

### Cleanup

Todo recurso temporário é protegido por `try/finally`.

A configuração do espelho é restaurada fielmente:

- se já existia, o valor original é recolocado;
- se não existia, a opção temporariamente criada pelo runner é removida.

---

## 14. Validações manuais obrigatórias

Alguns aspectos dependem do ciclo HTTP e da renderização real e não devem ser considerados comprovados apenas pelo runner:

- carregamento completo do layout e asset CSS;
- login e página de tarefa sem fatal error;
- CSRF da promoção por link GET;
- selo no histórico;
- renderização dos marcadores invisíveis no cartão;
- comportamento visual com outros plugins instalados.

Esses itens compõem o `GUIA-TESTE.md`.

---

## 15. Limitações conhecidas

1. Não há comando específico de desclassificação de Informe.
2. Em fila assíncrona, edição pode refletir no espelho com atraso; o painel derivado não sofre essa limitação.
3. O selo/ação contextual depende de `comment/show` e pode entrar em modo restrito por conflito.
4. Compatibilidade declarada `>=1.2.50` não elimina a necessidade de executar o runner e a validação manual em cada versão efetivamente utilizada.

---

## 16. Integridade do core

O plugin não modifica arquivos do Kanboard.

Não utiliza:

- patch no core;
- cron;
- serviço externo;
- JavaScript próprio;
- metadata como fonte da situação;
- cópia do texto para a tabela de classificação.

---

## 17. Estado para teste

O pacote consolidado mantém **versão 1.0.0** e incorpora as correções identificadas durante a homologação real na homelab.

O estado recomendado é **candidato a homologação**, condicionado à execução do runner e do roteiro manual no Kanboard efetivamente instalado no ambiente de teste.
