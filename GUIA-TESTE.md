# Guia de implantação e teste — StatusReport 1.0.0

## 1. Objetivo

Este roteiro deve ser utilizado para testar o plugin **StatusReport 1.0.0** no ambiente Kanboard sem presumir que a versão, PHP, banco ou conjunto de plugins sejam iguais aos da referência de desenvolvimento.

O teste deve confirmar dois níveis:

1. **compatibilidade técnica** do ambiente;
2. **comportamento funcional e visual** pela interface do Kanboard.

---

## 2. Antes da instalação

Registrar:

```text
Kanboard: ______________________
PHP: __________________________
Banco/driver: _________________
Ambiente: _____________________
Data: _________________________
Responsável pelo teste: _______
```

Registrar também a lista de plugins já instalados.

O StatusReport declara compatibilidade mínima com:

```text
Kanboard >= 1.2.50
```

Se a versão for inferior, não prosseguir com a homologação deste pacote sem revisão específica.

---

## 3. Estrutura esperada do pacote

Após descompactar:

```text
plugins/
└── StatusReport/
    ├── Plugin.php
    ├── Controller/
    ├── Core/
    ├── Helper/
    ├── Locale/
    ├── Model/
    ├── Schema/
    ├── Template/
    ├── Test/
    ├── Asset/
    ├── README.md
    ├── RELATORIO-TECNICO.md
    ├── GUIA-TESTE.md
    └── LICENSE
```

Verificação mínima:

```bash
test -f plugins/StatusReport/Plugin.php && echo OK
```

---

## 4. Primeiro carregamento

Após colocar a pasta no diretório `plugins/`:

1. abrir o Kanboard;
2. confirmar que a tela de login ou página inicial carrega normalmente;
3. acessar **Configurações → Plugins**;
4. confirmar `StatusReport 1.0.0`;
5. confirmar que não há erro fatal de `HookHelper::asset()`;
6. confirmar que não há aviso de conflito, ou registrar o plugin conflitante se o modo restrito for ativado.

Resultado:

```text
[ ] Kanboard carregou normalmente
[ ] StatusReport 1.0.0 identificado
[ ] Sem fatal error de layout/assets
[ ] Sem conflito de comment/show
```

Se houver conflito de `comment/show`, o teste pode continuar em modo restrito, desde que isso seja registrado.

---

## 5. Runner técnico

### Instalação direta

Na raiz real do Kanboard:

```bash
php plugins/StatusReport/Test/run.php
```

### Docker

O runner deve ser executado dentro do runtime que contém `app/common.php`.

Exemplo genérico:

```bash
docker exec -it <container-kanboard> sh
cd /var/www/app
php plugins/StatusReport/Test/run.php
```

Não presumir `/var/www/app`; confirmar o caminho do ambiente.

### Resultado a registrar

```text
Kanboard: ______________________
StatusReport: 1.0.0
PHP: __________________________
Driver: _______________________
Foreign Keys: _________________
Verificações executadas: ______
Falhas: _______________________
```

Critério esperado do runner consolidado:

```text
43 verificações
0 falhas
```

Se qualquer verificação falhar, interromper a homologação funcional e registrar a saída completa.

---

## 6. Teste funcional pela interface

Criar uma tarefa exclusiva para teste.

Exemplo:

```text
Título: Homologação StatusReport
Descrição manual:
Objetivo: validar o plugin StatusReport.
```

### Teste A — descrição manual

Confirmar antes de usar o plugin:

```text
[ ] texto manual aparece no cartão
[ ] texto manual aparece dentro da tarefa
```

### Teste B — comentário comum

Adicionar:

```text
Primeiro desdobramento operacional.
```

Confirmar:

```text
[ ] comentário salvo normalmente
[ ] comentário não virou Situação Atual
[ ] descrição não foi substituída indevidamente
```

### Teste C — primeiro Informe de Situação

Usar **Adicionar Informe de Situação**:

```text
Demanda encaminhada para análise da área responsável.
```

Confirmar:

```text
[ ] Informe permanece no histórico
[ ] painel Situação Atual mostra o texto
[ ] cartão mostra Situação Atual na descrição
[ ] título Situação Atual está em negrito e fonte normal
[ ] delimitadores internos não aparecem no cartão
[ ] texto manual anterior permanece intacto
```

