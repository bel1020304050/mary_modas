# Mary Modas — MVP para XAMPP/htdocs

Este MVP foi feito em PHP puro para rodar imediatamente no XAMPP, sem necessidade de configurar banco neste primeiro protótipo. Os dados ficam em arquivos JSON dentro de `data/`.

## Como rodar

1. Extraia a pasta `mary_modas_mvp` dentro de `C:\xampp\htdocs\`.
2. Inicie o **Apache** no XAMPP.
3. Abra no navegador:
   - `http://localhost/mary_modas_mvp/`
4. Use um dos acessos abaixo.

### Administrador
- E-mail: `admin@marymodas.local`
- Senha: `admin123`

### Cliente
- E-mail: `cliente@marymodas.local`
- Senha: `cliente123`

## Funcionalidades incluídas

- Login/logout e cadastro de cliente.
- Perfil do cliente com endereço.
- CRUD funcional de Produtos, Categorias, Fornecedores e Promoções.
- Administração de Usuários (perfil Cliente/Administrador e status).
- Catálogo com busca/filtros, tamanho, cor e estoque.
- Carrinho somente para cliente autenticado.
- Promoção automática e cupom; descontos não cumulativos.
- Criação única de pedido e bloqueio do carrinho durante pagamento.
- Pagamento simulado: PIX, Cartão de Crédito e Boleto.
- Retornos simulados: Aprovado, Pendente e Recusado.
- Baixa de estoque após pagamento aprovado.
- Cancelamento com estorno/devolução de estoque quando aplicável.
- Fluxo de pedido: Aguardando Pagamento → Pago → Em Separação → Enviado → Entregue.
- Relatórios de Vendas, Estoque e Clientes com exportação CSV.
- Estoque baixo definido como 10 unidades ou menos.

## Observação importante

Este MVP usa JSON somente para facilitar a demonstração em `htdocs`. A versão aprovada do projeto deve migrar a persistência para **MySQL/MariaDB** e, depois, para **Laravel + migrations**, mantendo as mesmas regras de negócio.

## Restaurar os dados de demonstração

Entre como Administrador e, na página inicial, use o botão **Restaurar demo**.
