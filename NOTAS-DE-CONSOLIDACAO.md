# Notas de consolidação — StatusReport 1.0.0

Este documento registra os ajustes incorporados ao pacote consolidado após os testes reais realizados na homelab. **A versão permanece 1.0.0.**

## Correções incorporadas

1. **Registro do CSS do plugin**
   - corrigido o formato do hook `template:layout:css` para o formato de asset esperado pelo Kanboard;
   - elimina o fatal error observado no carregamento real do Kanboard 1.2.52.

2. **Promoção por link e CSRF**
   - `StatusReportController::promote()` passou a utilizar `checkReusableGETCSRFParam()`;
   - elimina o retorno incorreto de “Acesso negado” ao promover um comentário pela interface.

3. **Repromoção de Informe histórico**
   - criado `StatusReportModel::promote()`;
   - um Informe de Situação histórico pode voltar a ser a Situação Atual;
   - `create()` continua idempotente;
   - não há duplicação do comentário nem alteração de schema.

4. **Espelho no cartão ativado por padrão**
   - a Situação Atual passa a ser apresentada na descrição por padrão;
   - o objetivo é permitir leitura da situação diretamente no quadro.

5. **Preservação da descrição manual**
   - o plugin continua alterando exclusivamente sua região delimitada;
   - informações manuais antes ou depois da Situação Atual permanecem intactas.

6. **Marcadores internos invisíveis**
   - os antigos comentários HTML foram substituídos por definições de referência Markdown;
   - os marcadores deixam de aparecer no cartão;
   - os marcadores antigos continuam reconhecidos para migração automática.

7. **Formatação da Situação Atual**
   - removido heading Markdown `##`;
   - título passa a ser `**Situação Atual**`, mantendo a fonte padrão do cartão somente em negrito;
   - removidas linhas decorativas que interferiam no parser Markdown.

8. **Runner de teste**
   - mensagem clara quando executado fora do runtime real do Kanboard;
   - preservação fiel da configuração anterior do espelho;
   - cobertura de repromoção de Informe histórico;
   - cobertura dos marcadores invisíveis e migração dos marcadores antigos;
   - pacote consolidado contém 43 verificações no runner.

## Componentes preservados sem mudança conceitual

- versão `1.0.0`;
- tabela `status_reports`;
- `UNIQUE(comment_id)`;
- `ON DELETE CASCADE`;
- ordenação por `status_reports.id DESC`;
- comentário como fonte única da verdade;
- painel Situação Atual;
- fluxo nativo de comentários comuns;
- único override `comment/show` em formato wrapper;
- modo restrito em caso de conflito de template;
- ACL `PROJECT_MEMBER`.

## Estado do pacote

O pacote é considerado **candidato consolidado para teste institucional**. A homologação  deve seguir `GUIA-TESTE.md` e registrar a versão real do Kanboard, PHP, banco e plugins instalados.
