# Módulo cel_cash Boleto para WHMCS

[![versão](https://img.shields.io/github/v/release/gofas/gofasgalaxpayboleto?label=vers%C3%A3o&color=005071&style=flat-square)](https://github.com/gofas/gofasgalaxpayboleto/releases/latest)
[![downloads](https://img.shields.io/endpoint?url=https%3A%2F%2Fgofas.net%2Fwp-json%2Fgofas%2Fv1%2Fbadge%2Fgofasgalaxpayboleto&style=flat-square)](https://github.com/gofas/gofasgalaxpayboleto/releases/latest)
[![suporte](https://img.shields.io/badge/suporte-f%C3%B3rum%20gratuito-ff8700?style=flat-square)](https://gofas.net/foruns/)

Módulo gratuito de integração que automatiza a emissão de boletos registrados no WHMCS através da API cel_cash. Cobranças com o seu CPF ou CNPJ, sem carteira de cobrança e sem burocracia, com confirmação de pagamento e baixa automática das faturas. Desenvolvido pela Gofas Software.

## Sumário

- [Download](#download)
- [Funcionalidades](#funcionalidades)
- [Requisitos](#requisitos)
- [Instalação](#instalação)
- [Configuração](#configuração)
- [Informações importantes](#informações-importantes)
- [Suporte](#suporte)
- [Licença](#licença)

## Download

**[Baixar a versão mais recente](https://github.com/gofas/gofasgalaxpayboleto/releases/latest/download/gofasgalaxpayboleto.zip)**

## Funcionalidades

- **Boletos registrados** emitidos com o seu CPF ou CNPJ, sem carteira de cobrança
- **Confirmação automática de pagamento** e baixa nas faturas
- **Linha digitável na fatura**, com opção de copiar em um clique
- **Informações do boleto nos emails** das faturas
- **Redirecionamento para o boleto** ao acessar a fatura (opcional)
- **Mensagem personalizada** exibida na fatura
- **Valor mínimo** da fatura para permitir pagamento via boleto
- **Cálculo da tarifa** por transação confirmada, preenchendo o campo "Taxas" (fee) da lista de transações do WHMCS
- **Dispensa configuração de campos CPF/CNPJ**: o módulo detecta automaticamente os campos personalizados de clientes
- **Suporte a produção e a testes (sandbox)**
- **Logs de diagnóstico** configuráveis
- **Aviso de atualização** e verificação de versão na própria tela de configuração do módulo

## Requisitos

- WHMCS >= 8.0
- PHP >= 7.3
- Conta cel_cash com o módulo Webservice ativo
- Credenciais: Galax ID e Galax Hash (produção e testes)

## Instalação

1. Baixe o arquivo pelo link de download e descompacte. Será criada a pasta `gofasgalaxpayboleto`.
2. Copie as pastas `includes` e `modules` de dentro de `gofasgalaxpayboleto` para a raiz da instalação do WHMCS, mesclando com as pastas existentes.
3. Ative o módulo em `Opções > Pagamentos > Portais para Pagamentos > aba All Payment Gateways`.
4. Informe o Galax ID e o Galax Hash.

## Configuração

### Pré configuração na cel_cash

1. No painel administrativo, em `Módulos`, ative o módulo Webservice.
2. Em `Módulos > Webservice > Configurar`, copie as credenciais Galax ID e Galax Hash de produção.
3. Repita o processo no painel do modo de testes para obter as credenciais de sandbox. A homologação em sandbox é obrigatória antes de usar o Webservice em produção.

### Pré configuração no WHMCS

Crie um campo personalizado de cliente para CPF e/ou CNPJ, ou dois campos distintos, um para cada documento. O módulo identifica os campos automaticamente.

### Opções do módulo

<img src="https://raw.githubusercontent.com/gofas/gofasgalaxpayboleto/master/docs/img/tela-configuracoes-modulo-1.3.0.png" alt="Tela de configuracoes do modulo" width="640">

- **Galax ID** e **Galax Hash**: credenciais do Webservice em produção.
- **Galax ID** e **Galax Hash** (testes): credenciais do Webservice em sandbox.
- **Sandbox**: gera cobranças em modo de testes.
- **Salvar Logs**: grava informações de diagnóstico em `Utilitários > Logs > Log de Módulo`.
- **Valor mínimo**: valor mínimo da fatura para permitir pagamento via boleto.
- **Tarifa do Boleto**: valor em % pago por transação, usado para preencher o campo "Taxas" (fee) da transação no WHMCS.
- **Mensagem na fatura**: texto exibido na fatura, acima do botão de visualizar o boleto.
- **Redirecionar para o Boleto**: redireciona o cliente ao URL do boleto ao acessar a fatura.
- **Administrador do WHMCS**: administrador com permissão para usar a API interna do WHMCS.
- **Enviar estatísticas de uso (opcional)**: controla o envio identificado das estatísticas de confirmação de pagamento. Desmarcado, as confirmações continuam sendo contabilizadas de forma anônima.

## Informações importantes

- A tarifa do boleto é paga separadamente à cel_cash, conforme o plano da sua conta.
- Sempre faça backup antes de mudar algo no seu sistema.

## Suporte

Fórum de suporte gratuito: https://gofas.net/foruns/

## Licença

O código deste módulo é público para transparência e auditoria. Isso não transfere a titularidade nem concede licença livre de uso: o software é de propriedade da Gofas Software, protegido pela Lei 9.609/98 e pelos tratados de direitos autorais.

Trechos do [contrato de licença de uso](https://gofas.net/contrato-de-venda-de-licenca-de-uso-de-software/) que se aplicam diretamente a este repositório:

- **Não redistribuir**: é proibido o aluguel, o arrendamento, o empréstimo, a cessão e o licenciamento do software a terceiros, total ou parcial, assim como o fornecimento de serviços de hospedagem comercial do software (Cláusula 10ª, §3º).
- **Não modificar**: é vedado qualquer procedimento que implique engenharia reversa, descompilação, desmontagem, tradução, adaptação ou modificação do software, bem como qualquer alteração não autorizada de suas funcionalidades (Cláusula 10ª, §2º).
- **Módulo alterado perde o suporte**: a Gofas não se responsabiliza por defeitos decorrentes de alteração do software, de operação por pessoas não autorizadas ou da integração com softwares de terceiros (Cláusula 10ª, §7º). O suporte é uma cortesia e não é garantido pela licença (Cláusula 7ª, §1º).