### Teste D — comentário após Informe

Adicionar comentário comum:

```text
Foi enviado e-mail solicitando prioridade.
```

Confirmar:

```text
[ ] comentário salvo
[ ] Situação Atual permanece a anterior
```

### Teste E — novo Informe

Adicionar:

```text
Análise iniciada, aguardando manifestação técnica.
```

Confirmar:

```text
[ ] novo Informe tornou-se Situação Atual
[ ] Informe anterior continua no histórico
[ ] cartão foi atualizado
[ ] não há duplicação do bloco na descrição
```

### Teste F — promoção de comentário comum

Em um comentário comum, utilizar **Definir como Situação Atual**.

Confirmar:

```text
[ ] não aparece Acesso negado
[ ] comentário passa a ser Informe
[ ] torna-se Situação Atual
[ ] cartão é atualizado
```

### Teste G — repromoção de Informe histórico

Escolher um Informe que já foi Situação Atual anteriormente e promovê-lo novamente.

Confirmar:

```text
[ ] Informe histórico volta a ser Situação Atual
[ ] situação que era atual continua como Informe histórico
[ ] não é criado comentário duplicado
[ ] apenas um comentário recebe sinalização de Situação Atual
```

### Teste H — edição do Informe vigente

Editar o comentário que representa a Situação Atual.

Confirmar:

```text
[ ] painel mostra texto editado
[ ] cartão mostra texto editado
[ ] Atualizado em corresponde ao maior valor entre classificação e edição
```

### Teste I — exclusão do Informe vigente

Excluir o comentário vigente.

Confirmar:

```text
[ ] Informe anterior volta a ser Situação Atual
[ ] cartão é recalculado
[ ] não há erro ou referência quebrada
```

### Teste J — descrição manual depois do plugin

Editar a tarefa e acrescentar fora do bloco da situação:

```text
Observação manual: informação adicional para o processo.
```

Registrar novo Informe.

Confirmar:

```text
[ ] observação manual continua existente
[ ] somente o trecho da Situação Atual foi atualizado
```

---

## 7. Teste de permissões

Quando possível, testar com pelo menos:

- membro do projeto;
- usuário apenas visualizador.

Confirmar:

```text
[ ] membro autorizado consegue registrar/promover Informe
[ ] visualizador não consegue registrar/promover Informe
```

---

## 8. Verificação de modo restrito

Se aparecer aviso de conflito:

Registrar:

```text
Plugin conflitante: ______________________
Template: comment/show
Arquivo informado: ______________________
```

Confirmar que continuam disponíveis:

```text
[ ] painel Situação Atual
[ ] Adicionar Informe de Situação
[ ] promoção pelo modal do painel
[ ] Situação Atual no cartão
```

É esperado que fiquem indisponíveis somente os elementos visuais adicionados diretamente ao histórico de comentários.

---

## 9. Critério de aceite

O plugin pode ser considerado tecnicamente apto no ambiente testado quando:

```text
[ ] Kanboard >= 1.2.50
[ ] runner concluído sem falhas
[ ] página/login sem fatal error
[ ] criação de Informe funcional
[ ] comentário comum não altera situação
[ ] promoção funcional sem erro CSRF
[ ] repromoção de Informe histórico funcional
[ ] exclusão restaura Informe anterior
[ ] Situação Atual visível no cartão
[ ] marcadores internos invisíveis
[ ] descrição manual preservada
[ ] ACL confirmada
[ ] conflitos de template inexistentes ou modo restrito compreendido
```

---

## 10. Registro final do teste

```text
Ambiente: ______________________________________________
Kanboard: ______________________________________________
PHP: ___________________________________________________
Banco: _________________________________________________
StatusReport: 1.0.0
Data: __________________________________________________
Responsável: ___________________________________________

Runner:      [ ] APROVADO  [ ] REPROVADO
Interface:   [ ] APROVADA  [ ] REPROVADA
Permissões:  [ ] APROVADAS [ ] REPROVADAS
Conflitos:   [ ] NÃO       [ ] SIM

Observações:
________________________________________________________
________________________________________________________
________________________________________________________

Resultado final:
[ ] APROVADO PARA USO
[ ] NECESSITA AJUSTE
```
