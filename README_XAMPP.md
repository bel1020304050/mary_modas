# Mary Modas MVP — Como rodar com XAMPP

Este MVP foi feito para **validar o TCC com o orientador**: mostrar CRUDs, os 3 relatórios e o processo de compra. Ele usa **PHP 8 + MySQL/MariaDB sem framework**, propositalmente, para abrir no XAMPP com o mínimo de configuração. A documentação final continua prevendo Laravel; após a validação do orientador, a equipe pode migrar/reestruturar o backend para Laravel.

## Requisitos
- Windows com XAMPP instalado.
- Apache e MySQL do XAMPP.
- Navegador.

## 1. Copiar a pasta do projeto
1. Extraia o ZIP.
2. Copie a pasta `mary-modas-mvp` inteira para:
   `C:\xampp\htdocs\`
3. O caminho final deve ficar:
   `C:\xampp\htdocs\mary-modas-mvp\`

> Se mudar o nome da pasta, abra `includes/config.php` e altere `APP_BASE`.

## 2. Iniciar o XAMPP
1. Abra o **XAMPP Control Panel**.
2. Clique em **Start** no **Apache**.
3. Clique em **Start** no **MySQL**.

## 3. Criar/importar o banco
1. Abra no navegador: `http://localhost/phpmyadmin`
2. Clique em **Importar**.
3. Selecione o arquivo:
   `C:\xampp\htdocs\mary-modas-mvp\sql\database.sql`
4. Clique em **Importar/Executar**.

O script cria automaticamente o banco `mary_modas_mvp`, tabelas e dados de demonstração.

## 4. Abrir o sistema
Acesse:
`http://localhost/mary-modas-mvp/`

## Contas de demonstração
### Administrador
- E-mail: `admin@marymodas.local`
- Senha: `admin123`

### Cliente
- E-mail: `cliente@marymodas.local`
- Senha: `cliente123`

## Roteiro de demonstração para o orientador
1. Entre como **Administrador**.
2. Abra **Produtos** e cadastre/edite/inative um produto.
3. Mostre **Categorias**, **Subgrupos**, **Marcas**, **Formas de Pagamento** e **Clientes/Usuários**.
4. Abra os 3 relatórios:
   - Vendas por período
   - Produtos e estoque
   - Pedidos
5. Saia e entre como **Cliente**.
6. Adicione um produto ao carrinho.
7. Finalize a compra escolhendo **Aprovar pagamento**.
8. Mostre o fluxo: `Aguardando Pagamento → Pago → baixa do estoque → carrinho finalizado`.
9. Saia e entre novamente como Admin.
10. Abra **Pedidos**, altere para `Enviado` / `Em transporte`.
11. Entre como cliente e mostre o status atualizado.
12. Para provar a regra de cancelamento, como Admin cancele um pedido pago/em andamento e mostre que o estoque retorna.

## Relatórios em PDF
O botão **Exportar / Salvar PDF** abre a impressão do navegador. No Chrome/Edge escolha **Salvar como PDF**. Para o MVP isso evita dependências extras.

## Pagamento e entrega no MVP
- O pagamento é **simulado**, sem gateway real.
- Endereço, frete e transportadora estão **fora do escopo**.
- Entrega ou retirada é combinada fora do sistema.

## Se o banco tiver senha no seu XAMPP
Por padrão o XAMPP usa `root` sem senha. Se a sua instalação for diferente, altere:
`includes/config.php`

## Próximo passo depois da aprovação do orientador
- Migrar a camada backend para **Laravel**, conforme o pré-projeto.
- Adicionar validações mais completas, testes automatizados, auditoria, upload real de imagens e autenticação mais robusta.
- Manter o mesmo banco/fluxos como referência funcional.
